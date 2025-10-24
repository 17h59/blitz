<?php
// Récupère la liste des utilisateurs en ligne avec leurs infos
header('Content-Type: application/json');
error_reporting(0);

try {
    $db = new SQLite3(__DIR__ . '/data/blitz.db');
    
    // Récupérer les utilisateurs en ligne avec leurs photos de profil
    $result = $db->query('
        SELECT o.pseudo, u.profile_pic, u.is_admin
        FROM online_users o
        LEFT JOIN users u ON o.user_id = u.id
        WHERE o.is_ghost = 0
        ORDER BY o.pseudo ASC
    ');
    
    $users = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $users[] = [
            'pseudo' => $row['pseudo'],
            'profile_pic' => $row['profile_pic'],
            'is_admin' => $row['is_admin']
        ];
    }
    
    $db->close();
    
    echo json_encode(['success' => true, 'users' => $users]);
} catch (Exception $e) {
    echo json_encode(['success' => true, 'users' => []]);
}
?>
