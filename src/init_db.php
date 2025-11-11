<?php
// init_db.php - initializes SQLite DB if missing
$dbfile = __DIR__ . '/db.sqlite';
$dbExists = file_exists($dbfile);
$db = new PDO('sqlite:' . $dbfile);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// create tables if they do not exist so new labs work on old DBs too
$db->exec("CREATE TABLE IF NOT EXISTS stored_messages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    title TEXT,
    body TEXT
)");

$db->exec("CREATE TABLE IF NOT EXISTS blind_hits (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    remote_addr TEXT,
    user_agent TEXT,
    params TEXT
)");

$db->exec("CREATE TABLE IF NOT EXISTS playground_entries (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    scenario TEXT,
    content TEXT
)");

if (!$dbExists) {
    echo "DB created at $dbfile\n";
}
