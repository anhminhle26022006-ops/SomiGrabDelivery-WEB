# 🇻🇳 LocalTaste Web

### 🌸 **Three Belles**

#### *Three girls, one taste.*

> **Discover the place. Meet the maker. Taste the story.**

**LocalTaste** is an e-commerce platform dedicated to authentic Vietnamese local specialties, connecting customers with regional products, local sellers, and the stories behind every product.

From traditional foods and regional specialties to unique local products, LocalTaste makes it easier to **discover, explore, and shop the authentic taste of Vietnam** — all in one place.

---

## 🌾 About LocalTaste

Vietnam is a country rich in regional flavors, traditional products, local craftsmanship, and cultural heritage. Every region has its own unique specialties, yet many local products remain difficult to discover and access beyond their hometowns.

**LocalTaste was created to bring those products closer to everyone.**

Our platform creates a digital marketplace where customers can discover products by **category, region, seller, and story**, while local sellers gain an opportunity to introduce their products and reach a wider audience.

### 🍵 Discover Local Flavors

Explore authentic specialties and unique products from different regions of Vietnam.

### 📍 Explore Their Origins

Discover where products come from and learn more about the places and traditions behind them.

### 🏪 Meet Local Sellers

Connect products with the local businesses, producers, and sellers who create and bring them to customers.

### ❤️ Share the Experience

Leave reviews, save favorite products, and share your discoveries with the community.

> **LocalTaste is more than a place to shop.**
> **It is a place to discover the taste, culture, and stories of Vietnam. 🇻🇳**

---

# ✨ Why LocalTaste?

### More Than Just an Online Store

LocalTaste is designed not only as an e-commerce website, but as a **digital marketplace for Vietnamese local products**.

Every product has a story:

```text
        📍 WHERE
           │
       Where is it from?
           ↓
        🏪 WHO
           │
       Who makes it?
           ↓
        📖 WHY
           │
       Why is it special?
           ↓
        ❤️ TASTE
           │
       What makes it unique?
```

Instead of simply asking:

> *"What should I buy?"*

LocalTaste encourages customers to discover:

> **"Where does it come from, who makes it, and what makes it special?"**

Our goal is to make local products easier to **discover, access, appreciate, and share** — while creating new opportunities for local sellers.

---

# 🚀 Core Features

## 👤 Customer Experience

### 🔐 Account & Authentication

* Create an account
* Login / Logout
* Manage personal profile
* Update account information
* Manage password

### 🔎 Product Discovery

* Browse product catalog
* Search products
* Filter by category
* Filter by region
* Filter by price
* Sort by price and rating
* View detailed product information
* Explore product origin and seller information

### 🛒 Shopping

* Add products to cart
* Update product quantity
* Remove products from cart
* Save products to wishlist
* Checkout
* Confirm orders

### 📦 Order Management

* View order history
* View order details
* Track order status
* Monitor purchasing activity

### ⭐ Reviews & Community

* Rate products
* Write product reviews
* Read customer reviews
* Share shopping experiences

---

# 🏪 Seller Center

LocalTaste is designed with the potential to evolve into a **multi-vendor marketplace**, allowing local businesses and sellers to manage and promote their own products.

### Seller Features

* Create and manage seller profiles
* Add new products
* Edit product information
* Remove products
* Manage product inventory
* Manage incoming orders
* View customer reviews
* Monitor product performance
* Track sales

The multi-vendor architecture provides a foundation for expanding LocalTaste from a single-store e-commerce website into a platform where **multiple local sellers can build and manage their own stores**.

---

# 👑 Admin Dashboard

The Admin Dashboard provides centralized control over the entire platform.

### 📂 Platform Management

* Manage users
* Manage sellers
* Manage categories
* Manage products
* Manage orders
* Manage reviews

### 📊 Dashboard & Analytics

Administrators can monitor important platform information such as:

* Total users
* Total sellers
* Total products
* Total orders
* Revenue
* Best-selling products
* Product categories

The administration system provides the foundation for maintaining product quality, managing platform activity, and monitoring overall business performance.

---

# ⚡ AJAX & JSON

To provide a smoother and more responsive shopping experience, LocalTaste integrates **AJAX** for selected interactions without requiring unnecessary page reloads.

### Example Request Flow

```text
┌──────────────┐
│    Client    │
│ HTML / CSS   │
│ JavaScript   │
└──────┬───────┘
       │
       │ AJAX Request
       ↓
┌──────────────┐
│  Controller  │
└──────┬───────┘
       │
       ↓
┌──────────────┐
│    Model     │
└──────┬───────┘
       │
       ↓
┌──────────────┐
│   Database   │
└──────┬───────┘
       │
       │ JSON Response
       ↓
┌──────────────┐
│  Update UI   │
└──────────────┘
```

### AJAX Use Cases

* 🔎 Product search
* 🏷️ Product filtering
* 🛒 Add to cart
* 🔢 Update cart quantity
* ❤️ Wishlist
* ⭐ Product reviews
* 💬 Comments

Data exchanged between the client and server is structured using **JSON** where appropriate, providing lightweight and organized communication between the frontend and backend.

---

# 🏗️ System Architecture

LocalTaste is developed using **Object-Oriented Programming (OOP)** and follows the **Model–View–Controller (MVC)** architectural pattern.

```text
                         LOCAL TASTE
                              │
                              ↓
                    ┌──────────────────┐
                    │      CLIENT      │
                    │ HTML / CSS / JS  │
                    │    Bootstrap     │
                    └────────┬─────────┘
                             │
                        AJAX / JSON
                             │
                             ↓
                    ┌──────────────────┐
                    │   CONTROLLER     │
                    │       MVC        │
                    └────────┬─────────┘
                             │
                             ↓
                    ┌──────────────────┐
                    │      MODEL       │
                    │       OOP        │
                    └────────┬─────────┘
                             │
                             ↓
                    ┌──────────────────┐
                    │     MySQL DB     │
                    └──────────────────┘
```

The architecture separates the **presentation layer, application logic, and data access layer**, making the system easier to maintain, test, and extend.

This structure also provides a foundation for future features such as multi-vendor management, payment integration, recommendation systems, and mobile applications.

---

# 🛠️ Technology Stack

## 🎨 Frontend

* HTML5
* CSS3
* JavaScript
* Bootstrap 5
* AJAX
* JSON

## ⚙️ Backend

* PHP
* Object-Oriented Programming
* MVC Architecture

## 🗄️ Database

* MySQL

## 🔧 Development Tools

* Visual Studio Code
* XAMPP
* Git
* GitHub

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

> The project structure may evolve during development as new modules, features, and services are introduced.

---

# 🗄️ Data Model

The core system is organized around several key entities.

```text
                         User
                          │
             ┌────────────┼────────────┐
             │            │            │
             ↓            ↓            ↓
           Order       Review      Wishlist
             │            │            │
             ↓            ↓            ↓
       OrderDetail     Product ←────────┘
                          │
                          ↓
                       Category

                        Seller
                          │
                          ↓
                       Product
```

### Core Entities

| Entity          | Purpose                                        |
| --------------- | ---------------------------------------------- |
| **User**        | Customer account and personal information      |
| **Seller**      | Local business or product provider             |
| **Product**     | Vietnamese local specialty or regional product |
| **Category**    | Product classification                         |
| **Order**       | Customer purchase                              |
| **OrderDetail** | Products contained in an order                 |
| **Review**      | Customer feedback and ratings                  |
| **Wishlist**    | Products saved by customers                    |

This data model provides a foundation for future marketplace functionality, including multiple sellers, product management, customer engagement, and business analytics.

---

# 🌟 Future Roadmap

LocalTaste is designed with **scalability and real-world expansion** in mind.

## Phase 1 — E-Commerce Foundation

* [x] Product catalog
* [x] Categories
* [x] User authentication
* [x] Shopping cart
* [x] Order management
* [x] Basic administration

## Phase 2 — Better Shopping Experience

* [ ] Advanced search
* [ ] Wishlist
* [ ] Product reviews
* [ ] Seller profiles
* [ ] Promotional campaigns
* [ ] Discount vouchers
* [ ] Flash sales

## Phase 3 — Multi-Vendor Marketplace

* [ ] Multiple independent sellers
* [ ] Seller dashboard
* [ ] Seller verification
* [ ] Seller analytics
* [ ] Seller order management
* [ ] Seller storefronts

## Phase 4 — Smart LocalTaste

* [ ] Personalized product recommendations
* [ ] AI-powered product search
* [ ] Intelligent product suggestions
* [ ] Customer–seller chat
* [ ] Real-time notifications
* [ ] Personalized shopping experience

## Phase 5 — Real-World Integration

* [ ] Online payment
* [ ] Shipping service integration
* [ ] Location and map services
* [ ] Mobile application
* [ ] Production deployment
* [ ] External service integration

---

# 📱 Web & Mobile Ecosystem

LocalTaste is designed as a multi-platform ecosystem rather than being limited to a single web application.

```text
                         LOCAL TASTE
                              │
                 ┌────────────┴────────────┐
                 │                         │
                WEB                     MOBILE
                 │                         │
         Online Shopping          Shopping On-the-Go
                 │                         │
                 └────────────┬────────────┘
                              │
                           DATABASE
                              │
                     ┌────────┴────────┐
                     │                 │
                Local Sellers    Local Products
                     │                 │
                     └────────┬────────┘
                              ↓
                  Authentic Vietnamese
                       Local Taste 🇻🇳
```

### Related Repository

📱 **LocalTaste Mobile — Three Belles**

The mobile application extends the LocalTaste experience to smartphones, allowing customers to discover, explore, and shop for Vietnamese local products anytime and anywhere.

---

# 🌐 Deployment

LocalTaste is designed to be deployable on a shared hosting environment for real-world accessibility and demonstration.

### Deployment Objectives

* Web hosting
* MySQL database
* Public website access
* Production configuration
* Database deployment
* Environment configuration

> 🚀 **From a university project to a potential real-world marketplace.**

---

# 🔀 Git Workflow

To maintain a clean and collaborative development process, **Three Belles** uses GitHub with feature branches.

```text
main
 │
 └── develop
       │
       ├── feature/authentication
       ├── feature/products
       ├── feature/categories
       ├── feature/cart
       ├── feature/orders
       ├── feature/admin
       └── feature/ajax
```

Each feature is developed independently before being reviewed and merged into the development branch.

This workflow helps the team:

* Keep development organized
* Work on features independently
* Reduce code conflicts
* Track individual contributions
* Maintain a clear development history

---

# 👩‍💻 Team — Three Belles

### 🔔 **Three Belles**

#### *Three girls, one taste.*

Three girls.
Three perspectives.
Three different ideas.
**One shared vision.**

We believe technology can do more than create convenient shopping experiences.

It can also help people **discover local products, support local sellers, and connect with the stories and traditions behind Vietnamese specialties.**

LocalTaste is our attempt to turn that idea into a real digital experience.

> **Three minds. One vision. One LocalTaste. 🇻🇳**

---

# 📚 Academic Project

**Course:** Web Application Development
**Project Type:** E-Commerce Website
**Topic:** Vietnamese Local Specialty Marketplace
**Team:** Three Belles
**Project:** LocalTaste

---

# 💚 Our Vision

> ### **Local products deserve a local story.**

We envision LocalTaste as more than an e-commerce platform.

We want it to become a place where people can:

**Discover** unique Vietnamese products.
**Connect** with local sellers.
**Explore** the stories behind every product.
**Support** local businesses.
**Share** the taste of Vietnam.

From a university project to a platform with real-world potential, LocalTaste is our first step toward making Vietnamese local products **easier to discover, easier to access, and easier to share with the world.**

---

## 🇻🇳 LocalTaste × Three Belles

> **Discover the place.**
> **Meet the maker.**
> **Taste the story.**

### 🔔 **Three Belles**

### *Three girls, one taste.*

**LocalTaste — Bringing Vietnam's local taste closer to you. 🇻🇳**
