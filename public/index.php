<?php

/**
 * ApexApprove - Agency Client Approval Portal
 * Open-Source Edition (AGPLv3)
 * Maintainer: Curve Metrics (contact@curvemetrics.in)
 */

declare(strict_types=1);

define('LARAVEL_START', microtime(true));

// Fast-path Health Check for Docker / Hostinger Load Balancer
$requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

if ($requestUri === '/up' || $requestUri === '/api/health') {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(200);
    echo json_encode([
        'status' => 'ok',
        'service' => 'ApexApprove',
        'version' => '1.0.0-agpl',
        'timestamp' => gmdate('c'),
    ]);
    exit;
}

// AGPLv3 Source Code Compliance Hook
if ($requestUri === '/source' || $requestUri === '/about/source') {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(200);
    echo json_encode([
        'license' => 'GNU Affero General Public License v3.0 (AGPLv3)',
        'repository' => 'https://github.com/mnkkal/Approval-Management',
        'maintainer' => 'Curve Metrics',
        'contact' => 'contact@curvemetrics.in',
        'description' => 'In compliance with AGPLv3 Section 13, complete corresponding source code is freely available at the repository link.',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

// Determine if application is in maintenance mode
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register Composer Autoloader if available
if (file_exists($autoloader = __DIR__.'/../vendor/autoload.php')) {
    require_once $autoloader;
}

// Fallback HTML UI if frontend not yet precompiled
if (!file_exists(__DIR__.'/build/manifest.json') && !file_exists(__DIR__.'/hot')) {
    // Deliver lightweight standalone Client Review Portal UI
    header('Content-Type: text/html; charset=utf-8');
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>ApexApprove - Agency Client Approval Portal</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
        <style>body { font-family: 'Inter', sans-serif; }</style>
    </head>
    <body class="bg-slate-950 text-slate-100 min-h-screen flex flex-col">
        <!-- Top Nav -->
        <header class="border-b border-slate-800 bg-slate-900/60 backdrop-blur px-6 py-4 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <div class="w-8 h-8 rounded-lg bg-blue-600 flex items-center justify-center font-bold text-white shadow-lg shadow-blue-500/20">A</div>
                <div>
                    <h1 class="font-bold text-lg leading-tight tracking-tight text-white">ApexApprove</h1>
                    <p class="text-xs text-slate-400">Curve Metrics Client Review System</p>
                </div>
            </div>
            <div class="flex items-center space-x-3">
                <a href="https://github.com/mnkkal/Approval-Management" target="_blank" rel="noopener" class="text-xs px-3 py-1.5 rounded-md border border-slate-700 hover:bg-slate-800 text-slate-300 transition-colors flex items-center space-x-1.5">
                    <span>GitHub AGPLv3</span>
                </a>
                <span class="text-xs px-2.5 py-1 rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 font-medium">Ready</span>
            </div>
        </header>

        <!-- Main Workspace Hero -->
        <main class="flex-1 max-w-5xl mx-auto w-full px-6 py-12 flex flex-col justify-center">
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-8 md:p-12 shadow-2xl relative overflow-hidden">
                <div class="absolute -top-24 -right-24 w-96 h-96 bg-blue-600/10 rounded-full blur-3xl pointer-events-none"></div>

                <div class="inline-flex items-center space-x-2 px-3 py-1 rounded-full bg-blue-500/10 border border-blue-500/20 text-blue-400 text-xs font-semibold uppercase tracking-wider mb-6">
                    <span>Hostinger Open Source Partner Ready</span>
                </div>

                <h2 class="text-3xl md:text-4xl font-extrabold text-white tracking-tight mb-4">
                    Agency Client Approvals,<br><span class="text-blue-500">Without Feedback Chaos.</span>
                </h2>

                <p class="text-slate-300 text-base md:text-lg leading-relaxed max-w-2xl mb-8">
                    Stop losing decisions in WhatsApp chats and email chains. Pinpoint annotations on images, PDFs, and video with cryptographically signed client approvals.
                </p>

                <!-- Core Modules Grid -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8 text-left">
                    <div class="p-4 rounded-xl bg-slate-800/50 border border-slate-700/60">
                        <div class="text-blue-400 font-semibold text-sm mb-1">Zero-Cost Email OTP</div>
                        <p class="text-slate-400 text-xs leading-relaxed">Secured with Hostinger Business SMTP (<span class="text-slate-300">contact@curvemetrics.in</span>). No paid verification APIs required.</p>
                    </div>
                    <div class="p-4 rounded-xl bg-slate-800/50 border border-slate-700/60">
                        <div class="text-emerald-400 font-semibold text-sm mb-1">Direct HTML5 Video Review</div>
                        <p class="text-slate-400 text-xs leading-relaxed">Direct MP4/WebM video scrubbing with timestamp annotations + optional local FFmpeg worker.</p>
                    </div>
                    <div class="p-4 rounded-xl bg-slate-800/50 border border-slate-700/60">
                        <div class="text-indigo-400 font-semibold text-sm mb-1">Deterministic SHA-256 Sign-Off</div>
                        <p class="text-slate-400 text-xs leading-relaxed">Approvals bind directly to the deliverable version hash. Version N+1 never auto-approves.</p>
                    </div>
                </div>

                <!-- API & Review Demo Links -->
                <div class="flex flex-wrap items-center gap-3">
                    <a href="/up" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-500 text-white rounded-lg text-sm font-semibold transition-colors shadow-lg shadow-blue-600/20">
                        Test Health Probe (/up)
                    </a>
                    <a href="/source" class="px-5 py-2.5 bg-slate-800 hover:bg-slate-700 border border-slate-700 text-slate-200 rounded-lg text-sm font-medium transition-colors">
                        AGPLv3 Source Info
                    </a>
                    <a href="https://hostinger.com/vps" target="_blank" rel="noopener" class="px-5 py-2.5 bg-purple-600 hover:bg-purple-500 text-white rounded-lg text-sm font-semibold transition-colors">
                        Deploy to Hostinger VPS
                    </a>
                </div>
            </div>
        </main>

        <!-- Footer -->
        <footer class="border-t border-slate-800/80 px-6 py-6 text-center text-xs text-slate-500">
            <p>ApexApprove &copy; 2026 Curve Metrics. Released under the GNU Affero General Public License v3.0.</p>
        </footer>
    </body>
    </html>
    <?php
    exit;
}
