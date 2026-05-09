<?php
/**
 * Review Model — MongoDB reviews collection
 */

require_once __DIR__ . '/../config/database.php';

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

class ReviewModel {
    private MongoDB\Collection $col;

    public function __construct() {
        $this->col = Database::getInstance()->collection('reviews');
    }

    public function create(array $data): ?string {
        // Prevent duplicate review for same order
        $existing = $this->col->findOne([
            'order_id' => $data['order_id'],
            'user_id'  => $data['user_id'],
        ]);
        if ($existing) return null;

        $doc = [
            'user_id'       => $data['user_id'],
            'user_name'     => $data['user_name'] ?? 'Anonymous',
            'restaurant_id' => $data['restaurant_id'],
            'order_id'      => $data['order_id'],
            'rating'        => (float)$data['rating'],
            'comment'       => trim($data['comment'] ?? ''),
            'created_at'    => new UTCDateTime(),
        ];

        $result = $this->col->insertOne($doc);
        return $result->getInsertedId() ? (string)$result->getInsertedId() : null;
    }

    public function findByRestaurant(string $restaurantId, int $limit = 20): array {
        $cursor = $this->col->find(
            ['restaurant_id' => $restaurantId],
            ['sort' => ['created_at' => -1], 'limit' => $limit]
        );
        $out = [];
        foreach ($cursor as $doc) {
            $arr = iterator_to_array($doc);
            $arr['_id'] = (string)$arr['_id'];
            if (isset($arr['created_at']) && $arr['created_at'] instanceof UTCDateTime) {
                $arr['created_at'] = $arr['created_at']->toDateTime()->format('c');
            }
            $out[] = $arr;
        }
        return $out;
    }

    public function findByUser(string $userId): array {
        $cursor = $this->col->find(
            ['user_id' => $userId],
            ['sort' => ['created_at' => -1]]
        );
        $out = [];
        foreach ($cursor as $doc) {
            $arr = iterator_to_array($doc);
            $arr['_id'] = (string)$arr['_id'];
            $out[] = $arr;
        }
        return $out;
    }
}
