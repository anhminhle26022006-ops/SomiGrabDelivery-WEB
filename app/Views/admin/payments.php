<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SOMI Delivery - Payments Management</title>
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
    <a href="orders.php" class="menu-item">
        <i class="fa-solid fa-file-lines"></i> Orders
    </a>
    <a href="complaints.php" class="menu-item">
        <i class="fa-solid fa-triangle-exclamation"></i> Complaints <span class="badge red">4</span>
    </a>
    <a href="disputes.php" class="menu-item">
        <i class="fa-solid fa-scale-balanced"></i> Disputes
    </a>
    <a href="payments.php" class="menu-item active">
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
                    <span class="subtitle">FINANCE OPERATIONS</span>
                    <div class="title-row">
                        <h2>Payments</h2>
                        <button class="btn-export"><i class="fa-solid fa-chart-column"></i> Export ledger</button>
                    </div>
                    <p class="description">Reconcile customer charges, refunds, and shipper payouts.</p>
                </div>

                <!-- Stats Grid 3 Cards -->
                <div class="stats-grid-3">
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fa-solid fa-dollar-sign"></i></div>
                        <span class="stat-label">Gross volume</span>
                        <div class="stat-value">$184.2K</div>
                        <span class="stat-trend positive">+12.6% this month</span>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fa-regular fa-credit-card"></i></div>
                        <span class="stat-label">Pending settlement</span>
                        <div class="stat-value">$8,420</div>
                        <span class="stat-sub">Next run at 5 PM</span>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
                        <span class="stat-label">Payment issues</span>
                        <div class="stat-value">3</div>
                        <span class="stat-sub text-warning">Requires reconciliation</span>
                    </div>
                </div>

                <!-- Filter Tabs & Search Bar -->
                <div class="users-control-panel">
                    <div class="filter-tabs">
                        <button class="tab-btn active" onclick="filterPaymentTable('all', this)">All <span class="tab-count">4</span></button>
                        <button class="tab-btn" onclick="filterPaymentTable('held', this)">Held</button>
                        <button class="tab-btn" onclick="filterPaymentTable('released', this)">Released</button>
                        <button class="tab-btn" onclick="filterPaymentTable('direct', this)">Paid directly</button>
                        <button class="tab-btn" onclick="filterPaymentTable('failed', this)">Failed</button>
                    </div>

                    <div class="search-sort-row">
                        <div class="search-box">
                            <i class="fa-solid fa-filter"></i>
                            <input type="text" id="paymentSearchInput" placeholder="Search payment, order, or account..." onkeyup="searchPaymentTable()">
                        </div>
                        <div class="sort-dropdown">
                            <select id="paymentSortSelect">
                                <option>Newest first</option>
                                <option>Oldest first</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Payments Data Table Card -->
                <div class="card users-table-card">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th style="width: 200px;">PAYMENT / ORDER</th>
                                <th>ACCOUNT / METHOD</th>
                                <th style="width: 140px;">AMOUNT</th>
                                <th style="width: 150px;">STATUS</th>
                                <th style="width: 130px; text-align: right;">ACTION</th>
                            </tr>
                        </thead>
                        <tbody id="paymentTableBody">
                            <tr data-status="held">
                                <td>
                                    <div class="user-id-text">PM-8145</div>
                                    <div class="user-sub-text">SM-2484 · Prepaid</div>
                                </td>
                                <td>
                                    <div class="main-text">SHP-1284 · Marcus Lee</div>
                                    <div class="sub-text">Visa •••• 4242 · CUS-7102</div>
                                </td>
                                <td class="date-text font-bold">$14.60</td>
                                <td><span class="status-badge held"><span class="dot"></span> Held</span></td>
                                <td style="text-align: right;"><button class="action-link-btn" style="justify-content: flex-end;">View details <i class="fa-solid fa-arrow-right"></i></button></td>
                            </tr>
                            <tr data-status="direct">
                                <td>
                                    <div class="user-id-text">PM-8144</div>
                                    <div class="user-sub-text">SM-2483 · Cash</div>
                                </td>
                                <td>
                                    <div class="main-text">SHP-1283 · Nina Park</div>
                                    <div class="sub-text">Cash on delivery · CUS-7098</div>
                                </td>
                                <td class="date-text font-bold">$12.80</td>
                                <td><span class="status-badge direct"><span class="dot"></span> Paid directly</span></td>
                                <td style="text-align: right;"><button class="action-link-btn" style="justify-content: flex-end;">View details <i class="fa-solid fa-arrow-right"></i></button></td>
                            </tr>
                            <tr data-status="released">
                                <td>
                                    <div class="user-id-text">PM-8138</div>
                                    <div class="user-sub-text">SM-2478 · Prepaid</div>
                                </td>
                                <td>
                                    <div class="main-text">SHP-1241 · Sam Rivera</div>
                                    <div class="sub-text">Mastercard •••• 9012 · CUS-7041</div>
                                </td>
                                <td class="date-text font-bold">$11.40</td>
                                <td><span class="status-badge released"><span class="dot"></span> Released</span></td>
                                <td style="text-align: right;"><button class="action-link-btn" style="justify-content: flex-end;">View details <i class="fa-solid fa-arrow-right"></i></button></td>
                            </tr>
                            <tr data-status="failed">
                                <td>
                                    <div class="user-id-text">PM-8142</div>
                                    <div class="user-sub-text">SM-2479 · Prepaid</div>
                                </td>
                                <td>
                                    <div class="main-text">SHP-1241 · Sam Rivera</div>
                                    <div class="sub-text">Mastercard •••• 9012 · CUS-7041</div>
                                </td>
                                <td class="date-text font-bold">$11.40</td>
                                <td><span class="status-badge failed"><span class="dot"></span> Failed</span></td>
                                <td style="text-align: right;"><button class="action-link-btn" style="justify-content: flex-end;">View details <i class="fa-solid fa-arrow-right"></i></button></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <script src="../../../public/assets/js/modules/payments.js"></script>
</body>
</html>