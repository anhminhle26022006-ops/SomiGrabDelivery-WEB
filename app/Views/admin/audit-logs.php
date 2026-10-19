<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SOMI Delivery - Audit Logs Management</title>
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
    <a href="payments.php" class="menu-item">
        <i class="fa-solid fa-wallet"></i> Payments
    </a>
    <a href="reviews.php" class="menu-item">
        <i class="fa-regular fa-star"></i> Reviews
    </a>
    <a href="notifications.php" class="menu-item">
        <i class="fa-regular fa-bell"></i> Notifications <span class="badge gray">3</span>
    </a>
    <a href="audit-logs.php" class="menu-item active">
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
                    <span class="subtitle">SYSTEM GOVERNANCE</span>
                    <div class="title-row">
                        <h2>Audit Logs</h2>
                        <button class="btn-export"><i class="fa-solid fa-chart-column"></i> Export logs</button>
                    </div>
                    <p class="description">Trace sensitive actions and changes made across the operations console.</p>
                </div>

                <!-- Stats Grid 3 Cards -->
                <div class="stats-grid-3">
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fa-solid fa-chart-line"></i></div>
                        <span class="stat-label">Events today</span>
                        <div class="stat-value">2,418</div>
                        <span class="stat-sub">Live system record</span>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fa-solid fa-user-group"></i></div>
                        <span class="stat-label">Admin actions</span>
                        <div class="stat-value">186</div>
                        <span class="stat-sub">Across 12 operators</span>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
                        <span class="stat-label">Security events</span>
                        <div class="stat-value">4</div>
                        <span class="stat-sub text-success">All reviewed</span>
                    </div>
                </div>

                <!-- Filter Tabs & Search Bar -->
                <div class="users-control-panel">
                    <div class="filter-tabs">
                        <button class="tab-btn active" onclick="filterAuditTable('all', this)">All <span class="tab-count">4</span></button>
                        <button class="tab-btn" onclick="filterAuditTable('success', this)">Success</button>
                        <button class="tab-btn" onclick="filterAuditTable('review', this)">Review</button>
                        <button class="tab-btn" onclick="filterAuditTable('blocked', this)">Blocked</button>
                    </div>

                    <div class="search-sort-row">
                        <div class="search-box">
                            <i class="fa-solid fa-filter"></i>
                            <input type="text" id="auditSearchInput" placeholder="Search actor, action, or resource ID..." onkeyup="searchAuditTable()">
                        </div>
                        <div class="sort-dropdown">
                            <select id="auditSortSelect">
                                <option>Newest first</option>
                                <option>Oldest first</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Audit Logs Data Table Card -->
                <div class="card users-table-card">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th style="width: 220px;">EVENT / ACTION</th>
                                <th>ACTOR / RESOURCE</th>
                                <th style="width: 140px;">TIME</th>
                                <th style="width: 140px;">RESULT</th>
                                <th style="width: 130px; text-align: right;">ACTION</th>
                            </tr>
                        </thead>
                        <tbody id="auditTableBody">
                            <tr data-status="success">
                                <td>
                                    <div class="user-id-text">LOG-62418</div>
                                    <div class="user-sub-text">Complaint status changed</div>
                                </td>
                                <td>
                                    <div class="main-text">Alex Morgan</div>
                                    <div class="sub-text">RP-1042 · Reviewing</div>
                                </td>
                                <td class="date-text">2 min ago</td>
                                <td><span class="status-badge success"><span class="dot"></span> Success</span></td>
                                <td style="text-align: right;"><button class="action-link-btn" style="justify-content: flex-end;">View details <i class="fa-solid fa-arrow-right"></i></button></td>
                            </tr>
                            <tr data-status="success">
                                <td>
                                    <div class="user-id-text">LOG-62417</div>
                                    <div class="user-sub-text">Shipper verification approved</div>
                                </td>
                                <td>
                                    <div class="main-text">Jamie Chen</div>
                                    <div class="sub-text">SHP-1283 · Nina Park</div>
                                </td>
                                <td class="date-text">12 min ago</td>
                                <td><span class="status-badge success"><span class="dot"></span> Success</span></td>
                                <td style="text-align: right;"><button class="action-link-btn" style="justify-content: flex-end;">View details <i class="fa-solid fa-arrow-right"></i></button></td>
                            </tr>
                            <tr data-status="review">
                                <td>
                                    <div class="user-id-text">LOG-62416</div>
                                    <div class="user-sub-text">Payment reconciliation attempted</div>
                                </td>
                                <td>
                                    <div class="main-text">System</div>
                                    <div class="sub-text">PM-8142 · $11.40</div>
                                </td>
                                <td class="date-text">1h ago</td>
                                <td><span class="status-badge review"><span class="dot"></span> Review</span></td>
                                <td style="text-align: right;"><button class="action-link-btn" style="justify-content: flex-end;">View details <i class="fa-solid fa-arrow-right"></i></button></td>
                            </tr>
                            <tr data-status="blocked">
                                <td>
                                    <div class="user-id-text">LOG-62415</div>
                                    <div class="user-sub-text">Admin sign-in blocked</div>
                                </td>
                                <td>
                                    <div class="main-text">Security</div>
                                    <div class="sub-text">Unknown device · 203.0.113.24</div>
                                </td>
                                <td class="date-text">2h ago</td>
                                <td><span class="status-badge blocked"><span class="dot"></span> Blocked</span></td>
                                <td style="text-align: right;"><button class="action-link-btn" style="justify-content: flex-end;">View details <i class="fa-solid fa-arrow-right"></i></button></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <script src="../../../public/assets/js/modules/audit-logs.js"></script>
</body>
</html>