<?php
header('Content-Type: application/json');

$file = 'rooms.json';
$action = $_GET['action'] ?? '';
$room = $_GET['room'] ?? '';
$user = $_GET['user'] ?? '';
$id = $_GET['id'] ?? '';

if (!file_exists($file)) {
    file_put_contents($file, json_encode([]));
}

$data = json_decode(file_get_contents($file), true);

// Clean up old rooms/users (older than 1 hour)
foreach ($data as $r => $users) {
    foreach ($users as $uid => $info) {
        if (time() - $info['last_seen'] > 10) { // 10 seconds timeout for heartbeat
            unset($data[$r][$uid]);
        }
    }
    if (empty($data[$r])) {
        unset($data[$r]);
    }
}

if ($action === 'join' && $room && $user && $id) {
    if (!isset($data[$room])) $data[$room] = [];
    $data[$room][$id] = [
        'user' => $user,
        'type' => 'peer',
        'last_seen' => time()
    ];
} elseif ($action === 'heartbeat' && $room && $id) {
    if (isset($data[$room][$id])) {
        $data[$room][$id]['last_seen'] = time();
    }
} elseif ($action === 'leave' && $room && $id) {
    if (isset($data[$room][$id])) {
        unset($data[$room][$id]);
    }
} elseif ($action === 'list' && $room) {
    // Return list
}

file_put_contents($file, json_encode($data));

echo json_encode($data[$room] ?? []);
?>
