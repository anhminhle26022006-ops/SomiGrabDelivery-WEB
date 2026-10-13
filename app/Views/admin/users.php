<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SOMI Delivery - Users Management</title>
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
                <a href="users.php" class="menu-item active">
                    <i class="fa-solid fa-user-group"></i> Users
                </a>
                <a href="#" class="menu-item">
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
                    <span class="subtitle">ACCESS & IDENTITY</span>
                    <div class="title-row">
                        <h2>Users</h2>
                        <button class="btn-export"><i class="fa-solid fa-chart-column"></i> Export accounts</button>
                    </div>
                    <p class="description">Manage registered customer and shipper accounts, access, and linked profiles.</p>
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
                        <span class="stat-label">Total users</span>
                        <div class="stat-value">8,426</div>
                        <span class="stat-trend positive">+214 this month</span>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fa-solid fa-check"></i></div>
                        <span class="stat-label">Active accounts</span>
                        <div class="stat-value">7,982</div>
                        <span class="stat-sub">94.7% of users</span>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
                        <span class="stat-label">Access reviews</span>
                        <div class="stat-value">18</div>
                        <span class="stat-sub text-warning">Requires attention</span>
                    </div>
                </div>

                <!-- Filter Tabs & Search Bar -->
                <div class="users-control-panel">
                    <div class="filter-tabs">
                        <button class="tab-btn active" onclick="filterTable('all', this)">All <span class="tab-count">4</span></button>
                        <button class="tab-btn" onclick="filterTable('active', this)">Active</button>
                        <button class="tab-btn" onclick="filterTable('pending', this)">Pending</button>
                        <button class="tab-btn" onclick="filterTable('suspended', this)">Suspended</button>
                    </div>

                    <div class="search-sort-row">
                        <div class="search-box">
                            <i class="fa-solid fa-filter"></i>
                            <input type="text" id="searchInput" placeholder="Search name, email, or user ID..." onkeyup="searchTable()">
                        </div>
                        <div class="sort-dropdown">
                            <select id="sortSelect">
                                <option>Newest first</option>
                                <option>Oldest first</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Users Data Table Card -->
                <div class="card users-table-card">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>USER</th>
                                <th>ROLE / CONTACT</th>
                                <th>JOINED</th>
                                <th>STATUS</th>
                                <th>ACTION</th>
                            </tr>
                        </thead>
                        <tbody id="userTableBody">
                            <tr data-status="active" data-userid="CUS-7102">
                                <td class="user-cell">
                                    <div class="user-id-text">CUS-7102</div>
                                    <div class="user-sub-text">Avery Kim</div>
                                </td>
                                <td>
                                    <div class="main-text">Customer · CUS-7102</div>
                                    <div class="sub-text">avery@example.com</div>
                                </td>
                                <td class="date-text">Jun 16, 2025</td>
                                <td><span class="status-badge active"><span class="dot"></span> Active</span></td>
                                <td><button class="action-link-btn" onclick="openModal('CUS-7102')">Manage account <i class="fa-solid fa-arrow-right"></i></button></td>
                            </tr>
                            <tr data-status="active" data-userid="SHP-1284">
                                <td class="user-cell">
                                    <div class="user-id-text">SHP-1284</div>
                                    <div class="user-sub-text">Marcus Lee</div>
                                </td>
                                <td>
                                    <div class="main-text">Shipper · SHP-1284</div>
                                    <div class="sub-text">marcus@example.com</div>
                                </td>
                                <td class="date-text">Jun 15, 2025</td>
                                <td><span class="status-badge active"><span class="dot"></span> Active</span></td>
                                <td><button class="action-link-btn" onclick="openModal('SHP-1284')">Manage account <i class="fa-solid fa-arrow-right"></i></button></td>
                            </tr>
                            <tr data-status="pending" data-userid="SHP-1283">
                                <td class="user-cell">
                                    <div class="user-id-text">SHP-1283</div>
                                    <div class="user-sub-text">Nina Park</div>
                                </td>
                                <td>
                                    <div class="main-text">Shipper · SHP-1283</div>
                                    <div class="sub-text">nina@example.com</div>
                                </td>
                                <td class="date-text">Jun 15, 2025</td>
                                <td><span class="status-badge pending"><span class="dot"></span> Pending</span></td>
                                <td><button class="action-link-btn" onclick="openModal('SHP-1283')">Manage account <i class="fa-solid fa-arrow-right"></i></button></td>
                            </tr>
                            <tr data-status="suspended" data-userid="CUS-7092">
                                <td class="user-cell">
                                    <div class="user-id-text">CUS-7092</div>
                                    <div class="user-sub-text">Jordan Bell</div>
                                </td>
                                <td>
                                    <div class="main-text">Customer · CUS-7092</div>
                                    <div class="sub-text">jordan@example.com</div>
                                </td>
                                <td class="date-text">Jun 12, 2025</td>
                                <td><span class="status-badge suspended"><span class="dot"></span> Suspended</span></td>
                                <td><button class="action-link-btn" onclick="openModal('CUS-7092')">Manage account <i class="fa-solid fa-arrow-right"></i></button></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- MODAL POPUP -->
    <div id="userModal" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-header">
                <h3 id="modalTitle">Users · CUS-7102</h3>
                <button class="modal-close" onclick="closeModal()"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="modal-body" id="modalBodyContent">
                <!-- Nội dung modal sẽ được render bằng JS tùy theo userId -->
            </div>
        </div>
    </div>

    <script src="../../../public/assets/js/modules/users.js"></script>
</body>
</html>