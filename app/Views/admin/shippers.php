<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SOMI Delivery - Shippers Management</title>
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
    <a href="shippers.php" class="menu-item active">
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
                    <span class="subtitle">NETWORK OPERATIONS</span>
                    <div class="title-row">
                        <h2>Shippers</h2>
                        <button class="btn-export"><i class="fa-solid fa-arrow-right"></i> Review registrations</button>
                    </div>
                    <p class="description">Registered shipper applications appear here automatically for verification and profile management.</p>
                </div>

                <!-- Info Notice Banner -->
                <div class="notice-banner">
                    <i class="fa-solid fa-check"></i>
                    <span>Accounts are created automatically after customer or shipper registration. Admins can review profiles and control access, but cannot create accounts manually.</span>
                </div>

                <!-- Stats Grid 3 Cards -->
                <div class="stats-grid-3">
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fa-solid fa-user-group"></i></div>
                        <span class="stat-label">Verified shippers</span>
                        <div class="stat-value">1,284</div>
                        <span class="stat-trend positive">+28 this month</span>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fa-solid fa-check"></i></div>
                        <span class="stat-label">Online now</span>
                        <div class="stat-value">842</div>
                        <span class="stat-sub">65.6% available</span>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
                        <span class="stat-label">Verification queue</span>
                        <div class="stat-value">18</div>
                        <span class="stat-sub">4 submitted today</span>
                    </div>
                </div>

                <!-- Filter Tabs & Search Bar -->
                <div class="users-control-panel">
                    <div class="filter-tabs">
                        <button class="tab-btn active" onclick="filterShipperTable('all', this)">All <span class="tab-count">4</span></button>
                        <button class="tab-btn" onclick="filterShipperTable('online', this)">Online</button>
                        <button class="tab-btn" onclick="filterShipperTable('pending', this)">Pending</button>
                        <button class="tab-btn" onclick="filterShipperTable('suspended', this)">Suspended</button>
                    </div>

                    <div class="search-sort-row">
                        <div class="search-box">
                            <i class="fa-solid fa-filter"></i>
                            <input type="text" id="shipperSearchInput" placeholder="Search shipper, vehicle, or ID..." onkeyup="searchShipperTable()">
                        </div>
                        <div class="sort-dropdown">
                            <select id="shipperSortSelect">
                                <option>Newest first</option>
                                <option>Oldest first</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Shippers Data Table Card -->
                <div class="card users-table-card">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>SHIPPER</th>
                                <th>VEHICLE / RATING</th>
                                <th>DELIVERIES</th>
                                <th>STATUS</th>
                                <th>ACTION</th>
                            </tr>
                        </thead>
                        <tbody id="shipperTableBody">
                            <tr data-status="online">
                                <td class="user-cell">
                                    <div class="user-id-text">SHP-1284</div>
                                    <div class="user-sub-text">Marcus Lee</div>
                                </td>
                                <td>
                                    <div class="main-text">VEH-0842 · Honda Wave</div>
                                    <div class="sub-text">4.9 rating · VER-1284</div>
                                </td>
                                <td class="date-text">428 deliveries</td>
                                <td><span class="status-badge online"><span class="dot"></span> Online</span></td>
                                <td><button class="action-link-btn">Manage account <i class="fa-solid fa-arrow-right"></i></button></td>
                            </tr>
                            <tr data-status="online">
                                <td class="user-cell">
                                    <div class="user-id-text">SHP-1283</div>
                                    <div class="user-sub-text">Nina Park</div>
                                </td>
                                <td>
                                    <div class="main-text">VEH-0839 · Yamaha Mio</div>
                                    <div class="sub-text">4.8 rating · VER-1283</div>
                                </td>
                                <td class="date-text">312 deliveries</td>
                                <td><span class="status-badge online"><span class="dot"></span> Online</span></td>
                                <td><button class="action-link-btn">Manage account <i class="fa-solid fa-arrow-right"></i></button></td>
                            </tr>
                            <tr data-status="pending">
                                <td class="user-cell">
                                    <div class="user-id-text">SHP-1282</div>
                                    <div class="user-sub-text">Chris Morgan</div>
                                </td>
                                <td>
                                    <div class="main-text">VEH-0838 · Bicycle</div>
                                    <div class="sub-text">Documents received · VER-1282</div>
                                </td>
                                <td class="date-text">0 deliveries</td>
                                <td><span class="status-badge pending"><span class="dot"></span> Pending</span></td>
                                <td><button class="action-link-btn">Manage account <i class="fa-solid fa-arrow-right"></i></button></td>
                            </tr>
                            <tr data-status="suspended">
                                <td class="user-cell">
                                    <div class="user-id-text">SHP-1241</div>
                                    <div class="user-sub-text">Sam Rivera</div>
                                </td>
                                <td>
                                    <div class="main-text">VEH-0782 · Toyota Vios</div>
                                    <div class="sub-text">4.2 rating · VER-1241</div>
                                </td>
                                <td class="date-text">186 deliveries</td>
                                <td><span class="status-badge suspended"><span class="dot"></span> Suspended</span></td>
                                <td><button class="action-link-btn">Manage account <i class="fa-solid fa-arrow-right"></i></button></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <script src="../../../public/assets/js/modules/shippers.js"></script>
</body>
</html>