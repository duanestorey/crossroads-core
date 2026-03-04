<?php

namespace CR;

class DevServer
{
    protected Config $config;
    protected PluginManager $pluginManager;
    protected DB $db;
    protected int $port = 0;
    protected string $address = '127.0.0.1';
    /** @var resource|null */
    protected mixed $process = null;
    protected bool $shutdown = false;
    protected int $buildId = 0;
    protected string $stateFile = '';

    public function __construct(Config $config, PluginManager $pluginManager, DB $db)
    {
        $this->config = $config;
        $this->pluginManager = $pluginManager;
        $this->db = $db;
        $this->stateFile = CROSSROADS_PUBLIC_DIR . '/.devserver-state.json';
    }

    public function start(): void
    {
        // Always include drafts in serve mode
        $this->config->set('options.include_drafts', true);

        // Initial build
        LOG(_i18n('core.build.starting'), 0, Log::INFO);
        $this->_runBuild();

        // Find available port
        $this->port = $this->_findAvailablePort();

        // Write initial state
        $this->_writeState();

        // Start PHP built-in server
        $this->_startServer();

        if (!$this->process || !is_resource($this->process)) {
            LOG(_i18n('core.devserver.failed_start'), 0, Log::ERROR);
            return;
        }

        LOG(sprintf(_i18n('core.devserver.started'), 'http://' . $this->address, $this->port), 0, Log::INFO);

        // Auto-open browser
        $openCommand = $this->config->get('options.browser.open_command');
        if ($openCommand && $this->config->get('options.browser.auto')) {
            $cmd = explode(' ', $openCommand)[0];
            $allowedCommands = ['open', 'xdg-open', 'start'];
            if (in_array(basename($cmd), $allowedCommands)) {
                exec(str_replace('%s', escapeshellarg('http://' . $this->address . ':' . $this->port), $openCommand));
            } else {
                LOG('Untrusted browser command: ' . $cmd, 0, Log::ERROR);
            }
        }

        LOG(_i18n('core.devserver.watching'), 1, Log::INFO);
        LOG(_i18n('core.class.server.to_close'), 1, Log::INFO);

        // Set up file watcher
        $watchDirs = [
            CROSSROADS_CONTENT_DIR,
            CROSSROADS_CONFIG_DIR,
        ];

        // Add theme directories
        $themeName = $this->config->get('site.theme');
        if ($themeName) {
            $watchDirs[] = CROSSROADS_CORE_DIR . '/themes/' . $themeName;
            if (defined('CROSSROADS_LOCAL_THEME_DIR') && is_dir(CROSSROADS_LOCAL_THEME_DIR)) {
                $watchDirs[] = CROSSROADS_LOCAL_THEME_DIR;
            }
        }

        $watcher = new FileWatcher($watchDirs);

        // Signal handling
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGINT, [ $this, '_handleSignal' ]);
            pcntl_signal(SIGTERM, [ $this, '_handleSignal' ]);
        }

        // Main loop
        while (!$this->shutdown) {
            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }

            // Check if server process is still running
            $status = proc_get_status($this->process);
            if (!$status[ 'running' ]) {
                LOG(_i18n('core.devserver.server_died'), 0, Log::ERROR);
                break;
            }

            // Check for file changes
            $changes = $watcher->check();
            if (count($changes)) {
                foreach ($changes as $change) {
                    LOG(sprintf(_i18n('core.devserver.change_detected'), $change[ 'type' ], basename($change[ 'path' ])), 2, Log::INFO);
                }

                LOG(_i18n('core.devserver.rebuilding'), 1, Log::INFO);
                $this->_runBuild();
                $this->buildId++;
                $this->_writeState();
                LOG(_i18n('core.devserver.rebuild_complete'), 1, Log::INFO);
            }

            usleep(500000); // 500ms
        }

        $this->_cleanup();
    }

    protected function _runBuild(): void
    {
        $builder = new Builder($this->config, $this->pluginManager, $this->db);
        try {
            $builder->run();
        } catch (Exception $e) {
            LOG(sprintf(_i18n('core.app.exception'), $e->name(), $e->msg()), 0, Log::ERROR);
        } catch (\Throwable $e) {
            LOG('Unexpected error: ' . $e->getMessage(), 0, Log::ERROR);
        }
    }

    protected function _findAvailablePort(): int
    {
        $sock = socket_create(AF_INET, SOCK_STREAM, 0);
        socket_bind($sock, $this->address, 0);
        socket_getsockname($sock, $addr, $port);
        socket_close($sock);
        return $port;
    }

    protected function _startServer(): void
    {
        $routerPath = CROSSROADS_SRC_DIR . '/devserver-router.php';
        $docRoot = CROSSROADS_PUBLIC_DIR;
        $host = $this->address . ':' . $this->port;

        $cmd = sprintf(
            'exec %s -S %s -t %s %s',
            PHP_BINARY,
            escapeshellarg($host),
            escapeshellarg($docRoot),
            escapeshellarg($routerPath)
        );

        $descriptors = [
            0 => [ 'file', '/dev/null', 'r' ],
            1 => [ 'file', '/dev/null', 'w' ],
            2 => [ 'file', '/dev/null', 'w' ],
        ];

        $this->process = proc_open($cmd, $descriptors, $pipes);
    }

    protected function _writeState(): void
    {
        $dir = dirname($this->stateFile);
        if (!file_exists($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($this->stateFile, json_encode([ 'buildId' => $this->buildId ]));
    }

    protected function _cleanup(): void
    {
        if ($this->process && is_resource($this->process)) {
            $status = proc_get_status($this->process);
            if ($status[ 'running' ] && $status[ 'pid' ]) {
                posix_kill($status[ 'pid' ], SIGTERM);
            }
            proc_close($this->process);
        }

        if (file_exists($this->stateFile)) {
            unlink($this->stateFile);
        }

        LOG(_i18n('core.class.server.stopping'), 0, Log::INFO);
    }

    public function _handleSignal(int $signal): void
    {
        $this->shutdown = true;
    }
}
