<?php
// Supprimer un post (auteur ou admin)
header('Content-Type: application/json');
error_reporting(0);

session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit(json_encode(['success' => false, 'error' => 'Méthode invalide']));
}

if (!isset($_SESSION['user_id'])) {
    exit(json_encode(['success' => false, 'error' => 'Non authentifié']));
}

$postId = intval($_POST['post_id'] ?? 0);

if ($postId <= 0) {
    exit(json_encode(['success' => false, 'error' => 'Post ID invalide']));
}

try {
    $db = new SQLite3(__DIR__ . '/data/blitz.db');
    
    // Récupérer le post
    $stmt = $db->prepare('SELECT user_id, media_url FROM forum_posts WHERE id = ?');
    $stmt->bindValue(1, $postId, SQLITE3_INTEGER);
    $result = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    
    if (!$result) {
        exit(json_encode(['success' => false, 'error' => 'Post introuvable']));
    }
    
    // Vérifier les permissions (auteur ou admin)
    $isAdmin = $_SESSION['is_admin'] ?? false;
    if ($result['user_id'] !== $_SESSION['user_id'] && !$isAdmin) {
        exit(json_encode(['success' => false, 'error' => 'Permission refusée']));
    }
    
    // Supprimer le fichier média si existe
    if ($result['media_url'] && file_exists(__DIR__ . $result['media_url'])) {
        unlink(__DIR__ . $result['media_url']);
    }
    
    // Supprimer le post (CASCADE supprimera les commentaires et votes)
    $stmt = $db->prepare('DELETE FROM forum_posts WHERE id = ?');
    $stmt->bindValue(1, $postId, SQLITE3_INTEGER);
    $stmt->execute();
    
    $db->close();
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
}
?>
