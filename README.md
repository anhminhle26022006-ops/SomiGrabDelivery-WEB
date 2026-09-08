# 🇻🇳 LocalTaste

### *Three girls, one taste.*

**LocalTaste** is a modern e-commerce platform that brings authentic Vietnamese local specialties closer to customers — connecting **local sellers, regional products, and the stories behind them** in one place.

> 🍵 Discover local flavors.
> 🧺 Support local sellers.
> ❤️ Share the taste of Vietnam.

---

## 🌾 About LocalTaste

Vietnam is home to thousands of unique specialties, traditional products, and local brands. However, many of these products are still difficult to discover and access beyond their local regions.

**LocalTaste** was created to bridge that gap.

Our platform allows customers to:

* 🔎 Discover authentic Vietnamese specialties
* 🛍️ Browse and purchase local products
* 📍 Explore products by region
* ⭐ Read and leave product reviews
* ❤️ Save favorite products
* 🛒 Manage their shopping cart
* 📦 Place and track orders

At the same time, local sellers can introduce their products and reach more customers through an online marketplace.

---

## ✨ What Makes LocalTaste Different?

### 🇻🇳 More Than Just an Online Store

LocalTaste is not simply a website for selling products.

It is designed as a **digital marketplace for Vietnamese local products**, where every product can have its own:

* 📍 Origin
* 🏪 Local seller
* 📖 Story
* ⭐ Customer reviews
* 🛒 Shopping experience

Instead of asking:

> *"What should I buy?"*

LocalTaste helps customers discover:

> *"Where does it come from, who makes it, and why is it special?"*

---

# 🚀 Key Features

## 👤 Customer

### 🔐 Authentication

* Register
* Login / Logout
* User profile
* Password management

### 🔎 Product Discovery

* Browse products
* Search products
* Filter by category
* Filter by region
* Filter by price
* Sort by price / rating
* View product details

### 🛒 Shopping

* Add products to cart
* Update quantity
* Remove products
* Wishlist
* Checkout
* Order confirmation

### 📦 Order Management

* View order history
* Track order status
* View order details

### ⭐ Community

* Rate products
* Write reviews
* View customer reviews

---

# 🏪 Seller

LocalTaste can be extended into a **multi-vendor marketplace**, allowing local sellers to manage their own stores.

Sellers can:

* Create a seller profile
* Add products
* Update product information
* Manage inventory
* Manage orders
* View customer reviews
* Monitor sales performance

---

# 👑 Admin Dashboard

The administration system provides centralized management of the platform.

### 📂 Management

* Manage users
* Manage sellers
* Manage categories
* Manage products
* Manage orders
* Manage reviews

### 📊 Dashboard

Administrators can monitor:

* Total users
* Total products
* Total orders
* Revenue
* Best-selling products
* Product categories

---

# ⚡ AJAX & JSON

LocalTaste uses **AJAX** to create a smoother shopping experience without unnecessary page reloads.

Examples include:

```text
Search
   ↓
AJAX Request
   ↓
Controller
   ↓
Model
   ↓
Database
   ↓
JSON Response
   ↓
Update UI
```

AJAX can be used for:

* 🔎 Product search
* 🏷️ Product filtering
* 🛒 Add to cart
* 🔢 Update cart quantity
* ❤️ Wishlist
* ⭐ Reviews
* 💬 Comments

Data exchanged between frontend and backend is structured using **JSON** where appropriate.

---

# 🏗️ System Architecture

LocalTaste is designed using **Object-Oriented Programming** and follows the **MVC architecture**.

```text
                 ┌──────────────────┐
                 │      CLIENT      │
                 │ HTML / CSS / JS  │
                 │    Bootstrap     │
                 └────────┬─────────┘
                          │
                         AJAX
                          │
                         JSON
                          ↓
                 ┌──────────────────┐
                 │   CONTROLLER     │
                 │      (MVC)       │
                 └────────┬─────────┘
                          │
                          ↓
                 ┌──────────────────┐
                 │      MODEL       │
                 │      OOP         │
                 └────────┬─────────┘
                          │
                          ↓
                 ┌──────────────────┐
                 │     DATABASE     │
                 │      MySQL       │
                 └──────────────────┘
```

---

# 🛠️ Technology Stack

### Frontend

* HTML5
* CSS3
* JavaScript
* Bootstrap 5
* AJAX
* JSON

### Backend

* PHP
* Object-Oriented Programming
* MVC Architecture

### Database

* MySQL

### Development Tools

* Visual Studio Code
* Git
* GitHub
* XAMPP

---

# 📁 Project Structure

```text
LocalTaste-Web-ThreeBelles/
│
├── app/
│   ├── controllers/
│   ├── models/
│   ├── views/
│   └── services/
│
├── config/
│   └── database.php
│
├── public/
│   ├── css/
│   ├── js/
│   ├── images/
│   └── index.php
│
├── routes/
│
├── database/
│   └── localtaste.sql
│
├── .gitignore
├── README.md
└── index.php
```

---

# 🗄️ Main Data Model

The system can be organized around the following entities:

```text
User
 │
 ├── Order
 │     └── OrderDetail
 │            └── Product
 │
 ├── Review
 │       └── Product
 │
 └── Wishlist
         └── Product

Seller
 │
 └── Product
       │
       └── Category
```

This structure allows LocalTaste to grow from a simple e-commerce website into a more complete marketplace platform.

---

# 👩‍💻 Team — ThreeBelles

### 🌸 Three girls, one taste.

**ThreeBelles** is the team behind LocalTaste.

We believe that technology can do more than create convenient shopping experiences — it can also help people discover and support the local products, traditions, and stories that make Vietnam unique.

> **Three minds. One vision. One LocalTaste. 🇻🇳**

---

# 🌟 Future Roadmap

LocalTaste is designed with future expansion in mind.

### Phase 1 — E-commerce Foundation

* [x] Product catalog
* [x] Categories
* [x] User authentication
* [x] Shopping cart
* [x] Orders
* [x] Admin management

### Phase 2 — Better Shopping Experience

* [ ] Advanced search
* [ ] Wishlist
* [ ] Product reviews
* [ ] Seller profiles
* [ ] Promotions & vouchers
* [ ] Flash sales

### Phase 3 — Marketplace

* [ ] Multi-vendor system
* [ ] Seller dashboard
* [ ] Seller verification
* [ ] Seller analytics

### Phase 4 — Smart LocalTaste

* [ ] Personalized product recommendations
* [ ] AI-powered search
* [ ] Smart product suggestions
* [ ] Chat between customers and sellers
* [ ] Real-time notifications

### Phase 5 — Real-world Integration

* [ ] Online payment
* [ ] Shipping integration
* [ ] Location & map services
* [ ] Mobile application
* [ ] Production deployment

---

# 📱 Web & Mobile Ecosystem

LocalTaste is not limited to a single platform.

```text
                    LOCAL TASTE
                         │
             ┌───────────┴───────────┐
             │                       │
            WEB                   MOBILE
             │                       │
      Online Shopping        Shopping On-the-Go
             │                       │
             └───────────┬───────────┘
                         │
                      DATABASE
                         │
                   Local Sellers
                         │
                  Vietnamese Products
```

### Related Repository

📱 **LocalTaste Mobile — ThreeBelles**

The mobile application extends the LocalTaste experience to smartphones, allowing customers to discover and purchase local products anywhere.

---

# 🌐 Deployment

The project is designed to be deployable on a shared hosting environment.

Deployment objectives include:

* Web hosting
* MySQL database
* Public website access
* Production configuration

> 🚀 From a university project to a potential real-world marketplace.

---

# 🔀 Git Workflow

To maintain a clean and collaborative development process, the team uses GitHub with feature branches.

```text
main
 │
 └── develop
       │
       ├── feature/authentication
       ├── feature/products
       ├── feature/cart
       ├── feature/orders
       ├── feature/admin
       └── feature/ajax
```

Each feature is developed independently before being merged into the main development branch.

---

# 📚 Academic Project

**Course:** Web Application Development
**Project Type:** E-Commerce Website
**Topic:** Vietnamese Local Specialty Marketplace
**Team:** ThreeBelles
**Project:** LocalTaste

---

## 💚 Our Vision

> **Local products deserve a local story.**

LocalTaste aims to make Vietnamese specialties easier to discover, easier to access, and easier to share with the world.

**Discover the place.
Meet the maker.
Taste the story.**

### 🇻🇳 LocalTaste — Bringing Vietnam's local taste closer to you.
