<?php
/**
 * Session Controller — Redis-backed session management
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/redis.php';
require_once __DIR__ . '/../config/database.php';

class SessionController {
    private RedisClient $redis;

    public function __construct() {
        $this->redis = RedisClient::getInstance();
    }

    // ─── Create Session ────────────────────────────────────────────────────────

    public function create(array $userData, string $role = 'user'): string {
        $sessionId = bin2hex(random_bytes(32));
        $key       = SESSION_PREFIX . $sessionId;

        $payload = json_encode([
            'id'    => $userData['_id'],
            'name'  => $userData['name'],
            'email' => $userData['email'],
            'role'  => $role,
        ]);

        if (RedisClient::isAvailable()) {
            $this->redis->set($key, $payload, SESSION_TTL);
        }

        // Cookie-based session fallback: also store in PHP native session
        if (!session_id()) session_start();
        $_SESSION['session_id']   = $sessionId;
        $_SESSION['user_id']      = $userData['_id'];
        $_SESSION['user_name']    = $userData['name'];
        $_SESSION['user_email']   = $userData['email'];
        $_SESSION['user_role']    = $role;

        return $sessionId;
    }

    // ─── Validate Session ──────────────────────────────────────────────────────

    public function validate(string $sessionId): ?array {
        $key = SESSION_PREFIX . $sessionId;

        if (RedisClient::isAvailable()) {
            $data = $this->redis->get($key);
            if ($data) {
                // Refresh TTL on each access
                $this->redis->expire($key, SESSION_TTL);
                return json_decode($data, true);
            }
        }

        // Fallback: PHP native session
        if (!session_id()) session_start();
        if (isset($_SESSION['session_id']) && $_SESSION['session_id'] === $sessionId) {
            return [
                'id'    => $_SESSION['user_id']    ?? null,
                'name'  => $_SESSION['user_name']  ?? '',
                'email' => $_SESSION['user_email'] ?? '',
                'role'  => $_SESSION['user_role']  ?? 'user',
            ];
        }

        return null;
    }

    // ─── Destroy Session ───────────────────────────────────────────────────────

    public function destroy(string $sessionId): void {
        $key = SESSION_PREFIX . $sessionId;
        $this->redis->del($key);

        if (!session_id()) session_start();
        session_destroy();
    }

    // ─── Current User from Cookie ──────────────────────────────────────────────

    public function getCurrentUser(): ?array {
        if (!session_id()) session_start();
        $sessionId = $_COOKIE['session_id'] ?? $_SESSION['session_id'] ?? null;
        if (!$sessionId) return null;
        return $this->validate($sessionId);
    }

    public function requireLogin(): array {
        $user = $this->getCurrentUser();
        if (!$user) {
            header('Location: ' . BASE_URL . '/login.php');
            exit;
        }
        return $user;
    }

    public function requireRole(string $role): array {
        $user = $this->requireLogin();
        if ($user['role'] !== $role) {
            http_response_code(403);
            die(json_encode(['error' => 'Unauthorized']));
        }
        return $user;
    }
}
