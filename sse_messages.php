<?php
// SSE pour le chat général avec infos utilisateurs
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');

if (function_exists('apache_setenv')) {
    apache_setenv('no-gzip', '1');
}
ini_set('output_buffering', 'off');
ini_set('zlib.output_compression', false);
@ini_set('implicit_flush', true);
@ob_end_flush();

$lastId = intval($_GET['last_id'] ?? 0);

function sendSSE($data) {
    echo "data: " . json_encode($data) . "\n\n";
    if (ob_get_level() > 0) ob_flush();
    flush();
}

// Premier envoi
try {
    $db = new SQLite3(__DIR__ . '/data/blitz.db');
    
    $stmt = $db->prepare('
        SELECT m.id, m.pseudo, m.message, m.timestamp, m.voice_url, u.profile_pic
        FROM messages m
        LEFT JOIN users u ON m.user_id = u.id
        WHERE m.id > ?
        ORDER BY m.id ASC
        LIMIT 50
    ');
    $stmt->bindValue(1, $lastId, SQLITE3_INTEGER);
    $result = $stmt->execute();
    
    $initialMessages = [];
    $maxId = $lastId;
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $initialMessages[] = $row;
        $maxId = max($maxId, $row['id']);
    }
    
    if (!empty($initialMessages)) {
        sendSSE(['messages' => $initialMessages, 'type' => 'initial']);
        $lastId = $maxId;
    }
    
    $db->close();
} catch (Exception $e) {
    sendSSE(['error' => $e->getMessage()]);
}

// Boucle de surveillance
$timeout = 60;
$startTime = time();
$lastCheck = 0;

try {
    while (time() - $startTime < $timeout) {
        if (time() - $lastCheck >= 1) {
            $lastCheck = time();
            
            $db = new SQLite3(__DIR__ . '/data/blitz.db');
            
            $stmt = $db->prepare('
                SELECT m.id, m.pseudo, m.message, m.timestamp, m.voice_url, u.profile_pic
                FROM messages m
                LEFT JOIN users u ON m.user_id = u.id
                WHERE m.id > ?
                ORDER BY m.id ASC
            ');
            $stmt->bindValue(1, $lastId, SQLITE3_INTEGER);
            $result = $stmt->execute();
            
            $newMessages = [];
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $newMessages[] = $row;
                $lastId = $row['id'];
            }
            
            $db->close();
            
            if (!empty($newMessages)) {
                sendSSE(['messages' => $newMessages, 'type' => 'update']);
            }
        }
        
        if (time() % 15 == 0 && time() - $lastCheck < 1) {
            sendSSE(['ping' => time()]);
        }
        
        usleep(200000);
    }
} catch (Exception $e) {
    sendSSE(['error' => $e->getMessage()]);
}
?>
