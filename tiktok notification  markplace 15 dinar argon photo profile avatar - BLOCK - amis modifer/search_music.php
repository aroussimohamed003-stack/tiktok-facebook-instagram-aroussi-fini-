<?php
header('Content-Type: application/json');

// --- iTunes Search API (No Keys Required) ---

if (!isset($_GET['q']) || empty($_GET['q'])) {
    // Default popular search if empty (Top songs)
    $query = 'top hits';
} else {
    $query = urlencode($_GET['q']);
}

// iTunes API URL
$url = "https://itunes.apple.com/search?term=$query&media=music&entity=song&limit=20";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
// iTunes sometimes requires a User-Agent
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$result = curl_exec($ch);

if (curl_errno($ch)) {
    echo json_encode(['error' => 'Curl error: ' . curl_error($ch)]);
    exit;
}
curl_close($ch);

$json = json_decode($result, true);

$tracks = [];
if (isset($json['results'])) {
    foreach ($json['results'] as $item) {
        if (!empty($item['previewUrl'])) {
            // Get higher quality artwork (replace 100x100 with 600x600)
            $image = str_replace('100x100bb.jpg', '600x600bb.jpg', $item['artworkUrl100']);
            
            $tracks[] = [
                'id' => $item['trackId'],
                'name' => $item['trackName'],
                'artist' => $item['artistName'],
                'preview_url' => $item['previewUrl'],
                'image' => $image
            ];
        }
    }
}

echo json_encode(['success' => true, 'tracks' => $tracks]);
?>
