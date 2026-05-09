#  FoodRush — Food Delivery Platform

A production-style, full-stack food delivery web application built with **PHP 8.1**, **MongoDB**, and **Redis** — inspired by Zomato / Swiggy.

> **Redis is optional.** The app runs fully without it — orders and sessions fall back to MongoDB/PHP sessions automatically.

---

##  Table of Contents

- [Tech Stack](#-tech-stack)
- [Prerequisites](#-prerequisites)
- [Installation](#-installation)
- [Running the App](#-running-the-app)
- [Test Credentials](#-test-credentials)
- [Project Structure](#-project-structure)
- [API Reference](#-api-reference)
- [MongoDB Schema](#-mongodb-schema)
- [Redis Key Schema](#-redis-key-schema)
- [Order Lifecycle](#-order-lifecycle)
- [Features](#-features)
- [Troubleshooting](#-troubleshooting)

---

##  Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend | PHP 8.1 (no framework) |
| Database | MongoDB 6+ |
| Cache / Queue | Redis 7+ *(optional)* |
| PHP MongoDB lib | `mongodb/mongodb ^1.16` |
| PHP Redis client | `predis/predis ^2.2` |
| Frontend | Vanilla HTML + CSS + JavaScript |
| Web Server | Apache (XAMPP) |

---

##  Prerequisites

Install these before continuing:

| Tool | Download | Notes |
|------|----------|-------|
| **XAMPP** | [apachefriends.org](https://www.apachefriends.org/) | Provides Apache + PHP 8.1 |
| **MongoDB Community** | [mongodb.com/try/download](https://www.mongodb.com/try/download/community) | Version 6.0 or newer |
| **MongoDB PHP Extension** | [pecl.php.net/package/mongodb](https://pecl.php.net/package/mongodb) | Enable in `php.ini` |
| **Composer** | [getcomposer.org](https://getcomposer.org/) | PHP dependency manager |
| **Redis** *(optional)* | [github.com/microsoftarchive/redis](https://github.com/microsoftarchive/redis/releases) | Windows port — or use WSL |

---

##  Installation

### Step 1 — Clone / Place the project

Put the project folder inside XAMPP's web root:

```
C:\xampp\htdocs\food_tracking_system\
```

### Step 2 — Enable the MongoDB PHP Extension

1. Download `php_mongodb.dll` matching your PHP version from [PECL](https://pecl.php.net/package/mongodb)  
2. Copy it to `C:\xampp\php\ext\`
3. Open `C:\xampp\php\php.ini` and add:
   ```ini
   extension=mongodb
   ```
4. **Restart Apache** from the XAMPP Control Panel

Verify the extension is loaded:
```powershell
php -m | findstr mongodb
# Should output: mongodb
```

### Step 3 — Install PHP Dependencies

Open a terminal in the project root and run:

```powershell
cd C:\xampp\htdocs\food_tracking_system
php composer.phar install
```

Or if you have Composer installed globally:

```powershell
composer install
```

This installs:
- `mongodb/mongodb` — MongoDB PHP library
- `predis/predis` — Redis PHP client (used automatically if Redis is running)

### Step 4 — Start Services

**MongoDB:**
```powershell
# If installed as a service, it starts automatically.
# Or start manually:
mongod --dbpath "C:\data\db"
```

**Apache:**  
Open the **XAMPP Control Panel** and click **Start** next to Apache.

**Redis** *(optional — app works without it)*:
```powershell
redis-server
```

### Step 5 — Seed the Database

Run the seeder to populate MongoDB with sample restaurants, users, and orders:

```powershell
cd C:\xampp\htdocs\food_tracking_system
php seed.php
```

Expected output:
```
 Dropped existing collections
 Created 3 users
 Created 5 restaurants with menu items
 Created sample orders
 Seeding complete!
```

### Step 6 — Open the App

Visit in your browser:

```
http://localhost/food_tracking_system/public/
```

---

##  Test Credentials

After running `seed.php`:

| Role | Email | Password | Login URL |
|------|-------|----------|-----------|
| **User** | `arjun@example.com` | `user123` | `/public/login.php` |
| **Restaurant** | `spice@example.com` | `password123` | `/public/login.php` (Restaurant tab) |
| **Admin** | `admin@foodrush.com` | `admin123` | `/public/login.php` (Admin tab) |

---

##  Project Structure

```
food_tracking_system/
├── config/
│   ├── config.php              # App constants (DB host, Redis, TTLs, status codes)
│   ├── database.php            # MongoDB singleton connection
│   └── redis.php               # Redis singleton with auto-failover
│
├── models/
│   ├── UserModel.php           # Users + embedded addresses + favorites
│   ├── RestaurantModel.php     # Restaurants + polymorphic menu items
│   ├── OrderModel.php          # Orders + aggregation pipelines
│   └── ReviewModel.php         # Ratings & reviews
│
├── controllers/
│   ├── AuthController.php      # Registration & login (user / restaurant / admin)
│   ├── SessionController.php   # Redis-backed sessions (PHP session fallback)
│   ├── OrderController.php     # Full order lifecycle + Redis queue + rate limiting
│   └── RestaurantController.php# Heartbeat, menu CRUD, online status
│
├── api/
│   └── router.php              # REST API dispatcher (all /api/* routes)
│
├── public/
│   ├── index.php               # Home — restaurant listing + filters
│   ├── restaurant.php          # Restaurant detail + menu + reviews
│   ├── checkout.php            # Checkout + address selection
│   ├── track.php               # Real-time order status tracker
│   ├── orders.php              # Order history (user)
│   ├── profile.php             # User profile + address book
│   ├── login.php               # Unified login (user / restaurant / admin tabs)
│   ├── register.php            # User registration
│   ├── restaurant_register.php # Restaurant registration
│   ├── restaurant_dashboard.php# Restaurant owner panel (orders + menu + cover photo)
│   ├── admin.php               # Admin dashboard + analytics
│   ├── logout.php              # Destroy session
│   ├── api.php                 # API entry point (delegates to router.php)
│   ├── uploads/                # Uploaded restaurant & menu item images
│   └── assets/
│       ├── css/style.css       # Full design system (dark theme, glassmorphism)
│       └── js/app.js           # Cart state, API helpers, toasts, utilities
│
├── views/
│   └── partials/
│       ├── header.php          # Navbar + cart sidebar
│       └── footer.php          # Scripts + closing tags
│
├── seed.php                    # Sample data seeder
├── composer.json               # PHP dependencies
└── README.md                   # This file
```

---

##  API Reference

All requests go to:
```
http://localhost/food_tracking_system/public/api.php?route=<endpoint>
```

### Auth

| Method | Route | Description |
|--------|-------|-------------|
| `POST` | `auth/register` | Register a new user |
| `POST` | `auth/login` | User login |
| `POST` | `auth/restaurant-register` | Register a new restaurant |
| `POST` | `auth/restaurant-login` | Restaurant login |
| `POST` | `auth/admin-login` | Admin login |
| `POST` | `auth/logout` | Destroy session |

### Restaurants

| Method | Route | Description |
|--------|-------|-------------|
| `GET` | `restaurant` | List all restaurants (supports `?search=`, `?cuisine=`, `?is_veg=`, `?sort=`) |
| `GET` | `restaurant/{id}` | Get restaurant by ID |
| `PATCH` | `restaurant/{id}` | Update restaurant info |
| `GET` | `restaurant/{id}/menu` | Get menu grouped by type |
| `POST` | `restaurant/{id}/menu` | Add a menu item |
| `PATCH` | `restaurant/{id}/menu/{item_id}` | Edit a menu item |
| `DELETE` | `restaurant/{id}/menu/{item_id}` | Delete a menu item |
| `POST` | `restaurant/{id}/upload-image` | Upload restaurant/item image *(multipart)* |
| `POST` | `restaurant/{id}/heartbeat` | Mark restaurant as online (TTL 5 min) |
| `GET` | `restaurant/{id}/online` | Check if restaurant is online |

### Orders

| Method | Route | Description |
|--------|-------|-------------|
| `POST` | `order/place` | Place a new order |
| `GET` | `order/my` | Get logged-in user's orders |
| `GET` | `order/{id}/status` | Get order status (Redis → MongoDB fallback) |
| `PATCH` | `order/{id}/status` | Update order status |
| `GET` | `order/restaurant/{id}` | Get all orders for a restaurant |
| `GET` | `order/queue` | View the delivery queue |
| `POST` | `order/assign` | Assign next order from FIFO queue |

### Users

| Method | Route | Description |
|--------|-------|-------------|
| `GET` | `user/profile` | Get current user profile |
| `PATCH` | `user/profile` | Update profile |
| `POST` | `user/address` | Add delivery address |
| `DELETE` | `user/address/{id}` | Remove an address |
| `POST` | `user/favorite/{restaurant_id}` | Add favourite |
| `DELETE` | `user/favorite/{restaurant_id}` | Remove favourite |

### Reviews & Dashboard

| Method | Route | Description |
|--------|-------|-------------|
| `POST` | `review` | Submit a review |
| `GET` | `review/{restaurant_id}` | Get reviews for a restaurant |
| `GET` | `dashboard/analytics` | Full analytics (admin only) |

---

## ️ MongoDB Schema

### `restaurants` collection

```js
{
  _id:           ObjectId,
  name:          String,
  email:         String,          // unique
  password:      String,          // bcrypt hashed
  phone:         String,
  address:       String,
  city:          String,
  cuisine:       [String],        // e.g. ["Indian", "Chinese"]
  description:   String,
  image:         String,          // filename in /public/uploads/
  is_veg:        Boolean,
  opens_at:      String,          // "10:00"
  closes_at:     String,          // "23:00"
  delivery_fee:  Number,
  min_order:     Number,

  // Polymorphic embedded menu items
  menu_items: [
    // Vegetarian:
    { _id, name, price, type:"veg", is_jain:Boolean, is_available, image_url }
    // Non-vegetarian:
    { _id, name, price, type:"non_veg", spice_level:"low|medium|high", is_available, image_url }
    // Beverage:
    { _id, name, price, type:"beverage", serving_size_ml:Number, is_available, image_url }
  ],

  // Computed fields (updated with $inc — no re-aggregation needed)
  total_orders:  Number,
  total_revenue: Number,
  avg_rating:    Number,
  review_count:  Number,
  rating_sum:    Number,

  is_active:     Boolean,
  created_at:    Date,
  updated_at:    Date
}
```

### `orders` collection

```js
{
  _id:              ObjectId,
  user_id:          String,
  restaurant_id:    String,
  items: [
    { item_id, name, price, quantity, type }
  ],
  total_price:      Number,
  delivery_address: { street, city, pincode, landmark },
  status:           "placed|accepted|preparing|out_for_delivery|delivered|cancelled",
  notes:            String,
  eta_minutes:      Number,
  placed_at:        Date,
  accepted_at:      Date | null,
  preparing_at:     Date | null,
  out_for_delivery_at: Date | null,
  delivered_at:     Date | null,
  cancelled_at:     Date | null
}
```

### `users` collection

```js
{
  _id:       ObjectId,
  name:      String,
  email:     String,          // unique
  password:  String,          // bcrypt hashed
  phone:     String,
  role:      "user",
  addresses: [
    { _id, label, street, city, pincode, landmark }
  ],
  favorites: [String],        // restaurant IDs
  created_at: Date
}
```

### `reviews` collection

```js
{
  _id:           ObjectId,
  restaurant_id: String,
  user_id:       String,
  user_name:     String,
  rating:        Number,      // 1–5
  comment:       String,
  created_at:    Date
}
```

---

##  Redis Key Schema

> Redis is **optional**. If it's not running, the app falls back gracefully.

| Key Pattern | Type | TTL | Purpose |
|-------------|------|-----|---------|
| `session:{token}` | String (JSON) | 24 h | User session data |
| `order:{id}` | Hash | 24 h | Real-time order tracking fields |
| `delivery:queue` | List | — | FIFO delivery queue (`RPUSH` / `LPOP`) |
| `restaurant:{id}:online` | String | 5 min | Restaurant heartbeat / online status |
| `rate:order:{user_id}` | String | 1 h | Order rate limiting (max 5/hour) |

---

##  Order Lifecycle

```
placed ──► accepted ──► preparing ──► out_for_delivery ──► delivered
                                                        └──► cancelled
```

**On order placed:**
1. Saved to MongoDB with status `placed`
2. Cached in Redis hash `order:{id}` for fast status reads
3. Order ID pushed to `delivery:queue` (Redis List)

**On delivered:**
1. Redis key `order:{id}` is deleted
2. MongoDB status updated to `delivered`
3. Restaurant's `total_orders` and `total_revenue` incremented via `$inc`

---

##  Aggregation Queries

| Query | Method | Description |
|-------|--------|-------------|
| Revenue per restaurant | `RestaurantModel::aggregateRevenuePerRestaurant()` | Projects stored computed fields, sorted by revenue |
| Most ordered items | `OrderModel::aggregateMostOrderedItem()` | `$unwind` items → `$group` by item → top 10 |
| Avg delivery time | `OrderModel::aggregateAvgDeliveryTime()` | `placed_at` → `delivered_at` delta, in minutes |
| Orders by status | `OrderModel::countByStatus()` | `$group` by status field |

---

##  Features

| Feature | Details |
|---------|---------|
|  Dark UI | Inter font, glassmorphism cards, gradient accents |
|  Cart | Persistent `localStorage` cart with quantity controls |
|  Orders | Real-time status tracker with 10-second polling |
|  Restaurant Dashboard | Cover photo upload, menu CRUD with item images, order management |
|  Image Upload | Drag-and-drop or click-to-upload (JPG/PNG/WebP/GIF, max 5 MB) |
|  Auth | Three separate login types: User, Restaurant, Admin |
|  Reviews | Rating + comment system with running average |
|  Admin Panel | Analytics: revenue, top items, delivery times, user counts |
|  Rate Limiting | Max 5 orders per user per hour (Redis-backed) |
| ️ Failover | Full graceful degradation when Redis is unavailable |
|  Responsive | Mobile-friendly layout |

---

##  Troubleshooting

### "Call to undefined function MongoDB\Driver\..."
The `mongodb` PHP extension is not loaded.  
→ Check `php.ini` has `extension=mongodb` and restart Apache.

### Blank page or 500 error
→ Check the Apache error log at `C:\xampp\apache\logs\error.log`  
→ Make sure MongoDB is running: `mongod --dbpath "C:\data\db"`

### Orders page loads slowly / spins forever
Redis is not running — this is fine, the app falls back automatically. The first request may take ~0.5 s while it determines Redis is unavailable; subsequent requests are instant.

### "No restaurants found" after seeding
→ Confirm the seeder ran without errors: `php seed.php`  
→ Check MongoDB is accessible: `mongosh` → `use food_delivery` → `db.restaurants.countDocuments()`

### Image uploads not working
→ Ensure `public/uploads/` directory exists and is writable:
```powershell
# The folder already exists with a .gitkeep file.
# If permissions are wrong on Linux/Mac:
chmod 775 public/uploads/
```

### Composer not found
```powershell
# Use the bundled composer.phar instead:
php composer.phar install
```

---

##  License

MIT — free to use for academic and personal projects.
