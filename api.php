<?php
// api.php - the whole backend: sends, fetches new messages, fetches history
header('Content-Type: application/json');

// ---- DB connection (XAMPP/WAMP default: root, no password) ----
$pdo = new PDO('mysql:host=localhost;charset=utf8mb4', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
// Auto-setup so you need no .sql file (remove these 3 lines once it works, for speed)
$pdo->exec("CREATE DATABASE IF NOT EXISTS chat_demo");
$pdo->exec("USE chat_demo");
$pdo->exec("CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender VARCHAR(30) NOT NULL,
    receiver VARCHAR(30) NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX pair (sender, receiver)
)");

$action = $_REQUEST['action'] ?? '';
$me     = trim($_REQUEST['me'] ?? '');
$other  = trim($_REQUEST['other'] ?? '');

if ($me === '' || $other === '') {
    echo json_encode(['ok' => false, 'error' => 'names required']);
    exit;
}

// Messages between the two users, in both directions
$pair = "((sender=? AND receiver=?) OR (sender=? AND receiver=?))";
$pairParams = [$me, $other, $other, $me];

if ($action === 'send') {
    $text = trim($_POST['message'] ?? '');
    if ($text === '') { echo json_encode(['ok' => false]); exit; }
    $st = $pdo->prepare("INSERT INTO messages (sender, receiver, message) VALUES (?,?,?)");
    $st->execute([$me, $other, $text]);
    echo json_encode(['ok' => true, 'id' => $pdo->lastInsertId()]);

} elseif ($action === 'new') {            // polling: only messages newer than 'after'
    $after = (int)($_GET['after'] ?? 0);
    $st = $pdo->prepare("SELECT * FROM messages WHERE $pair AND id > ? ORDER BY id ASC");
    $st->execute([...$pairParams, $after]);
    echo json_encode($st->fetchAll());

} elseif ($action === 'old') {            // history: 10 messages older than 'before' (0 = latest 10)
    $before = (int)($_GET['before'] ?? 0);
    $st = $pdo->prepare("SELECT * FROM (
            SELECT * FROM messages WHERE $pair AND (? = 0 OR id < ?) ORDER BY id DESC LIMIT 10
        ) t ORDER BY id ASC");
    $st->execute([...$pairParams, $before, $before]);
    echo json_encode($st->fetchAll());
}
