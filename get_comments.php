<?php
// Récupère les commentaires d'un post (avec imbrication)
header('Content-Type: application/json');
error_reporting(0);

$postId = intval($_GET['post_id'] ?? 0);

if ($postId <= 0) {
    exit(json_encode(['success' => false, 'error' => 'Post ID invalide', 'comments' => []]));
}

try {
    $db = new SQLite3(__DIR__ . '/data/blitz.db');
    
    // Récupérer tous les commentaires du post
    $stmt = $db->prepare('
        SELECT c.id, c.post_id, c.user_id, c.pseudo, c.content, c.parent_comment_id, c.upvotes, c.downvotes, c.created_at, u.profile_pic
        FROM forum_comments c
        LEFT JOIN users u ON c.user_id = u.id
        WHERE c.post_id = ?
        ORDER BY c.created_at ASC
    ');
    $stmt->bindValue(1, $postId, SQLITE3_INTEGER);
    $result = $stmt->execute();
    
    $comments = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $comments[] = $row;
    }
    
    $db->close();
    
    // Organiser les commentaires en arbre (parent-child)
    $commentTree = [];
    $commentMap = [];
    
    // Première passe : créer un map de tous les commentaires
    foreach ($comments as $comment) {
        $comment['replies'] = [];
        $commentMap[$comment['id']] = $comment;
    }
    
    // Deuxième passe : construire l'arbre
    foreach ($commentMap as $id => $comment) {
        if ($comment['parent_comment_id'] === null) {
            // C'est un commentaire racine
            $commentTree[] = &$commentMap[$id];
        } else {
            // C'est une réponse
            if (isset($commentMap[$comment['parent_comment_id']])) {
                $commentMap[$comment['parent_comment_id']]['replies'][] = &$commentMap[$id];
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'comments' => $commentTree,
        'total' => count($comments)
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Erreur serveur', 'comments' => []]);
}
?>
