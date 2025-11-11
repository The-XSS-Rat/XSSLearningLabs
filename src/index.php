<?php
// index.php - XSS teaching lab (single-file router)
require_once __DIR__ . '/init_db.php';
$dbfile = __DIR__ . '/db.sqlite';
$db = new PDO('sqlite:' . $dbfile);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$page = $_GET['page'] ?? 'home';

// server-side basic filter levels for the Filter Lab
function filter_input_level($input, $level){
    if ($level === 'none') return $input;
    if ($level === 'naive') {
        // naive strip of <script> tags and common event handlers
        $out = preg_replace('#<\s*script[^>]*>(.*?)<\s*/\s*script>#is','',$input);
        $out = preg_replace('#on[a-z]+\s*=\s*["\'].*?["\']#is','',$out);
        return $out;
    }
    if ($level === 'strip_tags') {
        return strip_tags($input);
    }
    if ($level === 'encode') {
        return htmlspecialchars($input, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
    if ($level === 'regex') {
        // remove <, > and javascript: pseudo-protocol occurrences
        $out = preg_replace('/<|>|javascript:/i','',$input);
        return $out;
    }
    // default fallback
    return $input;
}

// helper for rendering header
function header_html($title='XSS Lab'){
    echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.htmlspecialchars($title).'</title>';
    echo '<link rel="stylesheet" href="styles.css">';
    echo '</head><body>';
    echo '<div class="container">';
    // sidebar
    echo '<div class="sidebar card">';
    echo '<div class="logo">The XSS Rat — XSS Lab</div>';
    $tabs = ['home'=>'Home','reflected'=>'Reflected','stored'=>'Stored','dom'=>'DOM','blind'=>'Blind','filter'=>'Filter','contexts'=>'Contexts','bypasses'=>'Bypasses'];
    foreach ($tabs as $k=>$v){
        echo '<div><a class="nav-item button" href="#" data-page="'.htmlspecialchars($k).'">'.htmlspecialchars($v).'</a></div>';
    }
    echo '<div class="small" style="margin-top:12px">Warning: intentionally vulnerable. Run locally.</div>';
    echo '</div>'; // sidebar end
    echo '<div>'; // main
    echo '<div class="card">';
    echo '<div class="lab-title">'.htmlspecialchars($title).'</div>';
}

// footer
function footer_html(){
    echo '</div>'; // card
    echo '</div>'; // main
    echo '</div>'; // container
    echo '<script src="app.js"></script>';
    echo '</body></html>';
}

// ROUTES
if ($page === 'home'){
    header_html('Home — XSS Lab');
    echo '<div class="small">Choose a lab on the left. Focus an input to highlight information about it on the right.</div>';
    echo '<div style="margin-top:12px" class="meta">Quick testing tips: try basic payloads like <code>&lt;script&gt;alert(1)&lt;/script&gt;</code> for reflected/stored; use location.hash for DOM XSS; for blind XSS, send a payload that calls new Image().src to /blind_logger.php?p=PAYLOAD</div>';
    footer_html();
    exit;
}

if ($page === 'reflected'){
    header_html('Reflected XSS');
    // echo back GET param 'q' unsafely
    $q = $_GET['q'] ?? '';
    echo '<form method="GET" action="?"><input type="hidden" name="page" value="reflected">';
    echo '<div class="form-row"><input class="input" name="q" data-field="q" placeholder="Enter text to reflect (try scripts)"></div>';
    echo '<div class="form-row"><button class="button">Submit (reflect)</button></div>';
    echo '</form>';
    echo '<div class="meta"><div class="meta-item" data-field="q"><strong>Reflected input</strong><div class="small">This input is echoed directly into the page without encoding — classic reflected XSS.</div></div></div>';
    echo '<div class="results">Result area:<div style="padding:8px;background:#06121a;margin-top:8px">';
    // intentionally vulnerable echo
    echo $q;
    echo '</div></div>';
    footer_html();
    exit;
}

if ($page === 'stored'){
    header_html('Stored XSS');
    // handle posting messages into sqlite
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['title'])){
        $st = $db->prepare('INSERT INTO stored_messages (title, body) VALUES (:t,:b)');
        $st->execute([':t'=>$_POST['title'], ':b'=>$_POST['body']]);
        echo '<div class="small">Stored. Scroll down to see messages (unsanitized output).</div>';
    }
    echo '<form method="POST" action="?page=stored">';
    echo '<div class="form-row"><input class="input" name="title" data-field="title" placeholder="Title"></div>';
    echo '<div class="form-row"><textarea class="input" name="body" data-field="body" placeholder="Message body (try XSS payloads)" rows="4"></textarea></div>';
    echo '<div class="form-row"><button class="button">Submit (store)</button></div>';
    echo '</form>';
    echo '<div class="meta"><div class="meta-item" data-field="body"><strong>Stored messages</strong><div class="small">Messages are stored in SQLite and printed back unsanitized.</div></div></div>';
    echo '<div class="results">';
    $rows = $db->query('SELECT id,title,body,created_at FROM stored_messages ORDER BY id DESC LIMIT 30')->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r){
        echo '<div style="border-bottom:1px solid rgba(255,255,255,0.02);padding:8px;margin-bottom:8px">';
        echo '<div class="small">#'.intval($r['id']).' - '.htmlspecialchars($r['created_at']).' - <strong>'.htmlspecialchars($r['title']).'</strong></div>';
        // UNSAFE: render body raw to demonstrate XSS
        echo '<div style="margin-top:6px">'. $r['body'] .'</div>';
        echo '</div>';
    }
    echo '</div>';
    footer_html();
    exit;
}

if ($page === 'dom'){
    header_html('DOM XSS');
    // example using location.hash insertion into innerHTML
    echo '<div class="small">This page demonstrates DOM-based XSS using <code>location.hash</code> and unsafe innerHTML usage.</div>';
    echo '<div class="form-row"><input class="input" id="dom-input" data-field="dom-input" placeholder="Change the URL hash or try a payload and click: Set hash"></div>';
    echo '<div class="form-row"><button class="button" onclick="location.hash = document.getElementById(\'dom-input\').value">Set hash</button></div>';
    echo '<div class="meta"><div class="meta-item" data-field="dom-input"><strong>DOM sink</strong><div class="small">This demo writes <code>location.hash</code> into the page using <code>innerHTML</code>.</div></div></div>';
    echo '<div class="results"><div id="dom-sink">Hash will be shown here</div></div>';
    echo '<script>
(function(){
  var el = document.getElementById("dom-sink");
  // intentionally unsafe: writes location.hash into innerHTML
  function render(){ el.innerHTML = location.hash ? decodeURIComponent(location.hash.substring(1)) : "(no hash)"; }
  window.addEventListener("hashchange", render);
  render();
})();
</script>';
    footer_html();
    exit;
}

if ($page === 'blind'){
    header_html('Blind XSS');
    echo '<div class="small">Blind XSS sends a payload that executes in a target context elsewhere and triggers /blind_logger.php. Use payloads that create an image or script loader pointing to /blind_logger.php.</div>';
    echo '<form method="GET" action="?page=blind"><div class="form-row"><input class="input" name="payload" data-field="payload" placeholder="Example: &lt;img src=\'/blind_logger.php?p=1\'&gt;"></div><div class="form-row"><button class="button">Generate payload</button></div></form>';
    $payload = $_GET['payload'] ?? '';
    if ($payload){
        echo '<div class="meta"><strong>Payload</strong><div class="small">Use this payload on target to trigger the blind logger.</div></div>';
        echo '<div class="results"><div class="bypass-list">'.htmlspecialchars($payload).'</div></div>';
    }
    echo '<div style="margin-top:12px" class="card"><div class="small"><strong>Recent blind hits</strong></div>';
    $rows = $db->query('SELECT id,created_at,remote_addr,user_agent,params FROM blind_hits ORDER BY id DESC LIMIT 30')->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r){
        echo '<div style="border-top:1px solid rgba(255,255,255,0.02);padding:8px;margin-top:8px"><div class="small">#'.intval($r['id']).' '.htmlspecialchars($r['created_at']).' from '.htmlspecialchars($r['remote_addr']).'</div>';
        echo '<div class="small">UA: '.htmlspecialchars($r['user_agent']).'</div>';
        echo '<div class="bypass-list">'.htmlspecialchars($r['params']).'</div></div>';
    }
    echo '</div>';
    footer_html();
    exit;
}

if ($page === 'filter'){
    header_html('Filter Lab');
    $level = $_GET['level'] ?? 'none';
    $input = $_POST['input'] ?? null;
    echo '<form method="POST" action="?page=filter&level='.urlencode($level).'">';
    echo '<div class="form-row"><label class="small">Filter level:</label><select name="level_select" onchange="this.form.submit()"><option value="none" '.($level==='none'?'selected':'').'>none (vulnerable)</option><option value="naive" '.($level==='naive'?'selected':'').'>naive (strip &lt;script&gt; and on* handlers)</option><option value="strip_tags" '.($level==='strip_tags'?'selected':'').'>strip_tags()</option><option value="regex" '.($level==='regex'?'selected':'').'>regex (remove &lt; &gt; and javascript:)</option><option value="encode" '.($level==='encode'?'selected':'').'>encode (htmlspecialchars)</option></select></div>';
    echo '</form>';
    // accept input and show before/after and render result sink
    echo '<form method="POST" action="?page=filter&level='.urlencode($level).'"><div class="form-row"><textarea class="input" name="input" data-field="filter-input" rows="4" placeholder="Try payloads here"></textarea></div><div class="form-row"><button class="button">Test</button></div></form>';
    if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['input'])){
        $lvl = $_GET['level'] ?? 'none';
        // respect selection change via form that set level_select
        if (!empty($_POST['level_select'])) $lvl = $_POST['level_select'];
        $raw = $_POST['input'];
        $filtered = filter_input_level($raw, $lvl);
        echo '<div class="meta"><strong>Server-side filter applied:</strong> '.htmlspecialchars($lvl).'</div>';
        echo '<div class="results"><div><strong>Raw input</strong><div class="bypass-list">'.htmlspecialchars($raw).'</div></div>';
        echo '<div style="margin-top:8px"><strong>Filtered output</strong><div class="bypass-list">'.htmlspecialchars($filtered).'</div></div>';
        echo '<div style="margin-top:8px"><strong>Rendered sink (unsafe)</strong><div style="background:#06121a;padding:8px;margin-top:6px">';
        // intentionally render filtered output without encoding to show bypasses
        echo $filtered;
        echo '</div></div></div>';
    }
    footer_html();
    exit;
}

if ($page === 'contexts'){
    header_html('XSS Contexts Explorer');
    echo '<div class="small">Try inserting payloads into different contexts below to learn how escaping differs by context.</div>';
    // contexts: html body, attribute, js string, url param, style
    echo '<form method="GET" action="?page=contexts">';
    echo '<div class="form-row"><input class="input" name="c" data-field="contexts-input" placeholder="Payload to test (e.g. \'\' onmouseover=alert(1) )"></div>';
    echo '<div class="form-row"><button class="button">Test contexts</button></div>';
    echo '</form>';
    $c = $_GET['c'] ?? '';
    echo '<div class="card" style="margin-top:10px"><div class="small"><strong>HTML body context</strong><div class="results">'. $c .'</div></div>';
    echo '<div class="small" style="margin-top:8px"><strong>HTML attribute context</strong><div class="results"><div><button '.$c.'>Example button</button></div></div></div>';
    echo '<div class="small" style="margin-top:8px"><strong>JavaScript string context</strong><div class="results"><script>var s = "'.str_replace('"','\"',$c).'";console.log(s);</script><div class="small">(opened console to inspect)</div></div></div>';
    echo '<div class="small" style="margin-top:8px"><strong>URL / href context</strong><div class="results"><a href="'.htmlspecialchars($c).'">Click me (href)</a></div></div>';
    echo '<div class="small" style="margin-top:8px"><strong>CSS context</strong><div class="results"><div style="width:100%;height:30px;'.htmlspecialchars($c).'">Box</div></div></div>';
    echo '</div>';
    footer_html();
    exit;
}

if ($page === 'bypasses'){
    header_html('Filter bypasses (examples increasing difficulty)');
    // load bypass file
    $bf = __DIR__ . '/bypasses.txt';
    $content = is_file($bf) ? file_get_contents($bf) : 'No bypass file found.';
    echo '<div class="small">This list is organized from easy -> hard. Use these on the Filter lab by selecting different levels.</div>';
    echo '<div class="results bypass-list" style="margin-top:10px">'.htmlspecialchars($content).'</div>';
    footer_html();
    exit;
}

// default fallback
header_html('Unknown');
echo '<div class="small">Unknown page.</div>';
footer_html();
