// ===========================================================================
// FOOD DELIVERY ORDER TRACKING SYSTEM — MongoDB Data Layer
// Run with: mongosh "mongodb://localhost:27017" --file food_delivery_setup.js
// ===========================================================================

// ---------------------------------------------------------------------------
// STEP 1: DATABASE SETUP
// ---------------------------------------------------------------------------

print("\n========================================");
print("STEP 1: Database & Collection Setup");
print("========================================");

use("food_delivery");

db.createCollection("restaurants");
db.createCollection("users");
db.createCollection("orders");

print("✅ Collections created: restaurants, users, orders");

// ---------------------------------------------------------------------------
// STEP 2 & 3: INSERT SAMPLE DATA — 5 RESTAURANTS (Polymorphic Pattern)
// ---------------------------------------------------------------------------

print("\n========================================");
print("STEP 3a: Inserting 5 Restaurants");
print("========================================");

// Drop existing data to make the script idempotent on re-runs
db.restaurants.deleteMany({});
db.users.deleteMany({});
db.orders.deleteMany({});

db.restaurants.insertMany([
  {
    name: "Spice Garden",
    avg_rating: 4.5,
    total_orders: 0,
    total_revenue: 0,
    location: { city: "Mumbai", address: "12 Marine Drive" },
    menu: [
      // Food items — have spice_level (Polymorphic Pattern)
      { name: "Paneer Tikka",    category: "food",     type: "veg",     spice_level: "medium", price: 220 },
      { name: "Chicken Biryani", category: "food",     type: "non-veg", spice_level: "hot",    price: 310 },
      // Beverage items — have serving_size_ml instead (Polymorphic Pattern)
      { name: "Mango Lassi",     category: "beverage", type: "cold",    serving_size_ml: 300,  price: 80  }
    ]
  },
  {
    name: "The Burger Lab",
    avg_rating: 4.2,
    total_orders: 0,
    total_revenue: 0,
    location: { city: "Bangalore", address: "5 MG Road" },
    menu: [
      { name: "Classic Veggie Burger", category: "food",     type: "veg",     spice_level: "mild",   price: 180 },
      { name: "BBQ Chicken Burger",    category: "food",     type: "non-veg", spice_level: "medium", price: 240 },
      { name: "Cold Coffee",           category: "beverage", type: "cold",    serving_size_ml: 350,  price: 120 }
    ]
  },
  {
    name: "Dosa Palace",
    avg_rating: 4.7,
    total_orders: 0,
    total_revenue: 0,
    location: { city: "Chennai", address: "88 Anna Salai" },
    menu: [
      { name: "Masala Dosa",       category: "food",     type: "veg",     spice_level: "mild", price: 120 },
      { name: "Chettinad Chicken", category: "food",     type: "non-veg", spice_level: "hot",  price: 290 },
      { name: "Filter Coffee",     category: "beverage", type: "hot",     serving_size_ml: 150, price: 50 }
    ]
  },
  {
    name: "Pizza Nova",
    avg_rating: 4.0,
    total_orders: 0,
    total_revenue: 0,
    location: { city: "Delhi", address: "3 Connaught Place" },
    menu: [
      { name: "Margherita Pizza", category: "food",     type: "veg",     spice_level: "mild",   price: 350 },
      { name: "Pepperoni Pizza",  category: "food",     type: "non-veg", spice_level: "medium", price: 420 },
      { name: "Lemonade",        category: "beverage", type: "cold",    serving_size_ml: 250,  price: 70  }
    ]
  },
  {
    name: "Wok Wok",
    avg_rating: 4.3,
    total_orders: 0,
    total_revenue: 0,
    location: { city: "Hyderabad", address: "21 Banjara Hills" },
    menu: [
      { name: "Veg Fried Rice",    category: "food",     type: "veg",     spice_level: "medium", price: 160 },
      { name: "Chicken Noodles",   category: "food",     type: "non-veg", spice_level: "medium", price: 200 },
      { name: "Green Tea",         category: "beverage", type: "hot",     serving_size_ml: 200,  price: 60  }
    ]
  }
]);

print("✅ 5 restaurants inserted (Polymorphic Pattern: food + beverage in same menu[])");

// ---------------------------------------------------------------------------
// INSERT 5 USERS (One-to-Few Embedding Pattern)
// ---------------------------------------------------------------------------

print("\n========================================");
print("STEP 3b: Inserting 5 Users");
print("========================================");

db.users.insertMany([
  {
    name: "Aarav Sharma", email: "aarav@email.com", phone: "9000000001",
    // addresses[] embedded — always read with user doc (One-to-Few Pattern)
    addresses: [
      { label: "home", street: "14 Park Lane", city: "Mumbai", pincode: "400001" }
    ]
  },
  {
    name: "Priya Nair", email: "priya@email.com", phone: "9000000002",
    addresses: [
      { label: "home",   street: "2 Rose Garden", city: "Bangalore", pincode: "560001" },
      { label: "office", street: "78 Tech Park",  city: "Bangalore", pincode: "560037" }
    ]
  },
  {
    name: "Rahul Das", email: "rahul@email.com", phone: "9000000003",
    addresses: [
      { label: "home", street: "56 Lake View", city: "Chennai", pincode: "600001" }
    ]
  },
  {
    name: "Sneha Iyer", email: "sneha@email.com", phone: "9000000004",
    addresses: [
      { label: "home", street: "9 Green Ave", city: "Delhi", pincode: "110001" }
    ]
  },
  {
    name: "Karan Mehta", email: "karan@email.com", phone: "9000000005",
    addresses: [
      { label: "home",   street: "33 Star Colony",  city: "Hyderabad", pincode: "500001" },
      { label: "office", street: "1 Cyber Towers",  city: "Hyderabad", pincode: "500081" }
    ]
  }
]);

print("✅ 5 users inserted (One-to-Few Embedding: addresses[] inside user doc)");

// ---------------------------------------------------------------------------
// INSERT 20 ORDERS (Reference Pattern + Embedded Items)
// Capture restaurant/user ObjectIds first, then reference them in orders
// ---------------------------------------------------------------------------

print("\n========================================");
print("STEP 3c: Inserting 20 Orders");
print("========================================");

// Capture IDs in insertion order (matches insertMany order above)
const r = db.restaurants.find({}, { _id: 1 }).toArray();
const u = db.users.find({},     { _id: 1 }).toArray();

const [r1, r2, r3, r4, r5] = r.map(x => x._id);   // restaurant ObjectIds
const [u1, u2, u3, u4, u5] = u.map(x => x._id);   // user ObjectIds

db.orders.insertMany([

  // ── Spice Garden (r1) — 4 orders ──────────────────────────────────────
  {
    user_id: u1, restaurant_id: r1, status: "delivered", total: 380,
    // items[] embed price snapshot — preserves historical price (Embed Pattern)
    items: [
      { name: "Paneer Tikka", quantity: 1, price: 220 },
      { name: "Mango Lassi",  quantity: 2, price: 80  }
    ],
    timestamps: {
      placed_at:    new Date("2024-01-10T10:00:00Z"),
      delivered_at: new Date("2024-01-10T10:42:00Z")
    }
  },
  {
    user_id: u2, restaurant_id: r1, status: "delivered", total: 620,
    items: [{ name: "Chicken Biryani", quantity: 2, price: 310 }],
    timestamps: {
      placed_at:    new Date("2024-01-11T12:00:00Z"),
      delivered_at: new Date("2024-01-11T12:55:00Z")
    }
  },
  {
    user_id: u3, restaurant_id: r1, status: "delivered", total: 520,
    items: [
      { name: "Paneer Tikka", quantity: 2, price: 220 },
      { name: "Mango Lassi",  quantity: 1, price: 80  }
    ],
    timestamps: {
      placed_at:    new Date("2024-01-12T14:00:00Z"),
      delivered_at: new Date("2024-01-12T14:38:00Z")
    }
  },
  {
    user_id: u4, restaurant_id: r1, status: "preparing", total: 310,
    items: [{ name: "Chicken Biryani", quantity: 1, price: 310 }],
    timestamps: { placed_at: new Date("2024-01-13T09:00:00Z"), delivered_at: null }
  },

  // ── The Burger Lab (r2) — 4 orders ────────────────────────────────────
  {
    user_id: u1, restaurant_id: r2, status: "delivered", total: 720,
    items: [
      { name: "BBQ Chicken Burger", quantity: 2, price: 240 },
      { name: "Cold Coffee",        quantity: 2, price: 120 }
    ],
    timestamps: {
      placed_at:    new Date("2024-01-10T18:00:00Z"),
      delivered_at: new Date("2024-01-10T18:35:00Z")
    }
  },
  {
    user_id: u5, restaurant_id: r2, status: "delivered", total: 540,
    items: [{ name: "Classic Veggie Burger", quantity: 3, price: 180 }],
    timestamps: {
      placed_at:    new Date("2024-01-11T19:00:00Z"),
      delivered_at: new Date("2024-01-11T19:40:00Z")
    }
  },
  {
    user_id: u2, restaurant_id: r2, status: "delivered", total: 300,
    items: [
      { name: "Classic Veggie Burger", quantity: 1, price: 180 },
      { name: "Cold Coffee",           quantity: 1, price: 120 }
    ],
    timestamps: {
      placed_at:    new Date("2024-01-12T20:00:00Z"),
      delivered_at: new Date("2024-01-12T20:28:00Z")
    }
  },
  {
    user_id: u3, restaurant_id: r2, status: "accepted", total: 240,
    items: [{ name: "BBQ Chicken Burger", quantity: 1, price: 240 }],
    timestamps: { placed_at: new Date("2024-01-13T11:00:00Z"), delivered_at: null }
  },

  // ── Dosa Palace (r3) — 4 orders ───────────────────────────────────────
  {
    user_id: u4, restaurant_id: r3, status: "delivered", total: 340,
    items: [
      { name: "Masala Dosa",   quantity: 2, price: 120 },
      { name: "Filter Coffee", quantity: 2, price: 50  }
    ],
    timestamps: {
      placed_at:    new Date("2024-01-10T08:00:00Z"),
      delivered_at: new Date("2024-01-10T08:30:00Z")
    }
  },
  {
    user_id: u5, restaurant_id: r3, status: "delivered", total: 580,
    items: [{ name: "Chettinad Chicken", quantity: 2, price: 290 }],
    timestamps: {
      placed_at:    new Date("2024-01-11T13:00:00Z"),
      delivered_at: new Date("2024-01-11T13:45:00Z")
    }
  },
  {
    user_id: u1, restaurant_id: r3, status: "delivered", total: 510,
    items: [
      { name: "Masala Dosa",   quantity: 3, price: 120 },
      { name: "Filter Coffee", quantity: 3, price: 50  }
    ],
    timestamps: {
      placed_at:    new Date("2024-01-12T07:30:00Z"),
      delivered_at: new Date("2024-01-12T08:05:00Z")
    }
  },
  {
    user_id: u2, restaurant_id: r3, status: "placed", total: 120,
    items: [{ name: "Masala Dosa", quantity: 1, price: 120 }],
    timestamps: { placed_at: new Date("2024-01-13T07:00:00Z"), delivered_at: null }
  },

  // ── Pizza Nova (r4) — 4 orders ─────────────────────────────────────────
  {
    user_id: u3, restaurant_id: r4, status: "delivered", total: 490,
    items: [
      { name: "Margherita Pizza", quantity: 1, price: 350 },
      { name: "Lemonade",         quantity: 2, price: 70  }
    ],
    timestamps: {
      placed_at:    new Date("2024-01-10T20:00:00Z"),
      delivered_at: new Date("2024-01-10T20:50:00Z")
    }
  },
  {
    user_id: u4, restaurant_id: r4, status: "delivered", total: 840,
    items: [{ name: "Pepperoni Pizza", quantity: 2, price: 420 }],
    timestamps: {
      placed_at:    new Date("2024-01-11T21:00:00Z"),
      delivered_at: new Date("2024-01-11T21:55:00Z")
    }
  },
  {
    user_id: u5, restaurant_id: r4, status: "delivered", total: 700,
    items: [{ name: "Margherita Pizza", quantity: 2, price: 350 }],
    timestamps: {
      placed_at:    new Date("2024-01-12T19:00:00Z"),
      delivered_at: new Date("2024-01-12T20:00:00Z")
    }
  },
  {
    user_id: u1, restaurant_id: r4, status: "out_for_delivery", total: 420,
    items: [{ name: "Pepperoni Pizza", quantity: 1, price: 420 }],
    timestamps: { placed_at: new Date("2024-01-13T19:30:00Z"), delivered_at: null }
  },

  // ── Wok Wok (r5) — 4 orders ────────────────────────────────────────────
  {
    user_id: u2, restaurant_id: r5, status: "delivered", total: 440,
    items: [
      { name: "Veg Fried Rice", quantity: 2, price: 160 },
      { name: "Green Tea",      quantity: 2, price: 60  }
    ],
    timestamps: {
      placed_at:    new Date("2024-01-10T13:00:00Z"),
      delivered_at: new Date("2024-01-10T13:38:00Z")
    }
  },
  {
    user_id: u3, restaurant_id: r5, status: "delivered", total: 400,
    items: [{ name: "Chicken Noodles", quantity: 2, price: 200 }],
    timestamps: {
      placed_at:    new Date("2024-01-11T14:00:00Z"),
      delivered_at: new Date("2024-01-11T14:40:00Z")
    }
  },
  {
    user_id: u4, restaurant_id: r5, status: "delivered", total: 360,
    items: [
      { name: "Veg Fried Rice",  quantity: 1, price: 160 },
      { name: "Chicken Noodles", quantity: 1, price: 200 }
    ],
    timestamps: {
      placed_at:    new Date("2024-01-12T15:00:00Z"),
      delivered_at: new Date("2024-01-12T15:35:00Z")
    }
  },
  {
    user_id: u5, restaurant_id: r5, status: "delivered", total: 660,
    items: [
      { name: "Chicken Noodles", quantity: 3, price: 200 },
      { name: "Green Tea",       quantity: 1, price: 60  }
    ],
    timestamps: {
      placed_at:    new Date("2024-01-13T16:00:00Z"),
      delivered_at: new Date("2024-01-13T16:42:00Z")
    }
  }

]);

print("✅ 20 orders inserted (Reference Pattern for user_id/restaurant_id; Embed for items[])");

// Verify counts
const rCount = db.restaurants.countDocuments();
const uCount = db.users.countDocuments();
const oCount = db.orders.countDocuments();
print(`\n📊 Document counts — restaurants: ${rCount}, users: ${uCount}, orders: ${oCount}`);

// ---------------------------------------------------------------------------
// STEP 4: AGGREGATION QUERIES
// ---------------------------------------------------------------------------

print("\n========================================");
print("STEP 4a: Query 1 — Total Revenue per Restaurant");
print("========================================");

const revenueResult = db.orders.aggregate([
  {
    $group: {
      _id: "$restaurant_id",
      total_revenue: { $sum: "$total" },
      order_count:   { $sum: 1 }
    }
  },
  {
    $lookup: {
      from:         "restaurants",
      localField:   "_id",
      foreignField: "_id",
      as:           "restaurant"
    }
  },
  { $unwind: "$restaurant" },
  {
    $project: {
      _id: 0,
      restaurant_name: "$restaurant.name",
      total_revenue: 1,
      order_count:   1
    }
  },
  { $sort: { total_revenue: -1 } }
]).toArray();

print("Results:");
revenueResult.forEach(doc => {
  print(`  ${doc.restaurant_name.padEnd(20)} | revenue: ₹${doc.total_revenue} | orders: ${doc.order_count}`);
});

// ---------------------------------------------------------------------------

print("\n========================================");
print("STEP 4b: Query 2 — Top 5 Most Ordered Items");
print("========================================");

const topItemsResult = db.orders.aggregate([
  { $unwind: "$items" },
  {
    $group: {
      _id:            "$items.name",
      total_quantity: { $sum: "$items.quantity" },
      times_ordered:  { $sum: 1 }
    }
  },
  { $sort: { total_quantity: -1 } },
  { $limit: 5 },
  {
    $project: {
      _id: 0,
      item_name:      "$_id",
      total_quantity: 1,
      times_ordered:  1
    }
  }
]).toArray();

print("Results:");
topItemsResult.forEach(doc => {
  print(`  ${doc.item_name.padEnd(25)} | qty: ${doc.total_quantity} | in ${doc.times_ordered} orders`);
});

// ---------------------------------------------------------------------------

print("\n========================================");
print("STEP 4c: Query 3 — Average Delivery Time (minutes)");
print("========================================");

const deliveryResult = db.orders.aggregate([
  {
    $match: {
      status: "delivered",
      "timestamps.delivered_at": { $ne: null }
    }
  },
  {
    $addFields: {
      delivery_mins: {
        $divide: [
          { $subtract: ["$timestamps.delivered_at", "$timestamps.placed_at"] },
          60000   // ms → minutes
        ]
      }
    }
  },
  {
    $group: {
      _id:              null,
      avg_delivery_mins: { $avg: "$delivery_mins" },
      min_delivery_mins: { $min: "$delivery_mins" },
      max_delivery_mins: { $max: "$delivery_mins" }
    }
  },
  {
    $project: {
      _id: 0,
      avg_delivery_mins: { $round: ["$avg_delivery_mins", 1] },
      min_delivery_mins: 1,
      max_delivery_mins: 1
    }
  }
]).toArray();

print("Results:");
deliveryResult.forEach(doc => {
  print(`  avg: ${doc.avg_delivery_mins} mins | min: ${doc.min_delivery_mins} mins | max: ${doc.max_delivery_mins} mins`);
});

// ---------------------------------------------------------------------------
// STEP 5: COMPUTED PATTERN — Seed total_orders & total_revenue into restaurants
// ---------------------------------------------------------------------------

print("\n========================================");
print("STEP 5: Computed Pattern — Seeding total_orders & total_revenue");
print("========================================");

// Bulk-seed from real order data (run once to sync; safe to re-run)
db.orders.aggregate([
  {
    $group: {
      _id:           "$restaurant_id",
      total_orders:  { $sum: 1 },
      total_revenue: { $sum: "$total" }
    }
  }
]).forEach(doc => {
  db.restaurants.updateOne(
    { _id: doc._id },
    {
      $set: {
        total_orders:  doc.total_orders,
        total_revenue: doc.total_revenue
      }
    }
  );
});

print("✅ Computed fields seeded. Verifying:");
db.restaurants.find({}, { name: 1, total_orders: 1, total_revenue: 1, _id: 0 }).forEach(r => {
  print(`  ${r.name.padEnd(20)} | orders: ${r.total_orders} | revenue: ₹${r.total_revenue}`);
});

// ---------------------------------------------------------------------------
// HOW TO USE $inc FOR REAL-TIME UPDATES (printed as instructional comment)
// ---------------------------------------------------------------------------
// When a NEW order is placed, call this immediately after inserting the order:
//
//   db.restaurants.updateOne(
//     { _id: restaurantId },
//     { $inc: { total_orders: 1, total_revenue: orderTotal } }
//   )
//
// $inc is atomic — safe under concurrent writes, requires no read-before-write.
// ---------------------------------------------------------------------------

// ---------------------------------------------------------------------------
// STEP 6: CREATE PRODUCTION INDEXES
// ---------------------------------------------------------------------------

print("\n========================================");
print("STEP 6: Creating Indexes");
print("========================================");

// Compound index: filter orders by restaurant + status in aggregations
db.orders.createIndex({ restaurant_id: 1, status: 1 });
print("✅ Index created: orders(restaurant_id, status)");

// Single-field index: fetch all orders for a user
db.orders.createIndex({ user_id: 1 });
print("✅ Index created: orders(user_id)");

// Descending index: "recent orders" queries
db.orders.createIndex({ "timestamps.placed_at": -1 });
print("✅ Index created: orders(timestamps.placed_at DESC)");

// Index: find restaurants by city
db.restaurants.createIndex({ "location.city": 1 });
print("✅ Index created: restaurants(location.city)");

// Verify all indexes
print("\nAll indexes on orders:");
db.orders.getIndexes().forEach(idx => print("  " + JSON.stringify(idx.key)));

print("\nAll indexes on restaurants:");
db.restaurants.getIndexes().forEach(idx => print("  " + JSON.stringify(idx.key)));

// ---------------------------------------------------------------------------
// STEP 7: FINAL SUMMARY VERIFICATION
// ---------------------------------------------------------------------------

print("\n========================================");
print("STEP 7: Final Verification Summary");
print("========================================");

print(`\n📦 restaurants : ${db.restaurants.countDocuments()} documents`);
print(`👤 users        : ${db.users.countDocuments()} documents`);
print(`🛒 orders       : ${db.orders.countDocuments()} documents`);

// Verify polymorphic pattern — both category types in menu
const foodCount = db.restaurants.aggregate([
  { $unwind: "$menu" },
  { $match: { "menu.category": "food" } },
  { $count: "food_items" }
]).toArray();

const bevCount = db.restaurants.aggregate([
  { $unwind: "$menu" },
  { $match: { "menu.category": "beverage" } },
  { $count: "beverage_items" }
]).toArray();

print(`\n🍽️  Polymorphic menu items:`);
print(`   Food items:     ${foodCount[0]?.food_items ?? 0}`);
print(`   Beverage items: ${bevCount[0]?.beverage_items ?? 0}`);

// Verify statuses
const statuses = db.orders.distinct("status");
print(`\n📋 Order statuses present: ${statuses.join(", ")}`);

// Verify references are valid (every order's restaurant_id resolves)
const unresolvedRefs = db.orders.aggregate([
  {
    $lookup: {
      from:         "restaurants",
      localField:   "restaurant_id",
      foreignField: "_id",
      as:           "rest"
    }
  },
  { $match: { rest: { $size: 0 } } },
  { $count: "broken_refs" }
]).toArray();

print(`\n🔗 Broken restaurant references: ${unresolvedRefs[0]?.broken_refs ?? 0} (should be 0)`);

print("\n========================================");
print("✅  ALL STEPS COMPLETE — Food Delivery MongoDB data layer is ready.");
print("========================================\n");
