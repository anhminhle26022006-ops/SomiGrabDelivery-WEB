# Somi Grab Delivery

> Web-based delivery platform connecting customers with part-time shippers.

## 1. Overview

Somi Grab Delivery allows customers to create delivery orders, estimate fees, make payments, and track orders. Part-time shippers can view available orders, accept jobs, update delivery status, and manage delivery history.

Academic project using OOP, MVC, Bootstrap, AJAX/JSON, MySQL, and external APIs.

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

## 4. Technologies

| Technology | Purpose |
|---|---|
| PHP | Backend |
| MySQL | Database |
| HTML5 / CSS3 | Frontend |
| Bootstrap | Responsive UI |
| JavaScript | Client-side interaction |
| AJAX | Asynchronous requests |
| JSON | Data exchange |
| OOP | Programming approach |
| MVC | Application architecture |
| Map / Routing API | Location and distance |
| Payment API | Online payment |
| Git / GitHub | Version control |

## 5. Architecture

The application follows an OOP + MVC structure.

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

## 6. Project Structure

SomiGrabDelivery/
│
├── config/
├── controllers/
├── models/
├── services/
├── views/
│   ├── auth/
│   ├── customer/
│   ├── shipper/
│   ├── delivery/
│   └── admin/
│
├── ajax/
├── public/
├── database/
├── index.php
└── README.md

## 7. Database

Main tables:

users
shipper_profiles
addresses
orders
order_details
deliveries
payments
reviews
areas

## 8. Installation

### Requirements

- PHP
- MySQL
- XAMPP
- Git

### Setup

1. Clone the repository:

git clone https://github.com/your-username/SomiGrabDelivery.git

2. Move the project to:

C:\xampp\htdocs\SomiGrabDelivery

3. Start Apache and MySQL in XAMPP.

4. Create database:

somi_grab_delivery

5. Import:

database/somi_grab_delivery.sql

6. Configure:

config/Database.php

7. Configure API credentials:

config/ApiConfig.php

Do not commit real API keys or payment credentials to GitHub.

8. Open:

http://localhost/SomiGrabDelivery/

## 9. Git Workflow

main
  ↓
develop
  ↓
feature/...

Example:

git checkout develop
git checkout -b feature/customer-order

git add .
git commit -m "feat: add customer order"

git push origin feature/customer-order

Create a Pull Request before merging into develop.

## 10. API Integration

The system is designed to support:

- Map / Location API
- Distance / Routing API
- Payment API

Use sandbox/test environments during development where available.

## 11. Future Development

- Automatic shipper matching
- Real-time location tracking
- Real-time notifications
- Customer–shipper chat
- Digital wallet
- Mobile application
- Delivery analytics
- AI-assisted matching and ETA

## 12. Project Information

Project: Somi Grab Delivery
Type: Delivery Platform
Backend: PHP
Database: MySQL
Frontend: HTML, CSS, JavaScript, Bootstrap
Architecture: OOP + MVC
Communication: AJAX / JSON
APIs: Map, Routing, Payment
Version Control: Git / GitHub

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
