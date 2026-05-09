<?php
/**
 * Restaurant Model — MongoDB restaurants collection
 * Polymorphic menu items: veg | non-veg | beverage
 * Computed pattern: total_orders, avg_rating, total_revenue
 */

require_once __DIR__ . '/../config/database.php';

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

class RestaurantModel {
    private MongoDB\Collection $col;

    public function __construct() {
        $this->col = Database::getInstance()->collection('restaurants');
    }

    // ─── Create ───────────────────────────────────────────────────────────────

    public function create(array $data): ?string {
        $doc = [
            'name'          => trim($data['name']),
            'owner_id'      => $data['owner_id'],
            'email'         => strtolower(trim($data['email'])),
            'password'      => password_hash($data['password'], PASSWORD_BCRYPT),
            'phone'         => $data['phone']       ?? '',
            'address'       => $data['address']     ?? '',
            'city'          => $data['city']        ?? '',
            'cuisine'       => $data['cuisine']     ?? [],   // array of strings
            'description'   => $data['description'] ?? '',
            'image'         => $data['image']       ?? 'default_restaurant.jpg',
            'is_veg'        => (bool)($data['is_veg'] ?? false),
            'opens_at'      => $data['opens_at']   ?? '10:00',
            'closes_at'     => $data['closes_at']  ?? '23:00',
            'delivery_fee'  => (float)($data['delivery_fee'] ?? 30),
            'min_order'     => (float)($data['min_order']    ?? 100),
            'menu_items'    => [],
            // Computed fields
            'total_orders'  => 0,
            'avg_rating'    => 0.0,
            'total_revenue' => 0.0,
            'review_count'  => 0,
            'rating_sum'    => 0.0,
            'is_active'     => true,
            'created_at'    => new UTCDateTime(),
            'updated_at'    => new UTCDateTime(),
        ];

        $result = $this->col->insertOne($doc);
        return $result->getInsertedId() ? (string)$result->getInsertedId() : null;
    }

    // ─── Read ─────────────────────────────────────────────────────────────────

    public function findById(string $id): ?array {
        try {
            $doc = $this->col->findOne(['_id' => new ObjectId($id)]);
            return $doc ? $this->toArray($doc) : null;
        } catch (Exception $e) { return null; }
    }

    public function findByEmail(string $email): ?array {
        $doc = $this->col->findOne(['email' => strtolower(trim($email))]);
        return $doc ? $this->toArray($doc) : null;
    }

    public function getAll(array $filters = [], int $limit = 20, int $skip = 0): array {
        $query = ['is_active' => true];

        if (!empty($filters['cuisine'])) {
            $query['cuisine'] = ['$in' => (array)$filters['cuisine']];
        }
        if (isset($filters['is_veg']) && $filters['is_veg'] !== '') {
            $query['is_veg'] = (bool)$filters['is_veg'];
        }
        if (!empty($filters['min_rating'])) {
            $query['avg_rating'] = ['$gte' => (float)$filters['min_rating']];
        }
        if (!empty($filters['search'])) {
            $query['$text'] = ['$search' => $filters['search']];
        }

        $sort = ['avg_rating' => -1];
        if (!empty($filters['sort'])) {
            if ($filters['sort'] === 'rating')   $sort = ['avg_rating'    => -1];
            if ($filters['sort'] === 'orders')   $sort = ['total_orders'  => -1];
            if ($filters['sort'] === 'revenue')  $sort = ['total_revenue' => -1];
        }

        $cursor = $this->col->find($query, [
            'limit'      => $limit,
            'skip'       => $skip,
            'sort'       => $sort,
            'projection' => [
                'password'   => 0,
                'menu_items' => 0,   // excluded — cards don't need menu data
                'rating_sum' => 0,
            ],
        ]);
        $out = [];
        foreach ($cursor as $doc) $out[] = $this->toArray($doc);
        return $out;
    }

    // ─── Auth ─────────────────────────────────────────────────────────────────

    public function verifyPassword(string $email, string $password): ?array {
        $doc = $this->col->findOne(['email' => strtolower(trim($email))]);
        if (!$doc) return null;
        $arr = $this->toArray($doc);
        if (!password_verify($password, $arr['password'])) return null;
        return $arr;
    }

    // ─── Update ───────────────────────────────────────────────────────────────

    public function update(string $id, array $fields): bool {
        $fields['updated_at'] = new UTCDateTime();
        unset($fields['password'], $fields['_id']);
        try {
            $result = $this->col->updateOne(
                ['_id' => new ObjectId($id)],
                ['$set' => $fields]
            );
            return $result->getModifiedCount() >= 0;
        } catch (Exception $e) { return false; }
    }

    // ─── Menu Item CRUD ───────────────────────────────────────────────────────

    public function addMenuItem(string $restaurantId, array $item): ?string {
        $item['_id']        = new ObjectId();
        $item['created_at'] = new UTCDateTime();

        // Polymorphic fields validation
        $type = $item['type'] ?? 'veg';
        $item['type'] = $type;
        if ($type === 'veg') {
            $item['is_jain']       = (bool)($item['is_jain'] ?? false);
        } elseif ($type === 'non_veg') {
            $item['spice_level']   = $item['spice_level'] ?? 'medium'; // low|medium|high
        } elseif ($type === 'beverage') {
            $item['serving_size_ml'] = (int)($item['serving_size_ml'] ?? 250);
        }
        $item['price']       = (float)$item['price'];
        $item['is_available']= (bool)($item['is_available'] ?? true);

        try {
            $result = $this->col->updateOne(
                ['_id' => new ObjectId($restaurantId)],
                ['$push' => ['menu_items' => $item], '$set' => ['updated_at' => new UTCDateTime()]]
            );
            return $result->getModifiedCount() > 0 ? (string)$item['_id'] : null;
        } catch (Exception $e) { return null; }
    }

    public function updateMenuItem(string $restaurantId, string $itemId, array $fields): bool {
        $update = [];
        foreach ($fields as $k => $v) {
            $update["menu_items.$.$k"] = $v;
        }
        try {
            $result = $this->col->updateOne(
                ['_id' => new ObjectId($restaurantId), 'menu_items._id' => new ObjectId($itemId)],
                ['$set' => $update]
            );
            return $result->getModifiedCount() >= 0;
        } catch (Exception $e) { return false; }
    }

    public function removeMenuItem(string $restaurantId, string $itemId): bool {
        try {
            $result = $this->col->updateOne(
                ['_id' => new ObjectId($restaurantId)],
                ['$pull' => ['menu_items' => ['_id' => new ObjectId($itemId)]]]
            );
            return $result->getModifiedCount() > 0;
        } catch (Exception $e) { return false; }
    }

    // ─── Computed Fields Update ($inc pattern) ────────────────────────────────

    public function incrementOrderStats(string $restaurantId, float $revenue): bool {
        try {
            $this->col->updateOne(
                ['_id' => new ObjectId($restaurantId)],
                ['$inc' => ['total_orders' => 1, 'total_revenue' => $revenue]]
            );
            return true;
        } catch (Exception $e) { return false; }
    }

    public function addRating(string $restaurantId, float $rating): bool {
        // Recalculate avg_rating using rating_sum and review_count
        try {
            $this->col->updateOne(
                ['_id' => new ObjectId($restaurantId)],
                ['$inc' => ['rating_sum' => $rating, 'review_count' => 1]]
            );
            // Fetch updated values to recompute avg
            $doc = $this->col->findOne(['_id' => new ObjectId($restaurantId)], [
                'projection' => ['rating_sum' => 1, 'review_count' => 1]
            ]);
            if ($doc) {
                $sum   = (float)($doc['rating_sum']   ?? 0);
                $count = (int)  ($doc['review_count'] ?? 1);
                $avg   = $count > 0 ? round($sum / $count, 2) : 0.0;
                $this->col->updateOne(
                    ['_id' => new ObjectId($restaurantId)],
                    ['$set' => ['avg_rating' => $avg]]
                );
            }
            return true;
        } catch (Exception $e) { return false; }
    }

    // ─── Admin ────────────────────────────────────────────────────────────────

    public function getAllForAdmin(int $limit = 100): array {
        $cursor = $this->col->find([], [
            'projection' => ['password' => 0],
            'limit'      => $limit,
            'sort'       => ['created_at' => -1],
        ]);
        $out = [];
        foreach ($cursor as $doc) $out[] = $this->toArray($doc);
        return $out;
    }

    public function count(): int {
        return (int)$this->col->countDocuments([]);
    }

    // ─── Aggregation Queries ──────────────────────────────────────────────────

    /** Total revenue per restaurant */
    public function aggregateRevenuePerRestaurant(): array {
        $pipeline = [
            ['$project' => ['name' => 1, 'total_revenue' => 1, 'total_orders' => 1, 'avg_rating' => 1]],
            ['$sort'    => ['total_revenue' => -1]],
        ];
        $cursor = $this->col->aggregate($pipeline);
        $out = [];
        foreach ($cursor as $doc) $out[] = $this->toArray($doc);
        return $out;
    }

    // ─── Internal ─────────────────────────────────────────────────────────────

    private function toArray($doc): array {
        $arr = iterator_to_array($doc) ?? (array)$doc;
        if (isset($arr['_id']))        $arr['_id']  = (string)$arr['_id'];
        if (isset($arr['menu_items'])) {
            $arr['menu_items'] = array_map(function($item) {
                $item = (array)$item;
                if (isset($item['_id'])) $item['_id'] = (string)$item['_id'];
                return $item;
            }, (array)$arr['menu_items']);
        }
        return $arr;
    }
}
