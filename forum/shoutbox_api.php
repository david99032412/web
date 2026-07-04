<?php
// forum/shoutbox_api.php - FULLOS VERZIÓ (AVATÁROKKAL)
require 'config.php';
require 'functions.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

if ($action == 'get') {
    try {
        $stmt = $pdo->query("
            SELECT s.*, a.username, a.forum_avatar, COALESCE(a.adminLevel, a.AdminLevel, 0) as adminLevel 
            FROM forum_shoutbox s 
            JOIN accounts a ON s.user_id = a.accountId 
            ORDER BY s.created_at DESC LIMIT 20
        ");
        $msgs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($msgs);
    } catch (PDOException $e) {
        echo json_encode([]); 
    }
    exit;
}

if ($action == 'send' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isLoggedIn()) {
        echo json_encode(['status' => 'error', 'message' => 'Jelentkezz be!']);
        exit;
    }

    $userId = $_SESSION['user_id'];
    $message = clean($_POST['message']);

    if (!empty($message)) {
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS forum_shoutbox (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                message VARCHAR(255) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

            $stmt = $pdo->prepare("INSERT INTO forum_shoutbox (user_id, message) VALUES (?, ?)");
            $stmt->execute([$userId, $message]);
            echo json_encode(['status' => 'success']);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Adatbázis hiba!']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Üres üzenet!']);
    }
    exit;
}
?>
