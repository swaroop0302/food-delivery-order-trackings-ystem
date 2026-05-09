<?php
/**
 * User Model — MongoDB users collection
 * Handles CRUD, address management, favorites, and authentication
 */

require_once __DIR__ . '/../config/database.php';

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

class UserModel {
    private MongoDB\Collection $col;

    public function __construct() {
        $this->col = Database::getInstance()->collection('users');
    }

    // ─── Create ───────────────────────────────────────────────────────────────

    public function create(array $data): ?string {
        $doc = [
            'name'       => trim($data['name']),
            'email'      => strtolower(trim($data['email'])),
            'password'   => password_hash($data['password'], PASSWORD_BCRYPT),
            'phone'      => $data['phone'] ?? '',
            'addresses'  => [],
            'favorites'  => [],
            'created_at' => new UTCDateTime(),
            'updated_at' => new UTCDateTime(),
        ];

        $result = $this->col->insertOne($doc);
        return $result->getInsertedId() ? (string)$result->getInsertedId() : null;
    }

    // ─── Read ─────────────────────────────────────────────────────────────────

    public function findById(string $id): ?array {
        try {
            // Try Redis cache first
            require_once __DIR__ . '/../config/redis.php';
            if (RedisClient::isAvailable()) {
                $redis  = RedisClient::getInstance();
                $cached = $redis->get('user:profile:' . $id);
                if ($cached !== null) return json_decode($cached, true);
                $doc = $this->col->findOne(['_id' => new ObjectId($id)]);
                $result = $doc ? $this->toArray($doc) : null;
                if ($result) $redis->set('user:profile:' . $id, json_encode($result), 300);
                return $result;
            }
            $doc = $this->col->findOne(['_id' => new ObjectId($id)]);
            return $doc ? $this->toArray($doc) : null;
        } catch (Exception $e) { return null; }
    }

    public function findByEmail(string $email): ?array {
        $doc = $this->col->findOne(['email' => strtolower(trim($email))]);
        return $doc ? $this->toArray($doc) : null;
    }

    // ─── Update ───────────────────────────────────────────────────────────────

    public function update(string $id, array $fields): bool {
        $fields['updated_at'] = new UTCDateTime();
        unset($fields['password']);
        try {
            $result = $this->col->updateOne(
                ['_id' => new ObjectId($id)],
                ['$set' => $fields]
            );
            // Bust cache
            require_once __DIR__ . '/../config/redis.php';
            if (RedisClient::isAvailable()) {
                RedisClient::getInstance()->del('user:profile:' . $id);
            }
            return $result->getModifiedCount() > 0;
        } catch (Exception $e) { return false; }
    }

    public function updatePassword(string $id, string $newPassword): bool {
        try {
            $result = $this->col->updateOne(
                ['_id' => new ObjectId($id)],
                ['$set' => [
                    'password'   => password_hash($newPassword, PASSWORD_BCRYPT),
                    'updated_at' => new UTCDateTime(),
                ]]
            );
            return $result->getModifiedCount() > 0;
        } catch (Exception $e) { return false; }
    }

    // ─── Addresses ────────────────────────────────────────────────────────────

    public function addAddress(string $userId, array $address): bool {
        $address['_id']        = new ObjectId();
        $address['created_at'] = new UTCDateTime();
        try {
            $result = $this->col->updateOne(
                ['_id' => new ObjectId($userId)],
                ['$push' => ['addresses' => $address], '$set' => ['updated_at' => new UTCDateTime()]]
            );
            return $result->getModifiedCount() > 0;
        } catch (Exception $e) { return false; }
    }

    public function removeAddress(string $userId, string $addressId): bool {
        try {
            $result = $this->col->updateOne(
                ['_id' => new ObjectId($userId)],
                ['$pull' => ['addresses' => ['_id' => new ObjectId($addressId)]]]
            );
            return $result->getModifiedCount() > 0;
        } catch (Exception $e) { return false; }
    }

    // ─── Favorites ────────────────────────────────────────────────────────────

    public function addFavorite(string $userId, string $restaurantId): bool {
        try {
            $result = $this->col->updateOne(
                ['_id' => new ObjectId($userId)],
                ['$addToSet' => ['favorites' => $restaurantId]]
            );
            return true;
        } catch (Exception $e) { return false; }
    }

    public function removeFavorite(string $userId, string $restaurantId): bool {
        try {
            $result = $this->col->updateOne(
                ['_id' => new ObjectId($userId)],
                ['$pull' => ['favorites' => $restaurantId]]
            );
            return true;
        } catch (Exception $e) { return false; }
    }

    // ─── Auth ─────────────────────────────────────────────────────────────────

    public function verifyPassword(string $email, string $password): ?array {
        $user = $this->findByEmail($email);
        if (!$user) return null;
        if (!password_verify($password, $user['password'])) return null;
        return $user;
    }

    // ─── Admin helpers ────────────────────────────────────────────────────────

    public function getAll(int $limit = 50, int $skip = 0): array {
        $cursor = $this->col->find([], [
            'projection' => ['password' => 0],
            'limit'      => $limit,
            'skip'       => $skip,
            'sort'       => ['created_at' => -1],
        ]);
        $out = [];
        foreach ($cursor as $doc) $out[] = $this->toArray($doc);
        return $out;
    }

    public function count(): int {
        return (int)$this->col->countDocuments([]);
    }

    // ─── Internal ─────────────────────────────────────────────────────────────

    private function toArray($doc): array {
        $arr = (array)$doc;
        if (isset($arr['_id']))   $arr['_id']   = (string)$arr['_id'];
        if (isset($arr['addresses'])) {
            $arr['addresses'] = array_map(function($a) {
                $a = (array)$a;
                if (isset($a['_id'])) $a['_id'] = (string)$a['_id'];
                return $a;
            }, (array)$arr['addresses']);
        }
        if (isset($arr['favorites'])) {
            $arr['favorites'] = (array)$arr['favorites'];
        }
        return $arr;
    }
}
