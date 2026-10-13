<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SOMI Delivery - Disputes Management</title>
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
                <a href="disputes.php" class="menu-item active">
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
                    <span class="subtitle">TRUST & SAFETY</span>
                    <div class="title-row">
                        <h2>Disputes</h2>
                        <button class="btn-export" style="background-color: #0b1f44; color: #ffffff; border: none; padding: 10px 24px; border-radius: 30px; font-weight: 600; font-size: 13px; cursor: pointer; display: flex; align-items: center; gap: 8px;">
                            <i class="fa-solid fa-arrow-right"></i> Open dispute
                        </button>
                    </div>
                    <p class="description">Investigate contested deliveries and reach a fair resolution.</p>
                </div>

                <!-- Stats Grid 3 Cards -->
                <div class="stats-grid-3">
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
                        <span class="stat-label">Open disputes</span>
                        <div class="stat-value">12</div>
                        <span class="stat-trend warning">3 high priority</span>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fa-regular fa-clock"></i></div>
                        <span class="stat-label">Avg. resolution</span>
                        <div class="stat-value">18.4h</div>
                        <span class="stat-sub text-success">-2.1h this month</span>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fa-solid fa-check"></i></div>
                        <span class="stat-label">Resolved this month</span>
                        <div class="stat-value">84</div>
                        <span class="stat-sub">91% within SLA</span>
                    </div>
                </div>

                <!-- Filter Tabs & Search Bar -->
                <div class="users-control-panel">
                    <div class="filter-tabs">
                        <button class="tab-btn active" onclick="filterDisputeTable('all', this)">All <span class="tab-count">4</span></button>
                        <button class="tab-btn" onclick="filterDisputeTable('open', this)">Open</button>
                        <button class="tab-btn" onclick="filterDisputeTable('investigating', this)">Investigating</button>
                        <button class="tab-btn" onclick="filterDisputeTable('resolved', this)">Resolved</button>
                    </div>

                    <div class="search-sort-row">
                        <div class="search-box">
                            <i class="fa-solid fa-filter"></i>
                            <input type="text" id="disputeSearchInput" placeholder="Search dispute, order, or participant..." onkeyup="searchDisputeTable()">
                        </div>
                        <div class="sort-dropdown">
                            <select id="disputeSortSelect">
                                <option>Newest first</option>
                                <option>Oldest first</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Disputes Data Table Card -->
                <div class="card users-table-card">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th style="width: 220px;">DISPUTE / REASON</th>
                                <th>PARTIES</th>
                                <th style="width: 140px;">OPENED</th>
                                <th style="width: 140px;">STATUS</th>
                                <th style="width: 130px; text-align: right;">ACTION</th>
                            </tr>
                        </thead>
                        <tbody id="disputeTableBody">
                            <tr data-status="open">
                                <td>
                                    <div class="user-id-text">DSP-306</div>
                                    <div class="user-sub-text">Missing package</div>
                                </td>
                                <td>
                                    <div class="main-text">CUS-7102 / SHP-1284</div>
                                    <div class="sub-text">Order SM-2471</div>
                                </td>
                                <td class="date-text">2h ago</td>
                                <td><span class="status-badge open"><span class="dot"></span> Open</span></td>
                                <td style="text-align: right;"><button class="action-link-btn" style="justify-content: flex-end;">View details <i class="fa-solid fa-arrow-right"></i></button></td>
                            </tr>
                            <tr data-status="investigating">
                                <td>
                                    <div class="user-id-text">DSP-305</div>
                                    <div class="user-sub-text">Incorrect delivery fee</div>
                                </td>
                                <td>
                                    <div class="main-text">CUS-7098 / SOMI</div>
                                    <div class="sub-text">Order SM-2468</div>
                                </td>
                                <td class="date-text">5h ago</td>
                                <td><span class="status-badge investigating"><span class="dot"></span> Investigating</span></td>
                                <td style="text-align: right;"><button class="action-link-btn" style="justify-content: flex-end;">View details <i class="fa-solid fa-arrow-right"></i></button></td>
                            </tr>
                            <tr data-status="investigating">
                                <td>
                                    <div class="user-id-text">DSP-304</div>
                                    <div class="user-sub-text">Recipient unavailable</div>
                                </td>
                                <td>
                                    <div class="main-text">CUS-7092 / SHP-1283</div>
                                    <div class="sub-text">Order SM-2452</div>
                                </td>
                                <td class="date-text">Yesterday</td>
                                <td><span class="status-badge investigating"><span class="dot"></span> Investigating</span></td>
                                <td style="text-align: right;"><button class="action-link-btn" style="justify-content: flex-end;">View details <i class="fa-solid fa-arrow-right"></i></button></td>
                            </tr>
                            <tr data-status="resolved">
                                <td>
                                    <div class="user-id-text">DSP-299</div>
                                    <div class="user-sub-text">Damaged item</div>
                                </td>
                                <td>
                                    <div class="main-text">CUS-7041 / SHP-1241</div>
                                    <div class="sub-text">Order SM-2418</div>
                                </td>
                                <td class="date-text">Jun 12</td>
                                <td><span class="status-badge resolved"><span class="dot"></span> Resolved</span></td>
                                <td style="text-align: right;"><button class="action-link-btn" style="justify-content: flex-end;">View details <i class="fa-solid fa-arrow-right"></i></button></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <script src="../../../public/assets/js/modules/disputes.js"></script>
</body>
</html>