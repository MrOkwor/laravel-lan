<?php

declare(strict_types=1);

namespace Mrokwor\LaravelLan\HTTPS;

use RuntimeException;
use Throwable;

final class TlsProxy
{
    /** @var resource|null */
    private $serverSocket = null;

    /** @var array<int, array{client: resource, backend: resource|null, handshake: bool, created_at: float}> */
    private array $connections = [];

    public function __construct(
        private string $bindHost,
        private int $bindPort,
        private string $backendHost,
        private int $backendPort,
        private string $certPath,
        private string $keyPath,
    ) {
    }

    /**
     * Start the TCP listener with SSL context for non-blocking TLS termination.
     */
    public function start(): void
    {
        $context = stream_context_create([
            'ssl' => [
                'local_cert' => $this->certPath,
                'local_pk' => $this->keyPath,
                'allow_self_signed' => true,
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ]);

        $this->serverSocket = @stream_socket_server(
            "tcp://{$this->bindHost}:{$this->bindPort}",
            $errno,
            $errstr,
            STREAM_SERVER_BIND | STREAM_SERVER_LISTEN,
            $context
        );

        if (!$this->serverSocket) {
            throw new RuntimeException("Failed to bind TLS listener on {$this->bindHost}:{$this->bindPort}: {$errstr} ({$errno})");
        }

        stream_set_blocking($this->serverSocket, false);
    }

    /**
     * Process I/O for pending client handshakes, connections, and data transfers.
     */
    public function tick(): void
    {
        if ($this->serverSocket === null) {
            return;
        }

        // 1. Accept new incoming client TCP connection
        $client = @stream_socket_accept($this->serverSocket, 0);
        if ($client !== false && is_resource($client)) {
            stream_set_blocking($client, false);
            stream_context_set_option($client, 'ssl', 'local_cert', $this->certPath);
            stream_context_set_option($client, 'ssl', 'local_pk', $this->keyPath);
            stream_context_set_option($client, 'ssl', 'allow_self_signed', true);
            stream_context_set_option($client, 'ssl', 'verify_peer', false);
            stream_context_set_option($client, 'ssl', 'verify_peer_name', false);

            $id = (int) $client;
            $this->connections[$id] = [
                'client' => $client,
                'backend' => null,
                'handshake' => false,
                'created_at' => microtime(true),
            ];
        }

        // 2. Process all active connections
        $now = microtime(true);
        foreach ($this->connections as $id => &$session) {
            $c = $session['client'];

            // Clean up stale or dead connections
            if (!is_resource($c) || feof($c) || ($now - $session['created_at'] > 30.0)) {
                if (is_resource($c)) {
                    @fclose($c);
                }
                if (is_resource($session['backend'])) {
                    @fclose($session['backend']);
                }
                unset($this->connections[$id]);
                continue;
            }

            // Perform TLS Handshake
            if (!$session['handshake']) {
                $crypto = @stream_socket_enable_crypto(
                    $c,
                    true,
                    STREAM_CRYPTO_METHOD_TLS_SERVER
                );

                if ($crypto === true) {
                    $session['handshake'] = true;
                    $backend = @stream_socket_client("tcp://{$this->backendHost}:{$this->backendPort}", $errno, $errstr, 1);
                    if ($backend !== false && is_resource($backend)) {
                        stream_set_blocking($backend, false);
                        $session['backend'] = $backend;
                    } else {
                        @fclose($c);
                        unset($this->connections[$id]);
                        continue;
                    }
                } elseif ($crypto === false) {
                    // Handshake failed
                    @fclose($c);
                    unset($this->connections[$id]);
                    continue;
                }
            }

            // Relay data between TLS client and plain HTTP backend
            if ($session['handshake'] && is_resource($session['backend'])) {
                $b = $session['backend'];

                $fromClient = @fread($c, 65536);
                if ($fromClient !== false && $fromClient !== '') {
                    @fwrite($b, $fromClient);
                }

                $fromBackend = @fread($b, 65536);
                if ($fromBackend !== false && $fromBackend !== '') {
                    @fwrite($c, $fromBackend);
                }

                if (feof($b)) {
                    @fclose($c);
                    @fclose($b);
                    unset($this->connections[$id]);
                }
            }
        }
    }

    /**
     * Terminate the TLS proxy and close all open sockets.
     */
    public function stop(): void
    {
        foreach ($this->connections as $session) {
            if (is_resource($session['client'])) {
                @fclose($session['client']);
            }
            if (is_resource($session['backend'])) {
                @fclose($session['backend']);
            }
        }

        $this->connections = [];

        if ($this->serverSocket !== null && is_resource($this->serverSocket)) {
            @fclose($this->serverSocket);
            $this->serverSocket = null;
        }
    }
}
