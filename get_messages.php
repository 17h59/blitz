<?php
// Récupère les messages du chat général avec infos utilisateurs
header('Content-Type: application/json');
error_reporting(0);

$lastId = intval($_GET['last_id'] ?? 0);

try {
    $db = new SQLite3(__DIR__ . '/data/blitz.db');
    
    if ($lastId > 0) {
        // Nouveaux messages depuis last_id
        $stmt = $db->prepare('
            SELECT m.id, m.pseudo, m.message, m.timestamp, m.voice_url, u.profile_pic
            FROM messages m
            LEFT JOIN users u ON m.user_id = u.id
            WHERE m.id > ?
            ORDER BY m.id ASC
            LIMIT 50
        ');
        $stmt->bindValue(1, $lastId, SQLITE3_INTEGER);
    } else {
        // Tous les messages (max 300)
        $stmt = $db->prepare('
            SELECT m.id, m.pseudo, m.message, m.timestamp, m.voice_url, u.profile_pic
            FROM messages m
            LEFT JOIN users u ON m.user_id = u.id
            ORDER BY m.id DESC
            LIMIT 300
        ');
    }
    
    $result = $stmt->execute();
    
    $messages = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $messages[] = $row;
    }
    
    // Si on a pris les derniers, inverser pour ordre chronologique
    if ($lastId === 0) {
        $messages = array_reverse($messages);
    }
    
    $db->close();
    
    echo json_encode(['messages' => $messages]);
} catch (Exception $e) {
    echo json_encode(['messages' => []]);
}
?>
