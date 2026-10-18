<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SOMI Delivery - Reviews Management</title>
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
    <a href="reviews.php" class="menu-item active">
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
                    <span class="subtitle">SERVICE QUALITY</span>
                    <div class="title-row">
                        <h2>Reviews</h2>
                        <button class="btn-export"><i class="fa-solid fa-chart-column"></i> Export reviews</button>
                    </div>
                    <p class="description">Moderate feedback and understand delivery quality across the network.</p>
                </div>

                <!-- Stats Grid 3 Cards -->
                <div class="stats-grid-3">
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fa-regular fa-star"></i></div>
                        <span class="stat-label">Average rating</span>
                        <div class="stat-value">4.86</div>
                        <span class="stat-sub">From 9,284 reviews</span>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fa-solid fa-check"></i></div>
                        <span class="stat-label">Published</span>
                        <div class="stat-value">9,241</div>
                        <span class="stat-sub text-success">99.5% approval</span>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
                        <span class="stat-label">Flagged</span>
                        <div class="stat-value">8</div>
                        <span class="stat-sub text-warning">Needs moderation</span>
                    </div>
                </div>

                <!-- Filter Tabs & Search Bar -->
                <div class="users-control-panel">
                    <div class="filter-tabs">
                        <button class="tab-btn active" onclick="filterReviewTable('all', this)">All <span class="tab-count">4</span></button>
                        <button class="tab-btn" onclick="filterReviewTable('published', this)">Published</button>
                        <button class="tab-btn" onclick="filterReviewTable('flagged', this)">Flagged</button>
                        <button class="tab-btn" onclick="filterReviewTable('hidden', this)">Hidden</button>
                    </div>

                    <div class="search-sort-row">
                        <div class="search-box">
                            <i class="fa-solid fa-filter"></i>
                            <input type="text" id="reviewSearchInput" placeholder="Search review, order, or author..." onkeyup="searchReviewTable()">
                        </div>
                        <div class="sort-dropdown">
                            <select id="reviewSortSelect">
                                <option>Newest first</option>
                                <option>Oldest first</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Reviews Data Table Card -->
                <div class="card users-table-card">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th style="width: 260px;">REVIEW / RATING</th>
                                <th>AUTHOR / SHIPPER</th>
                                <th style="width: 150px;">SUBMITTED</th>
                                <th style="width: 140px;">STATUS</th>
                                <th style="width: 130px; text-align: right;">ACTION</th>
                            </tr>
                        </thead>
                        <tbody id="reviewTableBody">
                            <tr data-status="published">
                                <td>
                                    <div class="user-id-text">REV-9284</div>
                                    <div class="user-sub-text">Fast and careful delivery</div>
                                </td>
                                <td>
                                    <div class="main-text">CUS-7102 → SHP-1284</div>
                                    <div class="sub-text">5 stars · Order SM-2478</div>
                                </td>
                                <td class="date-text">12 min ago</td>
                                <td><span class="status-badge published"><span class="dot"></span> Published</span></td>
                                <td style="text-align: right;"><button class="action-link-btn" style="justify-content: flex-end;">View details <i class="fa-solid fa-arrow-right"></i></button></td>
                            </tr>
                            <tr data-status="published">
                                <td>
                                    <div class="user-id-text">REV-9283</div>
                                    <div class="user-sub-text">Great communication</div>
                                </td>
                                <td>
                                    <div class="main-text">CUS-7098 → SHP-1283</div>
                                    <div class="sub-text">5 stars · Order SM-2476</div>
                                </td>
                                <td class="date-text">1h ago</td>
                                <td><span class="status-badge published"><span class="dot"></span> Published</span></td>
                                <td style="text-align: right;"><button class="action-link-btn" style="justify-content: flex-end;">View details <i class="fa-solid fa-arrow-right"></i></button></td>
                            </tr>
                            <tr data-status="flagged">
                                <td>
                                    <div class="user-id-text">REV-9282</div>
                                    <div class="user-sub-text">Package arrived late</div>
                                </td>
                                <td>
                                    <div class="main-text">CUS-7092 → SHP-1241</div>
                                    <div class="sub-text">2 stars · Order SM-2474</div>
                                </td>
                                <td class="date-text">3h ago</td>
                                <td><span class="status-badge flagged"><span class="dot"></span> Flagged</span></td>
                                <td style="text-align: right;"><button class="action-link-btn" style="justify-content: flex-end;">View details <i class="fa-solid fa-arrow-right"></i></button></td>
                            </tr>
                            <tr data-status="hidden">
                                <td>
                                    <div class="user-id-text">REV-9271</div>
                                    <div class="user-sub-text">Contains personal information</div>
                                </td>
                                <td>
                                    <div class="main-text">CUS-7041 → SHP-1282</div>
                                    <div class="sub-text">3 stars · Order SM-2431</div>
                                </td>
                                <td class="date-text">Jun 14</td>
                                <td><span class="status-badge hidden-status"><span class="dot"></span> Hidden</span></td>
                                <td style="text-align: right;"><button class="action-link-btn" style="justify-content: flex-end;">View details <i class="fa-solid fa-arrow-right"></i></button></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <script src="../../../public/assets/js/modules/reviews.js"></script>
</body>
</html>