<?php
// Met à jour la présence de l'utilisateur (SQLite avec users)
header('Content-Type: application/json');
error_reporting(0);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit(json_encode(['success' => false]));
}

$userId = intval($_POST['user_id'] ?? 0);
$pseudo = trim($_POST['pseudo'] ?? '');
$sessionId = trim($_POST['session_id'] ?? '');

if (empty($userId) || empty($pseudo) || empty($sessionId)) {
    exit(json_encode(['success' => false]));
}

$pseudo = htmlspecialchars($pseudo, ENT_NOQUOTES, 'UTF-8');

try {
    $db = new SQLite3(__DIR__ . '/data/blitz.db');
    
    // Nettoyer les utilisateurs inactifs (> 2 minutes)
    $timeout = time() - 120;
    $db->exec("DELETE FROM online_users WHERE last_seen < $timeout");
    
    // Nettoyer aussi les comptes temporaires inactifs (> 2 minutes)
    $db->exec("DELETE FROM users WHERE is_temporary = 1 AND last_login < $timeout");
    
    // Insérer ou mettre à jour l'utilisateur
    $stmt = $db->prepare('INSERT OR REPLACE INTO online_users (session_id, user_id, pseudo, last_seen) VALUES (?, ?, ?, ?)');
    $stmt->bindValue(1, $sessionId, SQLITE3_TEXT);
    $stmt->bindValue(2, $userId, SQLITE3_INTEGER);
    $stmt->bindValue(3, $pseudo, SQLITE3_TEXT);
    $stmt->bindValue(4, time(), SQLITE3_INTEGER);
    $stmt->execute();
    
    // Compter les utilisateurs en ligne
    $result = $db->query('SELECT COUNT(*) as count FROM online_users WHERE is_ghost = 0');
    $row = $result->fetchArray(SQLITE3_ASSOC);
    $count = $row['count'];
    
    $db->close();
    
    echo json_encode(['success' => true, 'online_count' => $count]);
} catch (Exception $e) {
    echo json_encode(['success' => false]);
}
?>
