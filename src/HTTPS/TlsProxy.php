<?php

declare(strict_types=1);

namespace Mrokwor\LaravelLan\HTTPS;

use RuntimeException;
use Throwable;

final class TlsProxy
{
    /** @var resource|null */
    private $serverSocket = null;

    /** @var array<int, array{client: resource, backend: resource}> */
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
     * Start the non-blocking TLS listener socket.
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
            "tls://{$this->bindHost}:{$this->bindPort}",
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
     * Process I/O for pending client connections and data transfers.
     */
    public function tick(): void
    {
        if ($this->serverSocket === null) {
            return;
        }

        // 1. Accept new incoming TLS client connection
        $client = @stream_socket_accept($this->serverSocket, 0);
        if ($client !== false && is_resource($client)) {
            stream_set_blocking($client, false);

            // Connect to internal plain HTTP Laravel backend
            $backend = @stream_socket_client("tcp://{$this->backendHost}:{$this->backendPort}", $errno, $errstr, 1);
            if ($backend !== false && is_resource($backend)) {
                stream_set_blocking($backend, false);
                $clientId = (int) $client;
                $this->connections[$clientId] = [
                    'client' => $client,
                    'backend' => $backend,
                ];
            } else {
                @fclose($client);
            }
        }

        // 2. Relay data between TLS clients and internal HTTP backend
        foreach ($this->connections as $key => $pair) {
            $client = $pair['client'];
            $backend = $pair['backend'];

            if (!is_resource($client) || !is_resource($backend) || feof($client) || feof($backend)) {
                if (is_resource($client)) {
                    @fclose($client);
                }
                if (is_resource($backend)) {
                    @fclose($backend);
                }
                unset($this->connections[$key]);
                continue;
            }

            // Read from client -> write to backend
            $fromClient = @fread($client, 65536);
            if ($fromClient !== false && $fromClient !== '') {
                @fwrite($backend, $fromClient);
            }

            // Read from backend -> write to client
            $fromBackend = @fread($backend, 65536);
            if ($fromBackend !== false && $fromBackend !== '') {
                @fwrite($client, $fromBackend);
            }
        }
    }

    /**
     * Terminate the TLS proxy and close all open sockets.
     */
    public function stop(): void
    {
        foreach ($this->connections as $pair) {
            if (is_resource($pair['client'])) {
                @fclose($pair['client']);
            }
            if (is_resource($pair['backend'])) {
                @fclose($pair['backend']);
            }
        }

        $this->connections = [];

        if ($this->serverSocket !== null && is_resource($this->serverSocket)) {
            @fclose($this->serverSocket);
            $this->serverSocket = null;
        }
    }
}
