<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SOMI Delivery - Admin Console</title>
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
    <a href="index.php" class="menu-item active">
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
                <div class="header-left"></div>
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
                <!-- Welcome Banner -->
                <div class="welcome-section">
                    <span class="subtitle">OPERATIONS OVERVIEW</span>
                    <div class="title-row">
                        <h2>Good morning, Alex.</h2>
                        <div class="action-buttons">
                            <span class="live-indicator"><span class="green-dot"></span> LIVE</span>
                            <button class="btn-export"><i class="fa-solid fa-arrow-up-right-from-square"></i> Export report</button>
                        </div>
                    </div>
                    <p class="description">Here's the pulse of the Somi network today.</p>
                </div>

                <!-- Stats Grid 3x2 -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fa-regular fa-file-lines"></i></div>
                        <span class="stat-label">Total orders</span>
                        <div class="stat-value">12,684</div>
                        <span class="stat-trend positive">+8.2% this month</span>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fa-solid fa-check"></i></div>
                        <span class="stat-label">Completed</span>
                        <div class="stat-value">11,402</div>
                        <span class="stat-sub">89.9% completion</span>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fa-solid fa-user-group"></i></div>
                        <span class="stat-label">Active shippers</span>
                        <div class="stat-value">1,284</div>
                        <span class="stat-sub">842 online now</span>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
                        <span class="stat-label">Pending complaints</span>
                        <div class="stat-value">24</div>
                        <span class="stat-trend warning">6 high priority</span>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fa-solid fa-dollar-sign"></i></div>
                        <span class="stat-label">Revenue</span>
                        <div class="stat-value">$184.2K</div>
                        <span class="stat-trend positive">+12.6% this month</span>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
                        <span class="stat-label">Failed deliveries</span>
                        <div class="stat-value">86</div>
                        <span class="stat-sub">0.7% of orders</span>
                    </div>
                </div>

                <!-- Section: Recent Orders & Needs Attention -->
                <div class="content-row">
                    <!-- Recent Orders Table -->
                    <div class="card recent-orders-card">
                        <div class="card-header">
                            <h3>Recent orders</h3>
                            <a href="#" class="view-all">View all <i class="fa-solid fa-arrow-right"></i></a>
                        </div>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>ORDER</th>
                                    <th>ROUTE</th>
                                    <th>SHIPPER</th>
                                    <th>STATUS</th>
                                    <th>TOTAL</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="order-id">#SM-2484</td>
                                    <td>King Blvd <i class="fa-solid fa-arrow-right-long route-arrow"></i> Oak Rd</td>
                                    <td>Marcus Lee</td>
                                    <td><span class="status-badge picking">Picking up</span></td>
                                    <td class="total-price">$14.60</td>
                                </tr>
                                <tr>
                                    <td class="order-id">#SM-2483</td>
                                    <td>Market St <i class="fa-solid fa-arrow-right-long route-arrow"></i> Pine Ave</td>
                                    <td>Nina Park</td>
                                    <td><span class="status-badge transit">In transit</span></td>
                                    <td class="total-price">$14.60</td>
                                </tr>
                                <tr>
                                    <td class="order-id">#SM-2482</td>
                                    <td>Old Town <i class="fa-solid fa-arrow-right-long route-arrow"></i> Lakeside</td>
                                    <td class="dash">—</td>
                                    <td><span class="status-badge matching">Matching</span></td>
                                    <td class="total-price">$14.60</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Needs Attention Sidebar Card -->
                    <div class="card needs-attention-card">
                        <div class="card-header">
                            <h3>Needs attention</h3>
                        </div>
                        <div class="attention-list">
                            <div class="attention-item">
                                <div class="att-icon red-bg"><i class="fa-solid fa-triangle-exclamation"></i></div>
                                <div class="att-info">
                                    <h4>6 priority complaints</h4>
                                    <p>Oldest waiting 3h 24m</p>
                                </div>
                                <i class="fa-solid fa-arrow-right"></i>
                            </div>
                            <div class="attention-item">
                                <div class="att-icon orange-bg"><i class="fa-solid fa-user-group"></i></div>
                                <div class="att-info">
                                    <h4>18 verification requests</h4>
                                    <p>4 submitted today</p>
                                </div>
                                <i class="fa-solid fa-arrow-right"></i>
                            </div>
                            <div class="attention-item">
                                <div class="att-icon blue-bg"><i class="fa-solid fa-wallet"></i></div>
                                <div class="att-info">
                                    <h4>3 payment issues</h4>
                                    <p>Requires reconciliation</p>
                                </div>
                                <i class="fa-solid fa-arrow-right"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section: Recent Complaints & Recent Activity -->
                <div class="content-row">
                    <!-- Recent Complaints -->
                    <div class="card recent-complaints-card">
                        <div class="card-header">
                            <h3>Recent complaints</h3>
                            <a href="#" class="manage-all">Manage all</a>
                        </div>
                        <div class="complaints-list">
                            <div class="complaint-row">
                                <div class="comp-id">RP-1042</div>
                                <div class="comp-detail">
                                    <h4>Damaged package</h4>
                                    <p>Reported by Avery Kim</p>
                                </div>
                                <span class="status-badge open">Open</span>
                                <span class="comp-time">Today, 10:42</span>
                                <i class="fa-solid fa-arrow-right"></i>
                            </div>
                            <div class="complaint-row">
                                <div class="comp-id">RP-1041</div>
                                <div class="comp-detail">
                                    <h4>Payment problem</h4>
                                    <p>Reported by Minh Tran</p>
                                </div>
                                <span class="status-badge reviewing">Reviewing</span>
                                <span class="comp-time">Today, 10:42</span>
                                <i class="fa-solid fa-arrow-right"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Activity -->
                    <div class="card recent-activity-card">
                        <div class="card-header">
                            <h3>Recent activity</h3>
                        </div>
                        <div class="activity-timeline">
                            <div class="activity-item">
                                <span class="dot-marker"></span>
                                <div class="activity-content">
                                    <p>Order <strong>#SM-2484</strong> was accepted by <strong>Marcus Lee</strong></p>
                                    <span class="time">2 minutes ago</span>
                                </div>
                            </div>
                            <div class="activity-item">
                                <span class="dot-marker"></span>
                                <div class="activity-content">
                                    <p>Shipper verification approved for <strong>Nina Park</strong></p>
                                    <span class="time">12 minutes ago</span>
                                </div>
                            </div>
                            <div class="activity-item">
                                <span class="dot-marker"></span>
                                <div class="activity-content">
                                    <p>Complaint <strong>#RP-1038</strong> was resolved</p>
                                    <span class="time">28 minutes ago</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </main>
    </div>
</body>
</html>