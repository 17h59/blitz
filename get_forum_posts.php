<?php
// Récupère les posts du forum avec tri
header('Content-Type: application/json');
error_reporting(0);

$filter = $_GET['filter'] ?? 'recent';
$limit = intval($_GET['limit'] ?? 20);
$offset = intval($_GET['offset'] ?? 0);

try {
    $db = new SQLite3(__DIR__ . '/data/blitz.db');
    
    // Construire la requête selon le filtre
    $orderBy = 'created_at DESC';
    switch ($filter) {
        case 'top':
            $orderBy = 'upvotes DESC, downvotes ASC';
            break;
        case 'bottom':
            $orderBy = 'upvotes ASC, downvotes DESC';
            break;
        case 'activity':
            $orderBy = '(upvotes + downvotes + comment_count) DESC';
            break;
        case 'recent':
        default:
            $orderBy = 'created_at DESC';
            break;
    }
    
    $query = "
        SELECT 
            p.id,
            p.user_id,
            p.pseudo,
            p.title,
            p.content,
            p.media_url,
            p.media_type,
            p.youtube_url,
            p.is_repost,
            p.original_post_id,
            p.upvotes,
            p.downvotes,
            p.comment_count,
            p.created_at,
            u.profile_pic
        FROM forum_posts p
        LEFT JOIN users u ON p.user_id = u.id
        ORDER BY $orderBy
        LIMIT ? OFFSET ?
    ";
    
    $stmt = $db->prepare($query);
    $stmt->bindValue(1, $limit, SQLITE3_INTEGER);
    $stmt->bindValue(2, $offset, SQLITE3_INTEGER);
    $result = $stmt->execute();
    
    $posts = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $posts[] = $row;
    }
    
    // Compter le total
    $totalResult = $db->query('SELECT COUNT(*) as count FROM forum_posts');
    $totalRow = $totalResult->fetchArray(SQLITE3_ASSOC);
    $total = $totalRow['count'];
    
    $db->close();
    
    echo json_encode([
        'success' => true,
        'posts' => $posts,
        'total' => $total,
        'has_more' => ($offset + $limit) < $total
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Erreur serveur', 'posts' => []]);
}
?>
