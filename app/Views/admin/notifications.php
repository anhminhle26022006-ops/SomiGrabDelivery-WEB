<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SOMI Delivery - Notifications Management</title>
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
    <a href="notifications.php" class="menu-item active">
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
                <div class="welcome-section" style="margin-bottom: 24px;">
                    <div class="title-row">
                        <h2>Notifications</h2>
                        <button class="btn-export" onclick="markAllRead()" style="background-color: #ffffff; border: 1px solid #cbd5e1; padding: 10px 24px; border-radius: 30px; font-weight: 600; font-size: 13px; color: #0f172a; cursor: pointer;">
                            Mark all as read
                        </button>
                    </div>
                    <p class="description" style="margin-top: 4px;">Important updates from across your Somi account.</p>
                </div>

                <!-- Filter Tabs -->
                <div class="users-control-panel" style="margin-bottom: 20px;">
                    <div class="filter-tabs">
                        <button class="tab-btn active" onclick="filterNotificationTab('all', this)">All</button>
                        <button class="tab-btn" onclick="filterNotificationTab('unread', this)">Unread <span class="tab-count">3</span></button>
                        <button class="tab-btn" onclick="filterNotificationTab('orders', this)">Orders</button>
                        <button class="tab-btn" onclick="filterNotificationTab('account', this)">Account</button>
                    </div>
                </div>

                <!-- Notifications List Card -->
                <div class="card notifications-card" style="padding: 12px 24px;">
                    <div class="notification-list" id="notificationList">
                        
                        <div class="notification-item unread" data-category="orders unread">
                            <div class="notif-icon-box">
                                <i class="fa-regular fa-bell"></i>
                            </div>
                            <div class="notif-content">
                                <h4>New complaint received</h4>
                                <p>Report RP-1042 for order SM-2481 needs review.</p>
                            </div>
                            <div class="notif-right">
                                <span class="notif-time">2 min</span>
                                <span class="unread-dot"></span>
                            </div>
                        </div>

                        <div class="notification-item unread" data-category="account unread">
                            <div class="notif-icon-box" style="background-color: #e0f2fe; color: #0369a1;">
                                <i class="fa-regular fa-credit-card"></i>
                            </div>
                            <div class="notif-content">
                                <h4>Shipper verification</h4>
                                <p>Nina Park submitted all required documents.</p>
                            </div>
                            <div class="notif-right">
                                <span class="notif-time">18 min</span>
                                <span class="unread-dot"></span>
                            </div>
                        </div>

                        <div class="notification-item unread" data-category="orders unread">
                            <div class="notif-icon-box">
                                <i class="fa-regular fa-bell"></i>
                            </div>
                            <div class="notif-content">
                                <h4>Payment issue</h4>
                                <p>Payment PM-8142 requires reconciliation.</p>
                            </div>
                            <div class="notif-right">
                                <span class="notif-time">1 hr</span>
                                <span class="unread-dot"></span>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="../../../public/assets/js/modules/notifications.js"></script>
</body>
</html>