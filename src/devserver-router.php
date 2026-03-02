<?php

/*
    Router script for PHP built-in development server.
    Used by DevServer to serve _public/ with live-reload injection.
*/

$uri = $_SERVER[ 'REQUEST_URI' ];
$path = parse_url($uri, PHP_URL_PATH);

// Live-reload endpoint — returns current build ID from state file
if ($path === '/__livereload') {
    header('Content-Type: application/json');
    header('Cache-Control: no-cache, no-store, must-revalidate');

    $stateFile = $_SERVER[ 'DOCUMENT_ROOT' ] . '/.devserver-state.json';
    if (file_exists($stateFile)) {
        echo file_get_contents($stateFile);
    } else {
        echo json_encode([ 'buildId' => 0 ]);
    }
    return;
}

// Resolve file path in document root
$filePath = $_SERVER[ 'DOCUMENT_ROOT' ] . $path;

// Directory requests — serve index.html
if (is_dir($filePath)) {
    $filePath = rtrim($filePath, '/') . '/index.html';
}

// If the file doesn't exist, let PHP's built-in server return 404
if (!file_exists($filePath)) {
    return false;
}

// For HTML files, inject the live-reload script before </body>
if (pathinfo($filePath, PATHINFO_EXTENSION) === 'html') {
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-cache, no-store, must-revalidate');

    $html = file_get_contents($filePath);

    $script = <<<'LIVERELOAD'
<script>
(function() {
    var buildId = null;
    var errorCount = 0;
    var maxErrors = 5;

    function poll() {
        fetch('/__livereload')
            .then(function(r) { return r.json(); })
            .then(function(data) {
                errorCount = 0;
                if (buildId === null) {
                    buildId = data.buildId;
                } else if (data.buildId !== buildId) {
                    location.reload();
                    return;
                }
                setTimeout(poll, 1000);
            })
            .catch(function() {
                errorCount++;
                if (errorCount < maxErrors) {
                    setTimeout(poll, 2000);
                }
            });
    }

    poll();
})();
</script>
LIVERELOAD;

    $html = str_replace('</body>', $script . "\n</body>", $html);

    echo $html;
    return;
}

// All other files — let PHP's built-in server handle them natively
return false;
