# 🚚 Somi Grab Delivery

> **A Delivery Application for Part-Time Workers**

Somi Grab Delivery is a delivery platform that connects people who need to send packages with **part-time delivery workers (Part-time Shippers)**.

The system allows customers to create delivery requests, provide pickup and delivery locations, calculate estimated delivery fees, make payments, and track their orders.

Part-time shippers can browse available delivery requests, accept suitable orders, pick up packages, update delivery statuses, and manage their delivery history and earnings.

The project is developed using **Object-Oriented Programming (OOP)** and the **MVC architecture**, with **Bootstrap** for the user interface, **AJAX/JSON** for asynchronous communication, and **External APIs/Webservices** for location, distance, and payment services.

---

## 📌 Table of Contents

* [Introduction](#-introduction)
* [Project Objectives](#-project-objectives)
* [Target Users](#-target-users)
* [Main Features](#-main-features)
* [Delivery Workflow](#-delivery-workflow)
* [Technologies](#-technologies)
* [System Architecture](#-system-architecture)
* [API & Webservice Integration](#-api--webservice-integration)
* [AJAX & JSON](#-ajax--json)
* [Database](#-database)
* [Project Structure](#-project-structure)
* [Installation](#-installation)
* [Git Workflow](#-git-workflow)
* [Project Management](#-project-management)
* [Team Members](#-team-members)
* [Future Development](#-future-development)

---

# 📖 Introduction

Somi Grab Delivery is a delivery platform designed to connect **customers who need to send packages** with **part-time delivery workers**.

Customers can create delivery requests by providing pickup and delivery addresses, recipient information, package details, and delivery requirements.

The system can use external map and distance services to determine locations, calculate the estimated distance and travel time, and estimate the delivery fee.

After the order is created and the payment process is completed, available part-time shippers can view and accept suitable delivery requests.

The shipper then picks up the package, transports it, updates the delivery status, and confirms successful delivery.

```text
Customer
   │
   │ Create Delivery Order
   ▼
Somi Grab Delivery
   │
   ├── Map / Location API
   │
   ├── Distance / Routing API
   │
   ├── Delivery Fee Calculation
   │
   └── Payment API
   │
   ▼
Part-time Shipper
   │
   ├── Accept Order
   ├── Pick Up Package
   ├── Deliver Package
   └── Complete Order
```

---

# 🎯 Project Objectives

The main objectives of Somi Grab Delivery are:

* Provide a simple platform for creating delivery requests.
* Connect customers with part-time delivery workers.
* Support pickup and delivery address management.
* Calculate delivery distance and estimated travel time.
* Calculate estimated delivery fees.
* Support online payment through an external payment service.
* Allow part-time shippers to find and accept available delivery orders.
* Allow shippers to update delivery statuses.
* Allow customers to track their delivery orders.
* Provide order history for customers and shippers.
* Allow customers to rate and review shippers.
* Provide basic administration and management functions.
* Apply Object-Oriented Programming principles.
* Apply the MVC architecture.
* Use AJAX and JSON for asynchronous operations.
* Integrate existing external APIs/Webservices.
* Support collaborative development through Git and GitHub.

---

# 👥 Target Users

## 👤 Customer

Customers are users who need to send packages from one location to another.

### Customer Features

* Register and log in.
* Manage personal information.
* Create delivery orders.
* Enter pickup address.
* Enter delivery address.
* Enter recipient information.
* Enter package information.
* View estimated distance and travel time.
* View estimated delivery fee.
* Make payment.
* Track delivery status.
* View order history.
* Rate and review shippers.

---

## 🛵 Part-time Shipper

Part-time shippers are users who want to accept delivery jobs flexibly during their available time.

### Shipper Features

* Register as a shipper.
* Manage personal information.
* Set availability status.
* View available delivery orders.
* Search and filter delivery orders.
* View order details.
* Accept delivery orders.
* Confirm package pickup.
* Update delivery status.
* Confirm successful delivery.
* View delivery history.
* View delivery earnings.

---

## 🧑‍💼 Administrator

Administrators are responsible for managing and monitoring the platform.

### Admin Features

* Manage customers.
* Manage shippers.
* Manage delivery orders.
* Manage order statuses.
* Manage delivery areas.
* Manage delivery fees.
* Manage payment records.
* Manage reviews.
* Handle reports and delivery issues.

---

# ✨ Main Features

## 1. Authentication & Authorization

The system provides:

* User registration.
* User login.
* User logout.
* Role-based access control.
* Customer role.
* Shipper role.
* Admin role.
* Personal profile management.

---

## 2. Create Delivery Order

Customers can create a delivery request by entering:

```text
Pickup Address
Delivery Address
Recipient Information
Package Type
Package Weight
Delivery Notes
Preferred Delivery Time
```

The system can then use external location and routing services to process the provided addresses.

---

## 3. Distance & Delivery Fee Calculation

The system integrates external map and routing services to support:

* Address geocoding.
* Coordinate identification.
* Distance calculation.
* Estimated travel time.
* Delivery fee calculation.

Example:

```text
Pickup Location:
District 10

Delivery Location:
District 7

Estimated Distance:
6.8 km

Estimated Duration:
25 minutes

Estimated Delivery Fee:
35,000 VND
```

---

## 4. Online Payment

After the delivery fee is calculated, customers can proceed with payment through an external payment gateway.

```text
Create Order
      ↓
Calculate Delivery Fee
      ↓
Select Payment Method
      ↓
Payment API
      ↓
Payment Successful
      ↓
Update Payment Status
      ↓
Find Available Shipper
```

The project can use a **sandbox environment** for payment API integration during development and testing.

### Payment Status

```text
PENDING
   ↓
PAID
   │
   ├── FAILED
   │
   └── CANCELLED
```

---

# 5. Find & Accept Delivery Orders

Part-time shippers can browse available delivery requests.

Example:

```text
┌──────────────────────────────┐
│       ORDER #SGD00125        │
├──────────────────────────────┤
│ Pickup: District 10          │
│ Delivery: District 7         │
│ Package: Documents           │
│ Distance: 6.8 km             │
│ Delivery Fee: 35,000 VND    │
├──────────────────────────────┤
│        [ ACCEPT ORDER ]      │
└──────────────────────────────┘
```

Once a shipper accepts an order, the system updates the order status so that the order is no longer available to other shippers.

---

# 6. Delivery Status Tracking

A delivery order can go through the following statuses:

```text
Order Created
      ↓
Payment Completed
      ↓
Finding Shipper
      ↓
Shipper Accepted
      ↓
Package Picked Up
      ↓
In Transit
      ↓
Delivered Successfully
      ↓
Customer Review
```

If a delivery cannot be completed:

```text
Shipper Accepted
      ↓
Delivery Failed
      ↓
Issue Handling
```

---

# 7. Rating & Review

After a successful delivery, customers can provide feedback about the shipper.

Customers can:

* Give a rating from 1 to 5 stars.
* Write a review.
* Submit feedback.

The review information is stored in the database and can be displayed on the shipper's profile.

---

# 🔄 Delivery Workflow

```text
                         CUSTOMER
                            │
                            │ Create Order
                            ▼
                  ┌────────────────────┐
                  │  SOMI GRAB         │
                  │     DELIVERY       │
                  └─────────┬──────────┘
                            │
                    Enter Addresses
                            │
                            ▼
                    Map / Location API
                            │
                            ▼
                  Distance / Routing API
                            │
                            ▼
                    Calculate Delivery Fee
                            │
                            ▼
                       Payment API
                            │
                            ▼
                  Find Part-time Shipper
                            │
                            ▼
                       Accept Order
                            │
                            ▼
                       Pick Up Package
                            │
                            ▼
                         In Transit
                            │
                            ▼
                    Delivery Completed
                            │
                            ▼
                      Customer Review
```

---

# 🛠 Technologies

| Technology               | Purpose                              |
| ------------------------ | ------------------------------------ |
| **PHP**                  | Backend development                  |
| **OOP**                  | Object-Oriented Programming          |
| **MVC**                  | Application architecture             |
| **MySQL**                | Database management                  |
| **HTML5**                | Web structure                        |
| **CSS3**                 | Custom styling                       |
| **Bootstrap**            | Responsive UI design                 |
| **JavaScript**           | Client-side interaction              |
| **AJAX**                 | Asynchronous communication           |
| **JSON**                 | Data exchange format                 |
| **Map API**              | Location and map services            |
| **Distance/Routing API** | Distance and travel-time calculation |
| **Payment API**          | Online payment integration           |
| **Git**                  | Version control                      |
| **GitHub**               | Source code and team collaboration   |

---

# 🏗 System Architecture

Somi Grab Delivery follows the **MVC architecture** combined with Object-Oriented Programming.

```text
                         USER
                           │
                           ▼
                          VIEW
                           │
                           ▼
                      CONTROLLER
                     /     |      \
                    /      |       \
                   ▼       ▼        ▼
                MODEL     AJAX     SERVICE
                  │         │       /    \
                  │         │      /      \
                  ▼         ▼     ▼        ▼
              DATABASE    JSON   MAP     PAYMENT
```

## Model

The Model layer manages application data and business logic.

Main models include:

* User
* Order
* OrderDetail
* Shipper
* Delivery
* Address
* Payment
* Review

---

## View

The View layer is responsible for displaying the user interface.

Main interfaces include:

* Customer interface.
* Shipper dashboard.
* Delivery management.
* Admin dashboard.
* Order management.
* Payment interface.

**Bootstrap** is used to build a responsive interface.

---

## Controller

The Controller layer handles:

* HTTP requests.
* Input validation.
* Model interaction.
* Business processes.
* External API requests.
* AJAX requests.
* JSON responses.
* View navigation.

---

# 🌐 API & Webservice Integration

The project uses **existing external APIs/Webservices** to extend the functionality of the delivery platform.

## 🗺 Map / Location API

The Map API can be used for:

* Geocoding addresses.
* Converting addresses into coordinates.
* Displaying locations on a map.
* Supporting route information.

```text
Address
   ↓
Geocoding API
   ↓
Latitude + Longitude
```

---

## 📏 Distance / Routing API

The Routing API can be used to calculate:

* Distance between pickup and delivery locations.
* Estimated travel duration.
* Route information.

```text
Pickup Coordinates
        +
Delivery Coordinates
        ↓
Routing API
        ↓
Distance + Duration
```

---

## 💳 Payment API

The Payment API is used to support online delivery payments.

```text
Delivery Order
      ↓
Delivery Fee
      ↓
Payment Request
      ↓
Payment Gateway
      ↓
Payment Result
      ↓
Update Payment Status
```

The system should use a **sandbox environment** for development and testing.

---

# ⚡ AJAX & JSON

AJAX is used to perform asynchronous operations without requiring the entire page to reload.

## Example 1 – Calculate Distance

```text
Enter Addresses
      ↓
     AJAX
      ↓
Controller
      ↓
Routing API
      ↓
    JSON
      ↓
Frontend
```

Example response:

```json
{
    "success": true,
    "distance": 6.8,
    "duration": 25,
    "estimated_fee": 35000
}
```

---

## Example 2 – Accept Delivery Order

```text
Shipper clicks "Accept Order"
             ↓
            AJAX
             ↓
      OrderController
             ↓
          Database
             ↓
            JSON
             ↓
     Update Order Status
```

Example response:

```json
{
    "success": true,
    "message": "Order accepted successfully",
    "order_status": "ACCEPTED"
}
```

---

## AJAX Use Cases

AJAX can be applied to:

* Search delivery orders.
* Filter orders by delivery area.
* Calculate delivery distance.
* Calculate delivery fees.
* Accept delivery orders.
* Update delivery status.
* Update dashboard information.
* Submit customer reviews.
* Refresh available delivery orders.

---

# 🗄 Database

The main database tables are planned as follows:

```text
users
shipper_profiles
addresses
orders
order_details
deliveries
payments
reviews
areas
```

## Main Relationships

```text
USER
 │
 ├──────── CUSTOMER
 │             │
 │             └──── ORDERS
 │                    │
 │                    ├──── ORDER_DETAILS
 │                    │
 │                    ├──── PAYMENT
 │                    │
 │                    └──── DELIVERY
 │                              │
 │                              └──── SHIPPER
 │
 └──────── SHIPPER
```

---

# 📁 Project Structure

```text
SomiGrabDelivery/
│
├── config/
│   ├── Database.php
│   └── ApiConfig.php
│
├── controllers/
│   ├── AuthController.php
│   ├── OrderController.php
│   ├── ShipperController.php
│   ├── DeliveryController.php
│   ├── PaymentController.php
│   └── ReviewController.php
│
├── models/
│   ├── User.php
│   ├── Order.php
│   ├── OrderDetail.php
│   ├── Shipper.php
│   ├── Delivery.php
│   ├── Payment.php
│   └── Review.php
│
├── services/
│   ├── MapService.php
│   └── PaymentService.php
│
├── views/
│   ├── auth/
│   ├── customer/
│   ├── shipper/
│   ├── delivery/
│   └── admin/
│
├── ajax/
│   ├── search-order.php
│   ├── calculate-distance.php
│   ├── accept-order.php
│   └── update-status.php
│
├── public/
│   ├── css/
│   ├── js/
│   └── images/
│
├── database/
│   └── somi_grab_delivery.sql
│
├── index.php
└── README.md
```

---

# 🚀 Installation

## 1. Clone the Repository

```bash
git clone https://github.com/your-username/SomiGrabDelivery.git
```

## 2. Move the Project

If using XAMPP:

```text
C:\xampp\htdocs\SomiGrabDelivery
```

---

## 3. Start XAMPP

Start the following services:

```text
Apache
MySQL
```

---

## 4. Create the Database

Open phpMyAdmin:

```text
http://localhost/phpmyadmin
```

Create a database:

```text
somi_grab_delivery
```

Import:

```text
database/somi_grab_delivery.sql
```

---

## 5. Configure Database Connection

Open:

```text
config/Database.php
```

Configure the database connection:

```php
private $host = "localhost";
private $dbName = "somi_grab_delivery";
private $username = "root";
private $password = "";
```

---

## 6. Configure External APIs

Open:

```text
config/ApiConfig.php
```

Configure the required API credentials.

> **Important:** Do not commit real API keys or payment credentials to GitHub.

---

## 7. Run the Project

Open:

```text
http://localhost/SomiGrabDelivery/
```

---

# 🌿 Git Workflow

GitHub is used for source-code management and team collaboration.

## Branch Structure

```text
main
│
└── develop
     │
     ├── feature/authentication
     ├── feature/customer-order
     ├── feature/shipper
     ├── feature/delivery
     ├── feature/map-api
     ├── feature/payment
     └── feature/admin
```

## Development Workflow

```text
Create Feature Branch
        ↓
Develop Feature
        ↓
Commit Changes
        ↓
Push Branch
        ↓
Create Pull Request
        ↓
Code Review
        ↓
Merge
```

Each team member works on a separate feature branch and creates a Pull Request before merging changes into the shared development branch.

---

# 📊 Project Management

The project uses the following tools:

* **GitHub** – Source code management.
* **GitHub Branches** – Feature-based development.
* **GitHub Issues / Projects** – Task management.
* **Microsoft Teams** – Team communication.
* **Shared Hosting** – Website and database deployment.

Screenshots of project management activities can be included in the final project report as evidence of team collaboration and progress.

---

# 🔮 Future Development

Possible future improvements include:

* Real-time shipper location tracking.
* Navigation support for shippers.
* Automatic shipper-order matching.
* Location-based order recommendations.
* Advanced online payment support.
* Shipper digital wallet.
* Shipper reward system.
* Real-time notifications.
* Customer–shipper chat.
* Delivery issue and complaint management.
* Mobile applications for customers and shippers.
* More advanced delivery analytics.

---

# 👥 Team Members

| Member        | Role                     |
| ------------- | ------------------------ |
| Team Member 1 | Backend / MVC            |
| Team Member 2 | Frontend / Bootstrap     |
| Team Member 3 | Database / API           |
| Team Member 4 | AJAX / Payment / Testing |

> Team responsibilities may be updated during the development process.

---

# 📚 Project Information

| Information           | Details                                    |
| --------------------- | ------------------------------------------ |
| **Project Name**      | Somi Grab Delivery                         |
| **Project Type**      | Delivery Platform                          |
| **Target Users**      | Customers & Part-time Shippers             |
| **Architecture**      | OOP + MVC                                  |
| **Backend**           | PHP                                        |
| **Database**          | MySQL                                      |
| **Frontend**          | HTML, CSS, JavaScript, Bootstrap           |
| **Data Format**       | JSON                                       |
| **Communication**     | AJAX                                       |
| **External Services** | Map API, Distance/Routing API, Payment API |
| **Version Control**   | Git & GitHub                               |

---

# 🎓 Academic Project

Somi Grab Delivery is developed as a **Web Development course project**, focusing on:

* Object-Oriented Programming.
* MVC architecture.
* Responsive web design with Bootstrap.
* AJAX and JSON communication.
* External API/Webservice integration.
* Database management.
* Git and GitHub collaboration.
* Basic software development workflow.

---

## 📌 Project Scope

The core scope of the project is:

> **Connecting customers who need to send packages with part-time delivery workers through a web-based delivery platform.**

The project focuses on the **delivery process**, rather than operating as an online product marketplace.

**Somi Grab Delivery — Connecting deliveries with flexible part-time shippers.**
