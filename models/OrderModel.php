<?php
/**
 * Order Model — MongoDB orders collection
 * Manages order persistence, status, and aggregation queries
 */

require_once __DIR__ . '/../config/database.php';

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

class OrderModel {
    private MongoDB\Collection $col;

    public function __construct() {
        $this->col = Database::getInstance()->collection('orders');
    }

    // ─── Create ───────────────────────────────────────────────────────────────

    public function create(array $data): ?string {
        $doc = [
            'user_id'       => $data['user_id'],
            'restaurant_id' => $data['restaurant_id'],
            'items'         => $data['items'],       // embedded array
            'total_price'   => (float)$data['total_price'],
            'delivery_address' => $data['delivery_address'] ?? [],
            'status'        => STATUS_PLACED,
            'placed_at'     => new UTCDateTime(),
            'accepted_at'   => null,
            'preparing_at'  => null,
            'out_for_delivery_at' => null,
            'delivered_at'  => null,
            'cancelled_at'  => null,
            'eta_minutes'   => (int)($data['eta_minutes'] ?? 30),
            'notes'         => $data['notes'] ?? '',
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

    public function findByUser(string $userId, int $limit = 20): array {
        $cursor = $this->col->find(
            ['user_id' => $userId],
            ['sort' => ['placed_at' => -1], 'limit' => $limit]
        );
        $out = [];
        foreach ($cursor as $doc) $out[] = $this->toArray($doc);
        return $out;
    }

    public function findByRestaurant(string $restaurantId, int $limit = 50): array {
        $cursor = $this->col->find(
            ['restaurant_id' => $restaurantId],
            ['sort' => ['placed_at' => -1], 'limit' => $limit]
        );
        $out = [];
        foreach ($cursor as $doc) $out[] = $this->toArray($doc);
        return $out;
    }

    public function getAll(int $limit = 100, int $skip = 0): array {
        $cursor = $this->col->find([], [
            'sort'  => ['placed_at' => -1],
            'limit' => $limit,
            'skip'  => $skip,
        ]);
        $out = [];
        foreach ($cursor as $doc) $out[] = $this->toArray($doc);
        return $out;
    }

    // ─── Status Update ────────────────────────────────────────────────────────

    public function updateStatus(string $id, string $status): bool {
        $timestampField = match($status) {
            STATUS_ACCEPTED         => 'accepted_at',
            STATUS_PREPARING        => 'preparing_at',
            STATUS_OUT_FOR_DELIVERY => 'out_for_delivery_at',
            STATUS_DELIVERED        => 'delivered_at',
            STATUS_CANCELLED        => 'cancelled_at',
            default                 => null,
        };
        $set = ['status' => $status];
        if ($timestampField) $set[$timestampField] = new UTCDateTime();

        try {
            $result = $this->col->updateOne(
                ['_id' => new ObjectId($id)],
                ['$set' => $set]
            );
            return $result->getModifiedCount() >= 0;
        } catch (Exception $e) { return false; }
    }

    // ─── Aggregation Queries ──────────────────────────────────────────────────

    /** Most ordered item across all orders */
    public function aggregateMostOrderedItem(): array {
        $pipeline = [
            ['$unwind'  => '$items'],
            ['$group'   => [
                '_id'         => ['item_id' => '$items.item_id', 'name' => '$items.name'],
                'total_qty'   => ['$sum' => '$items.quantity'],
                'total_rev'   => ['$sum' => ['$multiply' => ['$items.price', '$items.quantity']]],
            ]],
            ['$sort'    => ['total_qty' => -1]],
            ['$limit'   => 10],
            ['$project' => ['item_id' => '$_id.item_id', 'name' => '$_id.name', 'total_qty' => 1, 'total_rev' => 1, '_id' => 0]],
        ];
        $cursor = $this->col->aggregate($pipeline);
        $out = [];
        foreach ($cursor as $doc) $out[] = iterator_to_array($doc);
        return $out;
    }

    /** Average delivery time (placed_at → delivered_at) in minutes */
    public function aggregateAvgDeliveryTime(): float {
        $pipeline = [
            ['$match'   => ['status' => STATUS_DELIVERED, 'delivered_at' => ['$ne' => null]]],
            ['$project' => [
                'delivery_ms' => ['$subtract' => ['$delivered_at', '$placed_at']],
            ]],
            ['$group'   => ['_id' => null, 'avg_ms' => ['$avg' => '$delivery_ms']]],
        ];
        $cursor = $this->col->aggregate($pipeline);
        foreach ($cursor as $doc) {
            return round((float)($doc['avg_ms'] ?? 0) / 60000, 1); // ms → minutes
        }
        return 0.0;
    }

    /** Revenue per restaurant from orders collection */
    public function aggregateRevenueByRestaurant(): array {
        $pipeline = [
            ['$match'  => ['status' => STATUS_DELIVERED]],
            ['$group'  => [
                '_id'     => '$restaurant_id',
                'revenue' => ['$sum' => '$total_price'],
                'orders'  => ['$sum' => 1],
            ]],
            ['$sort'   => ['revenue' => -1]],
        ];
        $cursor = $this->col->aggregate($pipeline);
        $out = [];
        foreach ($cursor as $doc) $out[] = iterator_to_array($doc);
        return $out;
    }

    /** Count by status */
    public function countByStatus(): array {
        $pipeline = [
            ['$group' => ['_id' => '$status', 'count' => ['$sum' => 1]]],
        ];
        $cursor = $this->col->aggregate($pipeline);
        $out = [];
        foreach ($cursor as $doc) {
            $out[(string)$doc['_id']] = (int)$doc['count'];
        }
        return $out;
    }

    public function count(): int {
        return (int)$this->col->countDocuments([]);
    }

    // ─── Internal ─────────────────────────────────────────────────────────────

    private function toArray($doc): array {
        $arr = iterator_to_array($doc);
        if (isset($arr['_id'])) $arr['_id'] = (string)$arr['_id'];
        // Convert UTCDateTime fields to ISO strings
        foreach (['placed_at','accepted_at','preparing_at','out_for_delivery_at','delivered_at','cancelled_at'] as $f) {
            if (isset($arr[$f]) && $arr[$f] instanceof UTCDateTime) {
                $arr[$f] = $arr[$f]->toDateTime()->format('c');
            }
        }
        if (isset($arr['items'])) {
            $arr['items'] = array_map(fn($i) => (array)$i, (array)$arr['items']);
        }
        return $arr;
    }
}
