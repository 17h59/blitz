<?php
// Récupère les messages privés entre deux utilisateurs
header('Content-Type: application/json');
error_reporting(0);

$user1 = trim($_GET['user1'] ?? '');
$user2 = trim($_GET['user2'] ?? '');
$lastId = intval($_GET['last_id'] ?? 0);

if (empty($user1) || empty($user2)) {
    exit(json_encode(['messages' => []]));
}

try {
    $db = new SQLite3(__DIR__ . '/data/blitz.db');
    
    if ($lastId > 0) {
        // Nouveaux messages depuis last_id
        $stmt = $db->prepare('
            SELECT id, from_user, to_user, message, timestamp
            FROM dms
            WHERE id > ?
            AND ((from_user = ? AND to_user = ?) OR (from_user = ? AND to_user = ?))
            ORDER BY id ASC
            LIMIT 50
        ');
        $stmt->bindValue(1, $lastId, SQLITE3_INTEGER);
        $stmt->bindValue(2, $user1, SQLITE3_TEXT);
        $stmt->bindValue(3, $user2, SQLITE3_TEXT);
        $stmt->bindValue(4, $user2, SQLITE3_TEXT);
        $stmt->bindValue(5, $user1, SQLITE3_TEXT);
    } else {
        // Tous les messages (max 100)
        $stmt = $db->prepare('
            SELECT id, from_user, to_user, message, timestamp
            FROM dms
            WHERE (from_user = ? AND to_user = ?) OR (from_user = ? AND to_user = ?)
            ORDER BY id ASC
            LIMIT 100
        ');
        $stmt->bindValue(1, $user1, SQLITE3_TEXT);
        $stmt->bindValue(2, $user2, SQLITE3_TEXT);
        $stmt->bindValue(3, $user2, SQLITE3_TEXT);
        $stmt->bindValue(4, $user1, SQLITE3_TEXT);
    }
    
    $result = $stmt->execute();
    
    $messages = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $messages[] = $row;
    }
    
    $db->close();
    
    echo json_encode(['messages' => $messages]);
} catch (Exception $e) {
    echo json_encode(['messages' => [], 'error' => $e->getMessage()]);
}
?>
