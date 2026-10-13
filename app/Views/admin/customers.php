<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SOMI Delivery - Customers Management</title>
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
                <a href="customers.php" class="menu-item active">
                    <i class="fa-solid fa-users"></i> Customers
                </a>
                <a href="#" class="menu-item">
                    <i class="fa-solid fa-motorcycle"></i> Shippers
                </a>
                <a href="#" class="menu-item">
                    <i class="fa-solid fa-file-lines"></i> Orders
                </a>
                <a href="#" class="menu-item">
                    <i class="fa-solid fa-triangle-exclamation"></i> Complaints <span class="badge red">4</span>
                </a>
                <a href="#" class="menu-item">
                    <i class="fa-solid fa-scale-balanced"></i> Disputes
                </a>
                <a href="#" class="menu-item">
                    <i class="fa-solid fa-wallet"></i> Payments
                </a>
                <a href="#" class="menu-item">
                    <i class="fa-regular fa-star"></i> Reviews
                </a>
                <a href="#" class="menu-item">
                    <i class="fa-regular fa-bell"></i> Notifications <span class="badge gray">3</span>
                </a>
                <a href="#" class="menu-item">
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
                    <span class="subtitle">CUSTOMER OPERATIONS</span>
                    <div class="title-row">
                        <h2>Customers</h2>
                        <button class="btn-export"><i class="fa-solid fa-chart-column"></i> Export customers</button>
                    </div>
                    <p class="description">Registered customer profiles appear here automatically for account and payment management[cite: 13].</p>
                </div>

                <!-- Info Notice Banner -->
                <div class="notice-banner">
                    <i class="fa-solid fa-check"></i>
                    <span>Accounts are created automatically after customer or shipper registration. Admins can review profiles and control access, but cannot create accounts manually[cite: 13].</span>
                </div>

                <!-- Stats Grid 3 Cards -->
                <div class="stats-grid-3">
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fa-solid fa-user-group"></i></div>
                        <span class="stat-label">Total customers</span>
                        <div class="stat-value">7,142</div>
                        <span class="stat-trend positive">+186 this month</span>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fa-regular fa-file-lines"></i></div>
                        <span class="stat-label">Active today</span>
                        <div class="stat-value">1,204</div>
                        <span class="stat-sub">342 with live orders</span>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fa-solid fa-dollar-sign"></i></div>
                        <span class="stat-label">Customer value</span>
                        <div class="stat-value">$286K</div>
                        <span class="stat-sub">Last 30 days</span>
                    </div>
                </div>

                <!-- Filter Tabs & Search Bar -->
                <div class="users-control-panel">
                    <div class="filter-tabs">
                        <button class="tab-btn active" onclick="filterCustomerTable('all', this)">All <span class="tab-count">4</span></button>
                        <button class="tab-btn" onclick="filterCustomerTable('active', this)">Active</button>
                        <button class="tab-btn" onclick="filterCustomerTable('new', this)">New</button>
                        <button class="tab-btn" onclick="filterCustomerTable('flagged', this)">Flagged</button>
                    </div>

                    <div class="search-sort-row">
                        <div class="search-box">
                            <i class="fa-solid fa-filter"></i>
                            <input type="text" id="customerSearchInput" placeholder="Search customer or phone number..." onkeyup="searchCustomerTable()">
                        </div>
                        <div class="sort-dropdown">
                            <select id="customerSortSelect">
                                <option>Newest first</option>
                                <option>Oldest first</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Customers Data Table Card -->
                <div class="card users-table-card">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>CUSTOMER</th>
                                <th>DELIVERIES</th>
                                <th>TOTAL SPEND</th>
                                <th>STATUS</th>
                                <th>ACTION</th>
                            </tr>
                        </thead>
                        <tbody id="customerTableBody">
                            <tr data-status="active">
                                <td class="user-cell">
                                    <div class="user-id-text">CUS-7102</div>
                                    <div class="user-sub-text">Avery Kim</div>
                                </td>
                                <td>
                                    <div class="main-text">26 orders · SM-2484</div>
                                    <div class="sub-text">avery@example.com · +1 555 284 0192</div>
                                </td>
                                <td class="date-text font-bold">$286.40</td>
                                <td><span class="status-badge active"><span class="dot"></span> Active</span></td>
                                <td><button class="action-link-btn">Manage account <i class="fa-solid fa-arrow-right"></i></button></td>
                            </tr>
                            <tr data-status="active">
                                <td class="user-cell">
                                    <div class="user-id-text">CUS-7098</div>
                                    <div class="user-sub-text">Minh Tran</div>
                                </td>
                                <td>
                                    <div class="main-text">18 orders · SM-2483</div>
                                    <div class="sub-text">minh@example.com · +1 555 401 7782</div>
                                </td>
                                <td class="date-text font-bold">$214.20</td>
                                <td><span class="status-badge active"><span class="dot"></span> Active</span></td>
                                <td><button class="action-link-btn">Manage account <i class="fa-solid fa-arrow-right"></i></button></td>
                            </tr>
                            <tr data-status="new">
                                <td class="user-cell">
                                    <div class="user-id-text">CUS-7092</div>
                                    <div class="user-sub-text">Jordan Bell</div>
                                </td>
                                <td>
                                    <div class="main-text">2 orders · SM-2482</div>
                                    <div class="sub-text">jordan@example.com · +1 555 632 8014</div>
                                </td>
                                <td class="date-text font-bold">$31.60</td>
                                <td><span class="status-badge new"><span class="dot"></span> New</span></td>
                                <td><button class="action-link-btn">Manage account <i class="fa-solid fa-arrow-right"></i></button></td>
                            </tr>
                            <tr data-status="flagged">
                                <td class="user-cell">
                                    <div class="user-id-text">CUS-7041</div>
                                    <div class="user-sub-text">Taylor Reed</div>
                                </td>
                                <td>
                                    <div class="main-text">11 orders · SM-2479</div>
                                    <div class="sub-text">taylor@example.com · +1 555 390 1148</div>
                                </td>
                                <td class="date-text font-bold">$128.90</td>
                                <td><span class="status-badge flagged"><span class="dot"></span> Flagged</span></td>
                                <td><button class="action-link-btn">Manage account <i class="fa-solid fa-arrow-right"></i></button></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <script src="../../../public/assets/js/modules/customers.js"></script>
</body>
</html>