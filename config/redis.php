<?php
/**
 * Redis Connection Configuration
 * Uses Predis library for Redis connectivity
 */

require_once __DIR__ . '/../vendor/autoload.php';

class RedisClient {
    private static $instance  = null;
    private static $available = null;
    private $client;

    // Flag file: written when Redis is down, deleted when Redis comes back up.
    // Prevents retrying the 0.1s TCP timeout on EVERY request.
    private static function flagFile(): string {
        return sys_get_temp_dir() . '/foodrush_redis_down.flag';
    }

    // How long (seconds) to trust the "Redis is down" flag before retrying.
    private const RETRY_INTERVAL = 30;

    private function __construct() {
        // Check filesystem flag first — skip connection attempt if flag is recent
        $flag = self::flagFile();
        if (file_exists($flag) && (time() - filemtime($flag)) < self::RETRY_INTERVAL) {
            self::$available = false;
            $this->client    = null;
            return;
        }

        $host = defined('REDIS_HOST') ? REDIS_HOST : '127.0.0.1';
        $port = defined('REDIS_PORT') ? REDIS_PORT : 6379;

        try {
            $this->client = new Predis\Client([
                'scheme'             => 'tcp',
                'host'               => $host,
                'port'               => $port,
                'timeout'            => 0.1,  // very short — only used once per 30s
                'read_write_timeout' => 0.5,
            ]);
            $this->client->ping();
            self::$available = true;
            // Redis is back up — remove the flag
            if (file_exists($flag)) @unlink($flag);
        } catch (Exception $e) {
            self::$available = false;
            $this->client    = null;
            // Write / touch the flag file so the next 30s of requests skip the check
            @file_put_contents($flag, date('c'));
        }
    }

    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /** Returns false when Redis is down (failover support) */
    public static function isAvailable(): bool {
        if (self::$available === null) {
            self::getInstance();
        }
        return (bool) self::$available;
    }

    /** Get the raw Predis client (may be null if unavailable) */
    public function getClient(): ?Predis\Client {
        return $this->client;
    }

    // ─── Convenience wrappers ─────────────────────────────────────────────────

    public function get(string $key): ?string {
        if (!$this->client) return null;
        try { return $this->client->get($key); } catch (Exception $e) { return null; }
    }

    public function set(string $key, string $value, int $ttl = 0): bool {
        if (!$this->client) return false;
        try {
            $ttl > 0 ? $this->client->setex($key, $ttl, $value) : $this->client->set($key, $value);
            return true;
        } catch (Exception $e) { return false; }
    }

    public function del(string $key): bool {
        if (!$this->client) return false;
        try { $this->client->del([$key]); return true; } catch (Exception $e) { return false; }
    }

    public function hset(string $key, string $field, string $value): bool {
        if (!$this->client) return false;
        try { $this->client->hset($key, $field, $value); return true; } catch (Exception $e) { return false; }
    }

    public function hget(string $key, string $field): ?string {
        if (!$this->client) return null;
        try { return $this->client->hget($key, $field); } catch (Exception $e) { return null; }
    }

    public function hgetall(string $key): array {
        if (!$this->client) return [];
        try { return $this->client->hgetall($key) ?: []; } catch (Exception $e) { return []; }
    }

    public function hmset(string $key, array $data): bool {
        if (!$this->client) return false;
        try { $this->client->hmset($key, $data); return true; } catch (Exception $e) { return false; }
    }

    public function rpush(string $key, string $value): bool {
        if (!$this->client) return false;
        try { $this->client->rpush($key, [$value]); return true; } catch (Exception $e) { return false; }
    }

    public function lpop(string $key): ?string {
        if (!$this->client) return null;
        try { return $this->client->lpop($key); } catch (Exception $e) { return null; }
    }

    public function llen(string $key): int {
        if (!$this->client) return 0;
        try { return (int)$this->client->llen($key); } catch (Exception $e) { return 0; }
    }

    public function lrange(string $key, int $start, int $stop): array {
        if (!$this->client) return [];
        try { return $this->client->lrange($key, $start, $stop) ?: []; } catch (Exception $e) { return []; }
    }

    public function incr(string $key): int {
        if (!$this->client) return 0;
        try { return (int)$this->client->incr($key); } catch (Exception $e) { return 0; }
    }

    public function expire(string $key, int $ttl): bool {
        if (!$this->client) return false;
        try { $this->client->expire($key, $ttl); return true; } catch (Exception $e) { return false; }
    }

    public function ttl(string $key): int {
        if (!$this->client) return -1;
        try { return (int)$this->client->ttl($key); } catch (Exception $e) { return -1; }
    }

    public function exists(string $key): bool {
        if (!$this->client) return false;
        try { return (bool)$this->client->exists($key); } catch (Exception $e) { return false; }
    }

    public function keys(string $pattern): array {
        if (!$this->client) return [];
        try { return $this->client->keys($pattern) ?: []; } catch (Exception $e) { return []; }
    }

    public function smembers(string $key): array {
        if (!$this->client) return [];
        try { return $this->client->smembers($key) ?: []; } catch (Exception $e) { return []; }
    }

    public function sadd(string $key, string $member): bool {
        if (!$this->client) return false;
        try { $this->client->sadd($key, [$member]); return true; } catch (Exception $e) { return false; }
    }

    public function srem(string $key, string $member): bool {
        if (!$this->client) return false;
        try { $this->client->srem($key, [$member]); return true; } catch (Exception $e) { return false; }
    }

    public function sismember(string $key, string $member): bool {
        if (!$this->client) return false;
        try { return (bool)$this->client->sismember($key, $member); } catch (Exception $e) { return false; }
    }
}
