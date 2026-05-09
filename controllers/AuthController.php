<?php
/**
 * Auth Controller — Registration, login, logout for users and restaurants
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../models/UserModel.php';
require_once __DIR__ . '/../models/RestaurantModel.php';
require_once __DIR__ . '/../controllers/SessionController.php';

class AuthController {
    private UserModel       $userModel;
    private RestaurantModel $restaurantModel;
    private SessionController $session;

    public function __construct() {
        $this->userModel       = new UserModel();
        $this->restaurantModel = new RestaurantModel();
        $this->session         = new SessionController();
    }

    // ─── User Registration ────────────────────────────────────────────────────

    public function registerUser(array $data): array {
        // Validation
        $errors = $this->validateUserData($data);
        if (!empty($errors)) return ['success' => false, 'errors' => $errors];

        // Email uniqueness
        if ($this->userModel->findByEmail($data['email'])) {
            return ['success' => false, 'errors' => ['email' => 'Email already registered']];
        }

        $userId = $this->userModel->create($data);
        if (!$userId) return ['success' => false, 'errors' => ['general' => 'Registration failed. Please try again.']];

        return ['success' => true, 'user_id' => $userId];
    }

    // ─── User Login ───────────────────────────────────────────────────────────

    public function loginUser(string $email, string $password): array {
        if (empty($email) || empty($password)) {
            return ['success' => false, 'error' => 'Email and password are required'];
        }

        $user = $this->userModel->verifyPassword($email, $password);
        if (!$user) return ['success' => false, 'error' => 'Invalid email or password'];

        $sessionId = $this->session->create($user, 'user');
        setcookie('session_id', $sessionId, time() + SESSION_TTL, '/', '', false, true);

        return ['success' => true, 'session_id' => $sessionId, 'user' => $user];
    }

    // ─── Restaurant Registration ──────────────────────────────────────────────

    public function registerRestaurant(array $data): array {
        $errors = $this->validateRestaurantData($data);
        if (!empty($errors)) return ['success' => false, 'errors' => $errors];

        if ($this->restaurantModel->findByEmail($data['email'])) {
            return ['success' => false, 'errors' => ['email' => 'Email already registered']];
        }

        // cuisine as array
        if (isset($data['cuisine']) && is_string($data['cuisine'])) {
            $data['cuisine'] = array_map('trim', explode(',', $data['cuisine']));
        }
        $data['owner_id'] = 'owner_' . time();

        $restId = $this->restaurantModel->create($data);
        if (!$restId) return ['success' => false, 'errors' => ['general' => 'Registration failed.']];

        return ['success' => true, 'restaurant_id' => $restId];
    }

    // ─── Restaurant Login ─────────────────────────────────────────────────────

    public function loginRestaurant(string $email, string $password): array {
        if (empty($email) || empty($password)) {
            return ['success' => false, 'error' => 'Email and password are required'];
        }

        $restaurant = $this->restaurantModel->verifyPassword($email, $password);
        if (!$restaurant) return ['success' => false, 'error' => 'Invalid email or password'];

        $sessionId = $this->session->create($restaurant, 'restaurant');
        setcookie('session_id', $sessionId, time() + SESSION_TTL, '/', '', false, true);

        return ['success' => true, 'session_id' => $sessionId, 'restaurant' => $restaurant];
    }

    // ─── Admin Login ──────────────────────────────────────────────────────────

    public function loginAdmin(string $email, string $password): array {
        $adminEmail = defined('ADMIN_EMAIL') ? ADMIN_EMAIL : 'admin@foodrush.com';
        $adminPass  = defined('ADMIN_PASS')  ? ADMIN_PASS  : 'admin123';

        if ($email !== $adminEmail || $password !== $adminPass) {
            return ['success' => false, 'error' => 'Invalid admin credentials'];
        }

        $fakeUser  = ['_id' => 'admin', 'name' => 'Admin', 'email' => $adminEmail];
        $sessionId = $this->session->create($fakeUser, 'admin');
        setcookie('session_id', $sessionId, time() + SESSION_TTL, '/', '', false, true);

        return ['success' => true, 'session_id' => $sessionId];
    }

    // ─── Logout ───────────────────────────────────────────────────────────────

    public function logout(): void {
        if (!session_id()) session_start();
        $sessionId = $_COOKIE['session_id'] ?? $_SESSION['session_id'] ?? null;
        if ($sessionId) $this->session->destroy($sessionId);
        setcookie('session_id', '', time() - 3600, '/');
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }

    // ─── Validation ───────────────────────────────────────────────────────────

    private function validateUserData(array $data): array {
        $errors = [];
        if (empty($data['name']))     $errors['name']     = 'Name is required';
        if (empty($data['email']))    $errors['email']    = 'Email is required';
        elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Invalid email';
        if (empty($data['password'])) $errors['password'] = 'Password is required';
        elseif (strlen($data['password']) < 6) $errors['password'] = 'Password must be at least 6 characters';
        if (isset($data['confirm_password']) && $data['password'] !== $data['confirm_password']) {
            $errors['confirm_password'] = 'Passwords do not match';
        }
        return $errors;
    }

    private function validateRestaurantData(array $data): array {
        $errors = [];
        if (empty($data['name']))    $errors['name']    = 'Restaurant name is required';
        if (empty($data['email']))   $errors['email']   = 'Email is required';
        elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Invalid email';
        if (empty($data['password'])) $errors['password'] = 'Password is required';
        if (empty($data['city']))    $errors['city']    = 'City is required';
        if (empty($data['cuisine'])) $errors['cuisine'] = 'At least one cuisine type is required';
        return $errors;
    }
}
