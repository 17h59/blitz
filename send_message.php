<?php
// Envoie un message dans le chat général (SQLite avec users)
header('Content-Type: application/json');
error_reporting(0);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit(json_encode(['success' => false]));
}

$userId = intval($_POST['user_id'] ?? 0);
$pseudo = trim($_POST['pseudo'] ?? '');
$message = trim($_POST['message'] ?? '');

if (empty($userId) || empty($pseudo) || empty($message) || strlen($message) > 500) {
    exit(json_encode(['success' => false]));
}

// Échapper pour XSS
$pseudo = htmlspecialchars($pseudo, ENT_NOQUOTES, 'UTF-8');
$message = htmlspecialchars($message, ENT_NOQUOTES, 'UTF-8');

try {
    $db = new SQLite3(__DIR__ . '/data/blitz.db');
    
    // Vérifier que l'utilisateur existe
    $stmt = $db->prepare('SELECT id FROM users WHERE id = ? AND pseudo = ?');
    $stmt->bindValue(1, $userId, SQLITE3_INTEGER);
    $stmt->bindValue(2, $pseudo, SQLITE3_TEXT);
    $result = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    
    if (!$result) {
        exit(json_encode(['success' => false, 'error' => 'Utilisateur invalide']));
    }
    
    // Insérer le message
    $stmt = $db->prepare('INSERT INTO messages (user_id, pseudo, message, timestamp) VALUES (?, ?, ?, ?)');
    $stmt->bindValue(1, $userId, SQLITE3_INTEGER);
    $stmt->bindValue(2, $pseudo, SQLITE3_TEXT);
    $stmt->bindValue(3, $message, SQLITE3_TEXT);
    $stmt->bindValue(4, time(), SQLITE3_INTEGER);
    $stmt->execute();
    
    $messageId = $db->lastInsertRowID();
    
    // Nettoyer les vieux messages (garder les 300 derniers)
    $db->exec('DELETE FROM messages WHERE id NOT IN (SELECT id FROM messages ORDER BY id DESC LIMIT 300)');
    
    $db->close();
    
    echo json_encode(['success' => true, 'id' => $messageId]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
}
?>
