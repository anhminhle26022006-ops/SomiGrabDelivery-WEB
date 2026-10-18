<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SOMI Delivery - Orders Management</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../public/assets/css/modules/admin.css">
</head>
<body>
    <div class="app-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-brand">
                <h1>SOMI <span>DELIVERY</span></h1>
            </div>
            <div class="sidebar-category">ADMIN CONSOLE</div>
            <nav class="sidebar-menu">
    <a href="index.php" class="menu-item">
        <i class="fa-solid fa-house"></i> Dashboard
    </a>
    <a href="users.php" class="menu-item">
        <i class="fa-solid fa-user-group"></i> Users
    </a>
    <a href="customers.php" class="menu-item">
        <i class="fa-solid fa-users"></i> Customers
    </a>
    <a href="shippers.php" class="menu-item">
        <i class="fa-solid fa-motorcycle"></i> Shippers
    </a>
    <a href="orders.php" class="menu-item active">
        <i class="fa-solid fa-file-lines"></i> Orders
    </a>
    <a href="complaints.php" class="menu-item">
        <i class="fa-solid fa-triangle-exclamation"></i> Complaints <span class="badge red">4</span>
    </a>
    <a href="disputes.php" class="menu-item">
        <i class="fa-solid fa-scale-balanced"></i> Disputes
    </a>
    <a href="payments.php" class="menu-item">
        <i class="fa-solid fa-wallet"></i> Payments
    </a>
    <a href="reviews.php" class="menu-item">
        <i class="fa-regular fa-star"></i> Reviews
    </a>
    <a href="notifications.php" class="menu-item">
        <i class="fa-regular fa-bell"></i> Notifications <span class="badge gray">3</span>
    </a>
    <a href="audit-logs.php" class="menu-item">
        <i class="fa-solid fa-chart-line"></i> Audit Logs
    </a>
</nav>
            <div class="sidebar-footer">
                <a href="#" class="menu-item logout">
                    <i class="fa-solid fa-right-from-bracket"></i> Log out
                </a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <header class="top-header">
                <div class="header-right">
                    <div class="icon-btn notification-bell">
                        <i class="fa-regular fa-bell"></i>
                        <span class="dot-badge"></span>
                    </div>
                    <div class="user-profile">
                        <div class="avatar">AM</div>
                        <div class="user-info">
                            <span class="name">Account</span>
                            <span class="role">Administrator</span>
                        </div>
                        <i class="fa-solid fa-arrow-right"></i>
                    </div>
                </div>
            </header>

            <div class="dashboard-body">
                <!-- Page Title Section -->
                <div class="welcome-section">
                    <span class="subtitle">DELIVERY OPERATIONS</span>
                    <div class="title-row">
                        <h2>Orders</h2>
                        <button class="btn-export"><i class="fa-solid fa-chart-column"></i> Export orders</button>
                    </div>
                    <p class="description">Monitor every package from creation through proof of delivery.</p>
                </div>

                <!-- Stats Grid 3 Cards -->
                <div class="stats-grid-3">
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fa-regular fa-file-lines"></i></div>
                        <span class="stat-label">Total orders</span>
                        <div class="stat-value">12,684</div>
                        <span class="stat-trend positive">+8.2% this month</span>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fa-solid fa-location-dot"></i></div>
                        <span class="stat-label">Live deliveries</span>
                        <div class="stat-value">326</div>
                        <span class="stat-sub">84 arriving soon</span>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
                        <span class="stat-label">Failed today</span>
                        <div class="stat-value">7</div>
                        <span class="stat-sub">0.6% failure rate</span>
                    </div>
                </div>

                <!-- Filter Tabs & Search Bar -->
                <div class="users-control-panel">
                    <div class="filter-tabs">
                        <button class="tab-btn active" onclick="filterOrderTable('all', this)">All <span class="tab-count">4</span></button>
                        <button class="tab-btn" onclick="filterOrderTable('matching', this)">Matching</button>
                        <button class="tab-btn" onclick="filterOrderTable('transit', this)">In transit</button>
                        <button class="tab-btn" onclick="filterOrderTable('completed', this)">Completed</button>
                        <button class="tab-btn" onclick="filterOrderTable('failed', this)">Failed</button>
                    </div>

                    <div class="search-sort-row">
                        <div class="search-box">
                            <i class="fa-solid fa-filter"></i>
                            <input type="text" id="orderSearchInput" placeholder="Search order, customer, or route..." onkeyup="searchOrderTable()">
                        </div>
                        <div class="sort-dropdown">
                            <select id="orderSortSelect">
                                <option>Newest first</option>
                                <option>Oldest first</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Orders Data Table Card -->
                <div class="card users-table-card">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ORDER / ROUTE</th>
                                <th>CUSTOMER / SHIPPER</th>
                                <th>TOTAL</th>
                                <th>STATUS</th>
                                <th>ACTION</th>
                            </tr>
                        </thead>
                        <tbody id="orderTableBody">
                            <tr data-status="transit">
                                <td>
                                    <div class="user-id-text">SM-2484</div>
                                    <div class="user-sub-text">120 King Boulevard → 44 Oak Road</div>
                                </td>
                                <td>
                                    <div class="main-text">CUS-7102 / SHP-1284</div>
                                    <div class="sub-text">PM-8145 · Prepaid Visa</div>
                                </td>
                                <td class="date-text font-bold">$14.60</td>
                                <td><span class="status-badge transit"><span class="dot"></span> In transit</span></td>
                                <td><button class="action-link-btn">View details <i class="fa-solid fa-arrow-right"></i></button></td>
                            </tr>
                            <tr data-status="transit">
                                <td>
                                    <div class="user-id-text">SM-2483</div>
                                    <div class="user-sub-text">88 Market Street → 340 Pine Avenue</div>
                                </td>
                                <td>
                                    <div class="main-text">CUS-7098 / SHP-1283</div>
                                    <div class="sub-text">PM-8144 · Cash on delivery</div>
                                </td>
                                <td class="date-text font-bold">$12.80</td>
                                <td><span class="status-badge transit"><span class="dot"></span> In transit</span></td>
                                <td><button class="action-link-btn">View details <i class="fa-solid fa-arrow-right"></i></button></td>
                            </tr>
                            <tr data-status="matching">
                                <td>
                                    <div class="user-id-text">SM-2482</div>
                                    <div class="user-sub-text">16 Old Town Road → 8 Lakeside Drive</div>
                                </td>
                                <td>
                                    <div class="main-text">CUS-7092 / Unassigned</div>
                                    <div class="sub-text">PM-8143 · Prepaid Visa</div>
                                </td>
                                <td class="date-text font-bold">$18.20</td>
                                <td><span class="status-badge matching"><span class="dot"></span> Matching</span></td>
                                <td><button class="action-link-btn">View details <i class="fa-solid fa-arrow-right"></i></button></td>
                            </tr>
                            <tr data-status="completed">
                                <td>
                                    <div class="user-id-text">SM-2478</div>
                                    <div class="user-sub-text">19 Central Square → 72 North Point</div>
                                </td>
                                <td>
                                    <div class="main-text">CUS-7041 / SHP-1241</div>
                                    <div class="sub-text">PM-8138 · Prepaid Mastercard</div>
                                </td>
                                <td class="date-text font-bold">$11.40</td>
                                <td><span class="status-badge completed"><span class="dot"></span> Completed</span></td>
                                <td><button class="action-link-btn">View details <i class="fa-solid fa-arrow-right"></i></button></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <script src="../../../public/assets/js/modules/orders.js"></script>
</body>
</html>