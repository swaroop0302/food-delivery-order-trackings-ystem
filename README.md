# 🍕 Food Delivery Order Tracking System — MongoDB Data Layer

A complete, production-ready MongoDB data layer for a Food Delivery Order Tracking System, built using **pure MongoDB aggregation pipelines** and four key MongoDB schema design patterns.

---

## 📦 Tech Stack

| Tool | Purpose |
|---|---|
| **MongoDB** | Database |
| **mongosh** | MongoDB Shell to run the script |
| **Aggregation Pipeline** | Analytics queries |

---

## 🗂️ Collections & Schema Design

### `restaurants`
Stores menu items using the **Polymorphic Pattern** — food items and beverage items live in the same `menu[]` array with different fields.

```json
{
  "name": "Spice Garden",
  "avg_rating": 4.5,
  "total_orders": 4,
  "total_revenue": 1830,
  "location": { "city": "Mumbai", "address": "12 Marine Drive" },
  "menu": [
    { "name": "Paneer Tikka", "category": "food", "type": "veg", "spice_level": "medium", "price": 220 },
    { "name": "Mango Lassi", "category": "beverage", "type": "cold", "serving_size_ml": 300, "price": 80 }
  ]
}
```

### `users`
Stores delivery addresses using the **One-to-Few Embedding Pattern** — addresses are always read with the user, so embedding avoids a separate collection.

```json
{
  "name": "Aarav Sharma",
  "email": "aarav@email.com",
  "phone": "9000000001",
  "addresses": [
    { "label": "home", "street": "14 Park Lane", "city": "Mumbai", "pincode": "400001" }
  ]
}
```

### `orders`
References users & restaurants (**Reference Pattern**) and embeds ordered items (**Embedding Pattern** with price snapshot).

```json
{
  "user_id": "<ObjectId>",
  "restaurant_id": "<ObjectId>",
  "status": "delivered",
  "total": 380,
  "items": [
    { "name": "Paneer Tikka", "quantity": 1, "price": 220 }
  ],
  "timestamps": {
    "placed_at": "2024-01-10T10:00:00Z",
    "delivered_at": "2024-01-10T10:42:00Z"
  }
}
```

---

## 🧩 Design Patterns Used

| Pattern | Collection | Why |
|---|---|---|
| **Polymorphic** | `restaurants.menu[]` | Food & beverages share name/price but have different fields |
| **One-to-Few Embed** | `users.addresses[]` | Max 2–3 addresses per user; always read together |
| **Reference** | `orders.user_id`, `orders.restaurant_id` | Large, independent, frequently-updated docs |
| **Embed sub-docs** | `orders.items[]` | Price snapshot preserves historical order value |
| **Computed** | `restaurants.total_orders`, `total_revenue` | O(1) reads via `$inc`; no runtime aggregation needed |

---

## 📊 Aggregation Queries

### Query 1 — Total Revenue per Restaurant
```js
db.orders.aggregate([
  { $group: { _id: "$restaurant_id", total_revenue: { $sum: "$total" }, order_count: { $sum: 1 } } },
  { $lookup: { from: "restaurants", localField: "_id", foreignField: "_id", as: "restaurant" } },
  { $unwind: "$restaurant" },
  { $project: { _id: 0, restaurant_name: "$restaurant.name", total_revenue: 1, order_count: 1 } },
  { $sort: { total_revenue: -1 } }
])
```

**Output:**
```
Pizza Nova      ₹2450  (4 orders)
Wok Wok         ₹1860  (4 orders)
Spice Garden    ₹1830  (4 orders)
The Burger Lab  ₹1800  (4 orders)
Dosa Palace     ₹1550  (4 orders)
```

---

### Query 2 — Top 5 Most Ordered Items
```js
db.orders.aggregate([
  { $unwind: "$items" },
  { $group: { _id: "$items.name", total_quantity: { $sum: "$items.quantity" }, times_ordered: { $sum: 1 } } },
  { $sort: { total_quantity: -1 } },
  { $limit: 5 },
  { $project: { _id: 0, item_name: "$_id", total_quantity: 1, times_ordered: 1 } }
])
```

---

### Query 3 — Average Delivery Time (minutes)
```js
db.orders.aggregate([
  { $match: { status: "delivered", "timestamps.delivered_at": { $ne: null } } },
  { $addFields: { delivery_mins: { $divide: [{ $subtract: ["$timestamps.delivered_at", "$timestamps.placed_at"] }, 60000] } } },
  { $group: { _id: null, avg_delivery_mins: { $avg: "$delivery_mins" }, min_delivery_mins: { $min: "$delivery_mins" }, max_delivery_mins: { $max: "$delivery_mins" } } },
  { $project: { _id: 0, avg_delivery_mins: { $round: ["$avg_delivery_mins", 1] }, min_delivery_mins: 1, max_delivery_mins: 1 } }
])
```

---

## ⚡ Computed Pattern — Real-Time Updates

When a new order is placed, atomically update restaurant stats with `$inc`:

```js
db.restaurants.updateOne(
  { _id: restaurantId },
  { $inc: { total_orders: 1, total_revenue: orderTotal } }
)
```

> `$inc` is atomic and lock-free — no read-before-write needed. Dashboard reads become O(1).

---

## 🔍 Indexes

```js
db.orders.createIndex({ restaurant_id: 1, status: 1 })   // aggregation filter
db.orders.createIndex({ user_id: 1 })                    // user order history
db.orders.createIndex({ "timestamps.placed_at": -1 })    // recent orders
db.restaurants.createIndex({ "location.city": 1 })       // city-based search
```

---

## 🚀 How to Run

### Prerequisites
- [MongoDB](https://www.mongodb.com/try/download/community) installed locally
- [mongosh](https://www.mongodb.com/try/download/shell) installed

### Run the Setup Script
```bash
mongosh "mongodb://localhost:27017" --file food_delivery_setup.js
```

This will:
1. Create the `food_delivery` database and 3 collections
2. Insert 5 restaurants, 5 users, and 20 orders
3. Run all 3 aggregation queries and print results
4. Seed computed fields (`total_orders`, `total_revenue`)
5. Create all 4 production indexes
6. Print a final verification summary

> ✅ The script is **idempotent** — safe to re-run; it drops and reseeds data each time.

---

## 🗃️ Sample Data

| Collection | Documents |
|---|---|
| restaurants | 5 (Mumbai, Bangalore, Chennai, Delhi, Hyderabad) |
| users | 5 (one per city) |
| orders | 20 (4 per restaurant, mix of all statuses) |

### Order Statuses present
`placed` · `accepted` · `preparing` · `out_for_delivery` · `delivered`
