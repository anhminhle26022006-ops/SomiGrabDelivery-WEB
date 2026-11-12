# Somi Grab Delivery

> Web-based delivery platform connecting customers with part-time shippers.

## 1. Overview

Somi Grab Delivery allows customers to create delivery orders, estimate fees, make payments, and track orders. Part-time shippers can view available orders, accept jobs, update delivery status, and manage delivery history.

Academic project using PHP, an MVC-style structure, HTML/CSS, JavaScript, AJAX/JSON, PostgreSQL on Supabase, and external APIs.

## 2. Main Features

### Customer

- Register / Login
- Manage profile
- Create delivery order
- Calculate distance and delivery fee
- Make payment
- Track order
- View order history
- Rate and review shipper

### Shipper

- Register / Login
- Manage profile
- Set availability
- View available orders
- Accept orders
- Confirm pickup
- Update delivery status
- Confirm delivery
- View history and earnings

### Admin

- Manage customers and shippers
- Manage orders and payments
- Manage reviews
- Manage delivery areas and fees
- Handle delivery issues

## 3. Delivery Flow

```text
Customer
    ↓
Create Order
    ↓
Calculate Distance & Fee
    ↓
Payment
    ↓
Find Shipper
    ↓
Shipper Accepts
    ↓
Pick Up
    ↓
In Transit
    ↓
Delivered
    ↓
Review
```

## 4. Technologies

| Technology | Purpose |
|---|---|
| PHP | Backend |
| PostgreSQL / Supabase | Database and private file storage |
| HTML5 / CSS3 | Frontend |
| Responsive CSS | Customer, shipper and admin interfaces |
| JavaScript | Client-side interaction |
| AJAX | Asynchronous requests |
| JSON | Data exchange |
| OOP | Programming approach |
| MVC | Application architecture |
| Leaflet / Geoapify | Maps, address search and motorcycle routes |
| SePay | Payment checkout and authenticated callbacks |
| Resend | Verification, password reset and transactional email |
| Git / GitHub | Version control |

## 5. Architecture

The application uses PHP views, API entry points and service classes in an MVC-style structure.

```text
View
  ↓
Controller
  ↓
Model
  ↓
Database

Controller
  ↓
Services
  ├── Map / Routing API
  └── Payment API
```

## 6. Project Structure

```text
SomiGrabDelivery-WEB/
│
├── app/
│   ├── Config/
│   ├── Services/
│   └── Views/
│       ├── shared/
│       ├── customer/
│       ├── shipper/
│       └── admin/
│
├── public/
│   ├── api/
│   └── assets/
├── database/
├── bin/
├── docs/
├── tests/
├── index.php
└── README.md
```

## 7. Database

Main tables include `users`, `shipper_profiles`, `customer_addresses`, `orders`, `order_assignments`, `deliveries`, `payments`, `order_reviews`, `support_tickets`, `chat_messages` and `private_files`.

## 8. Installation

### Requirements

- PHP 8.3 with PDO PostgreSQL, mbstring, BCMath, fileinfo, OpenSSL and cURL
- PostgreSQL / Supabase
- XAMPP (optional for local Apache/PHP)
- Git

### Setup

1. Clone the repository:

```sh
git clone --branch dev https://github.com/anhminhle26022006-ops/SomiGrabDelivery-WEB.git
cd SomiGrabDelivery-WEB
```

2. Copy `.env.example` to `.env` and configure the database and provider credentials. PHP loads the root `.env`; server environment variables take precedence.

   Use PostgreSQL credentials in `SOMI_DB_*`. Configure Geoapify, SePay, Resend and private Supabase Storage as described in the linked setup guides below.

3. For a new database, initialize `database/SOMI_database.sql`, then apply the versioned migrations. For an existing database, run the migration command without importing the base schema again:

```sh
php database/migrate-customer-orders.php --apply
```

Do not commit real API keys or payment credentials to GitHub.

4. Start the local development server:

```sh
php -S localhost:3000 index.php
```

Open `http://localhost:3000/`. No frontend build or `npm install` is required. With XAMPP, use its PHP executable if `php` is not on PATH.

Setup guides: [maps and addresses](docs/ADDRESS-GEOAPIFY-SETUP.md), [SePay](docs/SEPAY-SETUP.md), [private Storage](docs/SUPABASE-STORAGE.md).

Background processing uses the CLI workers in `bin/`. Docker deployments can enable the scheduler with `SOMI_RUN_WORKERS=1`. Configure hosting environment variables separately; `.env` is not included in Git or the Docker image.

## 9. Git Workflow

```text
main
  ↓
dev
  ↓
feature/...
```

Example:

```sh
git checkout dev
git checkout -b feature/customer-order

git add .
git commit -m "feat: add customer order"

git push origin feature/customer-order
```

Create a Pull Request before merging into `dev`. Render currently deploys `dev`; changes to `main` are managed separately.

## 10. API Integration

The system is designed to support:

- Map / Location API
- Distance / Routing API
- Payment API

Use sandbox/test environments during development where available.

## 11. Future Development

- Digital wallet
- Mobile application
- Delivery analytics
- AI-assisted matching and ETA

## 12. Project Information

Project: Somi Grab Delivery
Type: Delivery Platform
Backend: PHP
Database: PostgreSQL / Supabase
Frontend: HTML, CSS, JavaScript
Architecture: OOP + MVC
Communication: AJAX / JSON
APIs: Map, Routing, Payment
Version Control: Git / GitHub

Live website: [somigrabdelivery.online](https://somigrabdelivery.online)

Module contracts and handover details: [Customer, payment, email and Admin](docs/PERSON1-API.md), [Shipper and delivery](docs/PERSON2-API.md), [chat, support, notifications, Storage and payout](docs/PERSON3-API.md). Production acceptance still requires authenticated end-to-end checks and verification of live providers and background workers.

## 13. Academic Project

Somi Grab Delivery is an academic web development project focusing on:

- Object-Oriented Programming
- MVC architecture
- Responsive web design
- AJAX and JSON
- External API integration
- Database management
- Git and GitHub collaboration

---

Somi Grab Delivery — Connecting customers with flexible part-time shippers.
