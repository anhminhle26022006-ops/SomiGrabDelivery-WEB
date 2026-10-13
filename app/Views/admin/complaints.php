<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SOMI Delivery - Complaint Management</title>
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
                <a href="complaints.php" class="menu-item active">
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
                    <div class="title-row" style="margin-top: 0;">
                        <h2 style="font-size: 28px; font-weight: 800; letter-spacing: -0.5px;">Complaint management</h2>
                        <button class="btn-export" style="padding: 10px 24px;">Export</button>
                    </div>
                    <p class="description" style="margin-top: 4px;">Review, investigate, and resolve reports fairly[cite: 20].</p>
                </div>

                <!-- Filter Tabs & Search Bar -->
                <div class="users-control-panel">
                    <div class="filter-tabs">
                        <button class="tab-btn active" onclick="filterComplaintTable('all', this)">All <span class="tab-count">124</span></button>
                        <button class="tab-btn" onclick="filterComplaintTable('open', this)">Open <span class="tab-count">14</span></button>
                        <button class="tab-btn" onclick="filterComplaintTable('reviewing', this)">Reviewing <span class="tab-count">10</span></button>
                        <button class="tab-btn" onclick="filterComplaintTable('resolved', this)">Resolved</button>
                        <button class="tab-btn" onclick="filterComplaintTable('rejected', this)">Rejected</button>
                    </div>

                    <div class="search-sort-row">
                        <div class="search-box">
                            <i class="fa-solid fa-filter"></i>
                            <input type="text" id="complaintSearchInput" placeholder="Search report or order ID..." onkeyup="searchComplaintTable()">
                        </div>
                        <div class="sort-dropdown">
                            <select id="problemTypeSelect" style="margin-right: 8px; height: 44px; border: 1px solid #e2e8f0; border-radius: 12px; padding: 0 16px; font-weight: 600; font-size: 13px; outline: none; background: #fff;">
                                <option>All problem types</option>
                                <option>Damaged Package</option>
                                <option>Payment Problem</option>
                                <option>Wrong Address</option>
                                <option>Delivery Problem</option>
                            </select>
                            <select id="complaintSortSelect" style="height: 44px; border: 1px solid #e2e8f0; border-radius: 12px; padding: 0 16px; font-weight: 600; font-size: 13px; outline: none; background: #fff;">
                                <option>Newest first</option>
                                <option>Oldest first</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Complaints Data Table Card -->
                <div class="card users-table-card">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th style="width: 130px;">REPORT ID</th>
                                <th style="width: 130px;">ORDER</th>
                                <th style="width: 220px;">REPORTER</th>
                                <th>TYPE</th>
                                <th style="width: 140px;">CREATED</th>
                                <th style="width: 130px;">STATUS</th>
                                <th style="width: 60px; text-align: right;"></th>
                            </tr>
                        </thead>
                        <tbody id="complaintTableBody">
                            <tr data-status="open">
                                <td class="user-id-text">RP-1042[cite: 20]</td>
                                <td class="date-text font-bold text-dark">#SM-2481[cite: 20]</td>
                                <td>
                                    <div class="reporter-cell">
                                        <div class="mini-avatar">AK</div>
                                        <span>Avery Kim[cite: 20]</span>
                                    </div>
                                </td>
                                <td><span class="main-text">Damaged Package[cite: 20]</span></td>
                                <td class="date-text">Jun 16, 2025[cite: 20]</td>
                                <td><span class="status-badge open"><span class="dot"></span> Open[cite: 20]</span></td>
                                <td style="text-align: right;"><i class="fa-solid fa-arrow-right" style="color: #94a3b8; font-size: 13px; cursor: pointer;"></i></td>
                            </tr>
                            <tr data-status="reviewing">
                                <td class="user-id-text">RP-1041[cite: 20]</td>
                                <td class="date-text font-bold text-dark">#SM-2479[cite: 20]</td>
                                <td>
                                    <div class="reporter-cell">
                                        <div class="mini-avatar" style="background-color: #e0f2fe; color: #0369a1;">MT</div>
                                        <span>Minh Tran[cite: 20]</span>
                                    </div>
                                </td>
                                <td><span class="main-text">Payment Problem[cite: 20]</span></td>
                                <td class="date-text">Jun 16, 2025[cite: 20]</td>
                                <td><span class="status-badge reviewing"><span class="dot"></span> Reviewing[cite: 20]</span></td>
                                <td style="text-align: right;"><i class="fa-solid fa-arrow-right" style="color: #94a3b8; font-size: 13px; cursor: pointer;"></i></td>
                            </tr>
                            <tr data-status="resolved">
                                <td class="user-id-text">RP-1040[cite: 20]</td>
                                <td class="date-text font-bold text-dark">#SM-2472[cite: 20]</td>
                                <td>
                                    <div class="reporter-cell">
                                        <div class="mini-avatar" style="background-color: #fce7f3; color: #db2777;">NP</div>
                                        <span>Nina Park[cite: 20]</span>
                                    </div>
                                </td>
                                <td><span class="main-text">Wrong Address[cite: 20]</span></td>
                                <td class="date-text">Jun 16, 2025[cite: 20]</td>
                                <td><span class="status-badge resolved"><span class="dot"></span> Resolved[cite: 20]</span></td>
                                <td style="text-align: right;"><i class="fa-solid fa-arrow-right" style="color: #94a3b8; font-size: 13px; cursor: pointer;"></i></td>
                            </tr>
                            <tr data-status="rejected">
                                <td class="user-id-text">RP-1039[cite: 20]</td>
                                <td class="date-text font-bold text-dark">#SM-2466[cite: 20]</td>
                                <td>
                                    <div class="reporter-cell">
                                        <div class="mini-avatar" style="background-color: #f1f5f9; color: #475569;">JB</div>
                                        <span>Jordan Bell[cite: 20]</span>
                                    </div>
                                </td>
                                <td><span class="main-text">Delivery Problem[cite: 20]</span></td>
                                <td class="date-text">Jun 16, 2025[cite: 20]</td>
                                <td><span class="status-badge rejected"><span class="dot"></span> Rejected[cite: 20]</span></td>
                                <td style="text-align: right;"><i class="fa-solid fa-arrow-right" style="color: #94a3b8; font-size: 13px; cursor: pointer;"></i></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <script src="../../../public/assets/js/modules/complaints.js"></script>
</body>
</html>