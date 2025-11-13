<?php

declare(strict_types=1);

use App\Bootstrap;
use App\Support\BootstrapPaths;
use App\Support\PathResolver;

$projectRoot = realpath(__DIR__ . '/..');
if ($projectRoot === false || !is_dir($projectRoot . '/vendor')) {
    $projectRoot = __DIR__;
}

$bootstrapPathsFile = $projectRoot . '/src/Support/BootstrapPaths.php';
if (!file_exists($bootstrapPathsFile)) {
    http_response_code(500);
    echo 'Fatal error: Bootstrap path resolver not found.';
    exit;
}

require_once $bootstrapPathsFile;

$autoload = BootstrapPaths::resolve('vendor/autoload.php');

if (!file_exists($autoload)) {
    http_response_code(500);
    echo 'Fatal error: vendor/autoload.php not found.';
    exit;
}

require_once $autoload;

require_once BootstrapPaths::resolve('src/Bootstrap.php');

$bootstrap = Bootstrap::getInstance();
$config = $bootstrap->getConfig();
$appName = $config['app_name'] ?? 'EchoDB';
$appVersion = $config['app_version'] ?? 'dev';
$repoUrl = $config['app_repo'] ?? 'https://github.com/dominicminischetti/echodb';
$basePath = PathResolver::resolveBasePath($config['base_path'] ?? null, $_SERVER['SCRIPT_NAME'] ?? null);

if (!function_exists('assetVersion')) {
    /**
     * Generate a cache-busting version string for public assets.
     */
    function assetVersion(string $relativePath, string $fallback): string
    {
        $fullPath = __DIR__ . '/' . ltrim($relativePath, '/');
        if (is_file($fullPath)) {
            $modifiedTime = filemtime($fullPath);
            if ($modifiedTime !== false) {
                return (string) $modifiedTime;
            }
        }

        return $fallback;
    }
}

if (!function_exists('basePathUri')) {
    /**
     * Prefix a relative path with the configured application base path.
     */
    function basePathUri(string $relativePath, string $basePath): string
    {
        $normalizedRelative = '/' . ltrim($relativePath, '/');
        if ($basePath === '') {
            return $normalizedRelative;
        }

        return rtrim($basePath, '/') . $normalizedRelative;
    }
}

$apiBase = rtrim(basePathUri('api', $basePath), '/');
$cssVersion = assetVersion('css/style.css', (string) $appVersion);
$jsVersion = assetVersion('js/main.js', (string) $appVersion);
$apiEventsPath = basePathUri('api/events', $basePath);
$apiStreamPath = basePathUri('api/stream', $basePath);
$apiStatsPath = basePathUri('api/stats', $basePath);
?>
<!DOCTYPE html>
<html lang="en" data-app-version="<?= htmlspecialchars($appVersion, ENT_QUOTES) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($appName) ?> — The Sound of Change</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter+Tight:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= htmlspecialchars(basePathUri('css/style.css', $basePath), ENT_QUOTES) ?>?v=<?= htmlspecialchars($cssVersion, ENT_QUOTES) ?>">
    <link rel="icon" href="<?= htmlspecialchars(basePathUri('assets/logo.svg', $basePath), ENT_QUOTES) ?>" type="image/svg+xml">
</head>
<body class="theme-dark" data-api-base="<?= htmlspecialchars($apiBase, ENT_QUOTES) ?>">
    <div class="grain"></div>
    <header class="site-header" aria-label="Primary">
        <a href="#hero" class="brand" aria-label="<?= htmlspecialchars($appName) ?> home">
            <img src="<?= htmlspecialchars(basePathUri('assets/logo.svg', $basePath), ENT_QUOTES) ?>" alt="<?= htmlspecialchars($appName) ?> logo" class="brand-mark">
            <span class="brand-name"><?= htmlspecialchars($appName) ?></span>
        </a>
        <nav class="site-nav" aria-label="Main navigation">
            <a href="#live-demo">Live Demo</a>
            <a href="#architecture">Architecture</a>
            <a href="#docs">Docs</a>
        </nav>
    </header>

    <main>
        <section id="hero" class="hero" aria-labelledby="hero-title">
            <div class="hero-panel glass">
                <div class="waveform" aria-hidden="true">
                    <svg viewBox="0 0 600 240" role="presentation">
                        <path class="wave wave-1" d="M0 120 Q 75 40 150 120 T 300 120 T 450 120 T 600 120"></path>
                        <path class="wave wave-2" d="M0 130 Q 75 60 150 130 T 300 130 T 450 130 T 600 130"></path>
                        <path class="wave wave-3" d="M0 110 Q 75 180 150 110 T 300 110 T 450 110 T 600 110"></path>
                    </svg>
                </div>
                <div class="hero-content">
                    <p class="eyebrow">Minimal Glassmorphic Studio</p>
                    <h1 id="hero-title"><?= htmlspecialchars($appName) ?></h1>
                    <p class="hero-lede">Every change has a sound. Compose live database mutations and watch EchoDB translate them into motion and tone.</p>
                    <div class="hero-actions">
                        <a class="button primary" href="#live-demo">Try Demo</a>
                        <a class="button ghost" href="<?= htmlspecialchars($repoUrl, ENT_QUOTES) ?>" target="_blank" rel="noreferrer">View Code</a>
                    </div>
                    <dl class="hero-meta">
                        <div>
                            <dt>Status</dt>
                            <dd>Live stream via Server-Sent Events</dd>
                        </div>
                        <div>
                            <dt>Version</dt>
                            <dd><?= htmlspecialchars($appVersion) ?></dd>
                        </div>
                        <div>
                            <dt>Stack</dt>
                            <dd>PHP · PostgreSQL · Vanilla JS</dd>
                        </div>
                    </dl>
                </div>
            </div>
        </section>

        <section id="live-demo" class="section section-demo" aria-labelledby="demo-title">
            <div class="section-heading">
                <h2 id="demo-title">Live Demo</h2>
                <p>Shape a payload, trigger the database, and witness the visual instrument react in real time.</p>
            </div>
            <div class="demo-grid">
                <article class="demo-card glass" aria-labelledby="editor-title">
                    <header class="card-header">
                        <div>
                            <h3 id="editor-title">Mutation Composer</h3>
                            <p class="card-subtitle">Craft JSON events or tweak the generated samples.</p>
                        </div>
                        <span class="chip">JSON</span>
                    </header>
                    <label for="payloadInput" class="sr-only">JSON payload</label>
                    <textarea id="payloadInput" spellcheck="false" aria-describedby="editor-title"></textarea>
                    <div class="editor-actions">
                        <button type="button" class="button primary" id="sendPayload">Send Mutation</button>
                        <button type="button" class="button subtle" id="formatPayload">Beautify</button>
                        <button type="button" class="button subtle" id="resetPayload">Reset</button>
                    </div>
                </article>

                <article class="demo-card glass" aria-labelledby="visualizer-title">
                    <header class="card-header">
                        <div>
                            <h3 id="visualizer-title">Visual Instrument</h3>
                            <p class="card-subtitle">Echo nodes glow as payloads flow from database to UI.</p>
                        </div>
                        <span class="chip live" aria-live="polite">Streaming</span>
                    </header>
                    <div class="visual-stage">
                        <svg id="visualizerCanvas" viewBox="0 0 620 240" role="img" aria-label="Data flow visualizer">
                            <defs>
                                <linearGradient id="flowGradient" x1="0%" y1="0%" x2="100%" y2="0%">
                                    <stop offset="0%" stop-color="var(--accent-cyan)"></stop>
                                    <stop offset="100%" stop-color="var(--accent-mint)"></stop>
                                </linearGradient>
                                <filter id="glow">
                                    <feGaussianBlur stdDeviation="12" result="coloredBlur"></feGaussianBlur>
                                    <feMerge>
                                        <feMergeNode in="coloredBlur"></feMergeNode>
                                        <feMergeNode in="SourceGraphic"></feMergeNode>
                                    </feMerge>
                                </filter>
                            </defs>
                            <g class="flow">
                                <rect x="40" y="70" width="140" height="70" rx="20" class="node node-db"></rect>
                                <text x="110" y="110" class="node-label">Database</text>
                                <rect x="240" y="40" width="140" height="70" rx="20" class="node node-echo"></rect>
                                <text x="310" y="80" class="node-label">Echo Engine</text>
                                <rect x="440" y="70" width="140" height="70" rx="20" class="node node-ui"></rect>
                                <text x="510" y="110" class="node-label">Clients</text>
                                <path d="M180 100 C 220 40, 260 40, 310 75" class="arc arc-top"></path>
                                <path d="M310 75 C 360 120, 400 120, 450 100" class="arc arc-bottom"></path>
                                <path d="M180 110 C 220 160, 260 160, 310 125" class="arc arc-bottom"></path>
                                <path d="M310 125 C 360 60, 400 60, 450 100" class="arc arc-top"></path>
                            </g>
                        </svg>
                    </div>
                    <div class="live-stats" role="status" aria-live="polite">
                        <div class="stat">
                            <span class="label">Insert</span>
                            <span class="value" id="stat-insert">0</span>
                        </div>
                        <div class="stat">
                            <span class="label">Update</span>
                            <span class="value" id="stat-update">0</span>
                        </div>
                        <div class="stat">
                            <span class="label">Delete</span>
                            <span class="value" id="stat-delete">0</span>
                        </div>
                        <div class="stat">
                            <span class="label">Events/min</span>
                            <span class="value" id="stat-rpm">0</span>
                        </div>
                        <svg id="sparkline" viewBox="0 0 220 60" preserveAspectRatio="none"></svg>
                    </div>
                    <div class="timeline-panel" aria-live="polite">
                        <div class="panel-heading">
                            <h4>Event Timeline</h4>
                            <p>Newest first · tap to inspect</p>
                        </div>
                        <ul id="timeline" class="timeline"></ul>
                    </div>
                </article>
            </div>
            <div class="demo-controls" role="group" aria-label="Stream controls">
                <button type="button" class="button neon" id="randomPayload" data-icon="spark">Random Event</button>
                <button type="button" class="button ghost" id="pauseStream" data-icon="pause">Pause Stream</button>
                <button type="button" class="button ghost" data-sound-toggle data-icon="sound">Sound On</button>
            </div>
        </section>

        <section id="architecture" class="section architecture" aria-labelledby="architecture-title">
            <div class="section-heading">
                <h2 id="architecture-title">Architecture</h2>
                <p>Glassy nodes reveal how events ripple across EchoDB.</p>
            </div>
            <div class="architecture-diagram" role="list">
                <div class="arch-node" role="listitem" data-tooltip="PostgreSQL triggers emit change payloads">Database</div>
                <div class="arch-link" aria-hidden="true"></div>
                <div class="arch-node" role="listitem" data-tooltip="PHP backend streams mutations over SSE">PHP Streamer</div>
                <div class="arch-link" aria-hidden="true"></div>
                <div class="arch-node" role="listitem" data-tooltip="Vanilla JS client renders visuals &amp; sound">Client Studio</div>
            </div>
            <div class="architecture-cards">
                <article class="glass arch-card" data-animate>
                    <h3>Mutation Capture</h3>
                    <p>PostgreSQL triggers serialize row level changes. Payloads include before/after diffs and actor metadata.</p>
                </article>
                <article class="glass arch-card" data-animate>
                    <h3>Event Broadcast</h3>
                    <p>A lightweight PHP emitter opens a Server-Sent Events channel, guaranteeing order and low-latency delivery.</p>
                </article>
                <article class="glass arch-card" data-animate>
                    <h3>Multisensory Client</h3>
                    <p>The browser listens, animates the flow diagram, and orchestrates tones via the Web Audio API.</p>
                </article>
            </div>
        </section>

        <section id="docs" class="section docs" aria-labelledby="docs-title">
            <div class="section-heading">
                <h2 id="docs-title">Docs &amp; API</h2>
                <p>Everything you need to instrument your own dataset with EchoDB.</p>
                <button type="button" class="button ghost" data-theme-toggle data-icon="theme" aria-label="Toggle theme">Switch Theme</button>
            </div>
            <div class="tabs" role="tablist" aria-label="Documentation tabs">
                <button type="button" class="tab-button active" role="tab" aria-selected="true" data-tab-target="overview">Overview</button>
                <button type="button" class="tab-button" role="tab" aria-selected="false" data-tab-target="api">API</button>
                <button type="button" class="tab-button" role="tab" aria-selected="false" data-tab-target="examples">Examples</button>
            </div>
            <div class="tab-panels">
                <section id="tab-overview" class="tab-panel active" role="tabpanel" aria-labelledby="overview">
                    <h3>How it works</h3>
                    <p>EchoDB listens to PostgreSQL notifications, transforms them into structured JSON events, and streams them to browsers over Server-Sent Events. The client layers animation, stats, and audio to render each change as a sonic signature.</p>
                    <ul class="bullet-grid">
                        <li>Lightweight PHP SSE endpoint</li>
                        <li>Web Audio instrument with tone palettes per mutation</li>
                        <li>Framework-free frontend for GitHub Pages deployments</li>
                    </ul>
                </section>
                <section id="tab-api" class="tab-panel" role="tabpanel" aria-labelledby="api">
                    <h3>REST Endpoints</h3>
                    <div class="code-block" data-animate>
                        <button type="button" class="copy-button" data-copy-target="code-api" aria-label="Copy API snippet">Copy</button>
                        <pre id="code-api"><code>GET <?= htmlspecialchars($apiEventsPath, ENT_QUOTES) ?>?limit=20
GET <?= htmlspecialchars($apiStreamPath, ENT_QUOTES) ?> (SSE)
GET <?= htmlspecialchars($apiStatsPath, ENT_QUOTES) ?>
POST <?= htmlspecialchars($apiEventsPath, ENT_QUOTES) ?>
Content-Type: application/json
{
  "table": "orders",
  "row_id": 42,
  "type": "update",
  "actor": "cli-bot",
  "changes": {
    "status": "processing",
    "amount": 72.45
  }
}</code></pre>
                    </div>
                </section>
                <section id="tab-examples" class="tab-panel" role="tabpanel" aria-labelledby="examples">
                    <h3>Client Hook</h3>
                    <div class="code-block" data-animate>
                        <button type="button" class="copy-button" data-copy-target="code-examples" aria-label="Copy client snippet">Copy</button>
                        <pre id="code-examples"><code>const stream = new EventSource('<?= htmlspecialchars($apiStreamPath, ENT_QUOTES) ?>');
stream.addEventListener('update', (event) => {
  const payload = JSON.parse(event.data);
  animate(payload.type);
  playTone(payload.type);
});</code></pre>
                    </div>
                </section>
            </div>
        </section>
    </main>

    <div id="toast" class="toast hidden" role="status" aria-live="polite"></div>

    <footer class="site-footer" aria-label="Footer">
        <div class="footer-copy">
            <span>&copy; 2025 Dominic Minischetti</span>
            <p>Dedicated to optimizing performance and building scalable backend systems. I've been crafting fast, reliable solutions since 2012.</p>
        </div>
        <div class="footer-links" role="navigation" aria-label="External links">
            <a href="<?= htmlspecialchars($repoUrl, ENT_QUOTES) ?>" target="_blank" rel="noreferrer" aria-label="GitHub">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 .5a12 12 0 0 0-3.79 23.4c.6.11.82-.26.82-.58v-2c-3.34.73-4.04-1.61-4.04-1.61a3.18 3.18 0 0 0-1.34-1.77c-1.1-.75.08-.74.08-.74a2.52 2.52 0 0 1 1.84 1.24 2.56 2.56 0 0 0 3.5 1 2.55 2.55 0 0 1 .76-1.6c-2.67-.3-5.47-1.34-5.47-5.95a4.66 4.66 0 0 1 1.24-3.24 4.32 4.32 0 0 1 .12-3.2s1-.32 3.3 1.23a11.4 11.4 0 0 1 6 0c2.3-1.55 3.3-1.23 3.3-1.23a4.32 4.32 0 0 1 .12 3.2 4.66 4.66 0 0 1 1.24 3.24c0 4.62-2.81 5.64-5.49 5.94a2.86 2.86 0 0 1 .82 2.22v3.3c0 .32.22.7.83.58A12 12 0 0 0 12 .5Z"/></svg>
            </a>
            <a href="https://minischetti.org" target="_blank" rel="noreferrer" aria-label="Portfolio">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 3a2 2 0 0 0-2 2v14c0 1.1.9 2 2 2h14a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2H5Zm7 2 7 7-7 7-7-7 7-7Zm0 3.83L8.83 12 12 15.17 15.17 12 12 8.83Z"/></svg>
            </a>
        </div>
        <div class="footer-controls">
            <button type="button" class="button ghost" data-theme-toggle data-icon="theme" aria-label="Toggle theme">Switch Theme</button>
            <button type="button" class="button ghost" data-sound-toggle data-icon="sound" aria-label="Toggle sound">Sound On</button>
        </div>
    </footer>

    <script type="module" src="<?= htmlspecialchars(basePathUri('js/main.js', $basePath), ENT_QUOTES) ?>?v=<?= htmlspecialchars($jsVersion, ENT_QUOTES) ?>"></script>
</body>
</html>
