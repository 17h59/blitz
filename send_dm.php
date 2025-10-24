<?php
// Envoi de message privé (SQLite)
header('Content-Type: application/json');
error_reporting(0);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit(json_encode(['success' => false, 'error' => 'Méthode invalide']));
}

$from = trim($_POST['from'] ?? '');
$to = trim($_POST['to'] ?? '');
$message = trim($_POST['message'] ?? '');

if (empty($from) || empty($to) || empty($message) || strlen($message) > 500) {
    exit(json_encode(['success' => false, 'error' => 'Données invalides']));
}

// Échapper pour XSS
$from = htmlspecialchars($from, ENT_NOQUOTES, 'UTF-8');
$to = htmlspecialchars($to, ENT_NOQUOTES, 'UTF-8');
$message = htmlspecialchars($message, ENT_NOQUOTES, 'UTF-8');

try {
    $db = new SQLite3(__DIR__ . '/data/blitz.db');
    
    // Récupérer les IDs des utilisateurs
    $stmt = $db->prepare('SELECT id, pseudo FROM users WHERE pseudo IN (?, ?)');
    $stmt->bindValue(1, $from, SQLITE3_TEXT);
    $stmt->bindValue(2, $to, SQLITE3_TEXT);
    $result = $stmt->execute();
    
    $users = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $users[$row['pseudo']] = $row['id'];
    }
    
    if (count($users) < 2) {
        exit(json_encode(['success' => false, 'error' => 'Utilisateur introuvable']));
    }
    
    $fromId = $users[$from];
    $toId = $users[$to];
    
    // Insérer le message privé
    $stmt = $db->prepare('INSERT INTO dms (from_user_id, from_user, to_user_id, to_user, message, timestamp) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->bindValue(1, $fromId, SQLITE3_INTEGER);
    $stmt->bindValue(2, $from, SQLITE3_TEXT);
    $stmt->bindValue(3, $toId, SQLITE3_INTEGER);
    $stmt->bindValue(4, $to, SQLITE3_TEXT);
    $stmt->bindValue(5, $message, SQLITE3_TEXT);
    $stmt->bindValue(6, time(), SQLITE3_INTEGER);
    $stmt->execute();
    
    $messageId = $db->lastInsertRowID();
    
    $db->close();
    
    echo json_encode(['success' => true, 'id' => $messageId]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Erreur serveur: ' . $e->getMessage()]);
}
?>
