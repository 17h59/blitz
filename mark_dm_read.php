<?php
// Marque les DMs comme lus
header('Content-Type: application/json');
error_reporting(0);

session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['pseudo'])) {
    exit(json_encode(['success' => false, 'error' => 'Non authentifié']));
}

$fromUser = $_POST['from_user'] ?? '';

if (empty($fromUser)) {
    exit(json_encode(['success' => false, 'error' => 'Utilisateur requis']));
}

try {
    $db = new SQLite3(__DIR__ . '/data/blitz.db');

    $currentPseudo = $_SESSION['pseudo'];

    // Marquer tous les messages de cet utilisateur comme lus
    $stmt = $db->prepare('UPDATE dms SET read = 1 WHERE from_user = ? AND to_user = ? AND read = 0');
    $stmt->bindValue(1, $fromUser, SQLITE3_TEXT);
    $stmt->bindValue(2, $currentPseudo, SQLITE3_TEXT);
    $stmt->execute();

    $db->close();

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
}
?>
