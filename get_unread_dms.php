<?php
// Récupère les DMs non lus pour l'utilisateur courant
header('Content-Type: application/json');
error_reporting(0);

session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['pseudo'])) {
    exit(json_encode(['success' => false, 'error' => 'Non authentifié']));
}

try {
    $db = new SQLite3(__DIR__ . '/data/blitz.db');

    $currentUserId = $_SESSION['user_id'];
    $currentPseudo = $_SESSION['pseudo'];

    // Récupérer tous les utilisateurs avec qui on a des DMs non lus
    $query = "
        SELECT
            from_user,
            COUNT(*) as unread_count,
            MAX(message) as last_message,
            MAX(timestamp) as last_timestamp
        FROM dms
        WHERE to_user = ? AND read = 0
        GROUP BY from_user
        ORDER BY last_timestamp DESC
    ";

    $stmt = $db->prepare($query);
    $stmt->bindValue(1, $currentPseudo, SQLITE3_TEXT);
    $result = $stmt->execute();

    $unread = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $unread[$row['from_user']] = [
            'count' => $row['unread_count'],
            'last_message' => $row['last_message'],
            'last_timestamp' => $row['last_timestamp']
        ];
    }

    $db->close();

    echo json_encode([
        'success' => true,
        'unread' => $unread
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
}
?>
