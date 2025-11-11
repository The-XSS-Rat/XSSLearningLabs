<?php
// init_db.php - initializes SQLite DB if missing
$dbfile = __DIR__ . '/db.sqlite';
if (!file_exists($dbfile)) {
    $db = new PDO('sqlite:' . $dbfile);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec("CREATE TABLE stored_messages (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        title TEXT,
        body TEXT
    )");
    $db->exec("CREATE TABLE blind_hits (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        remote_addr TEXT,
        user_agent TEXT,
        params TEXT
    )");
    echo "DB created at $dbfile\n";
} else {
    // DB exists; nothing to do
}
