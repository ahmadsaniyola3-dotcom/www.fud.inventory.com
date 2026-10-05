<?php
/**
 * Digital Inventory Management System for Computer Laboratories
 * B.Sc. Computer Science Final Year Project
 * 
 * File: dashboard.php
 * Path: C:\laragon\www\inventory\dashboard.php
 * Description: Admin Dashboard with Summary Metrics & Recent Inventory (Pure MySQLi)
 */
// Step 1: Start PHP session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Step 2: Include configuration file (defines MySQLi connection $conn)
require_once 'config.php';

// Step 3 & 4: Authentication check - redirect to login.php if unauthenticated
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
// Step 5: Get logged-in user's name from session
$user_full_name = isset($_SESSION['full_name']) && !empty($_SESSION['full_name']) 
    ? $_SESSION['full_name'] 
    : (isset($_SESSION['username']) ? $_SESSION['username'] : 'Administrator');
// Step 6: Calculate metrics using MySQLi ($conn)
// Metric 1: Total Item Types
$totalItemTypes = 0;
$resTypes = $conn->query("SELECT COUNT(id) AS total_types FROM inventory");
if ($resTypes && $rowTypes = $resTypes->fetch_assoc()) {
    $totalItemTypes = (int)$rowTypes['total_types'];
}
// Metric 2, 3, 4: Total Equipment, Working, and Damaged Quantities
$totalEquipmentQty = 0;
$totalWorkingQty   = 0;
$totalDamagedQty   = 0;
$resQty = $conn->query("SELECT 
                            COALESCE(SUM(total_qty), 0) AS total_eq, 
                            COALESCE(SUM(working_qty), 0) AS working_eq, 
                            COALESCE(SUM(damaged_qty), 0) AS damaged_eq 
                        FROM inventory");
if ($resQty && $rowQty = $resQty->fetch_assoc()) {
    $totalEquipmentQty = (int)$rowQty['total_eq'];
    $totalWorkingQty   = (int)$rowQty['working_eq'];
    $totalDamagedQty   = (int)$rowQty['damaged_eq'];
}
// Metric 5: Total Categories
$totalCategories = 0;
$resCats = $conn->query("SELECT COUNT(id) AS total_cats FROM categories");
if ($resCats && $rowCats = $resCats->fetch_assoc()) {
    $totalCategories = (int)$rowCats['total_cats'];
}
// Step 7: Retrieve 5 latest inventory records
$recentItemsList = [];
$resRecent = $conn->query("SELECT i.id, i.item_name, i.brand, i.model, i.total_qty, i.working_qty, i.damaged_qty, i.location, i.date_added, c.category_name 
                           FROM inventory i 
                           LEFT JOIN categories c ON i.category_id = c.id 
                           ORDER BY i.id DESC LIMIT 5");
if ($resRecent) {
    while ($rowRecent = $resRecent->fetch_assoc()) {
        $recentItemsList[] = $rowRecent;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Lab Inventory Management System</title>
    
    <!-- Bootstrap 5 CSS CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Google Fonts (Inter) -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --sidebar-width: 260px;
            --primary-color: #25375e;
            --dark-bg: #0f172a;
            --light-bg: #f8fafc;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--light-bg);
            color: #334155;
            overflow-x: hidden;
            margin: 0;
        }

        /* Sidebar Navigation Styling */
        .sidebar {
            width: var(--sidebar-width);
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            background-color: var(--dark-bg);
            color: #ffffff;
            z-index: 1000;
            transition: all 0.3s ease;
            box-shadow: 4px 0 10px rgba(0, 0, 0, 0.1);
        }

        .sidebar-brand {
            padding: 25px 20px;
            font-size: 1.15rem;
            font-weight: 700;
            color: #ffffff;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .sidebar-brand i {
            font-size: 1.5rem;
            color: #38bdf8;
        }

        .sidebar-menu {
            padding: 20px 10px;
            list-style: none;
            margin: 0;
        }

        .sidebar-menu .nav-item {
            margin-bottom: 5px;
        }

        .sidebar-menu .nav-link {
            color: #94a3b8;
            padding: 12px 16px;
            border-radius: 10px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: all 0.2s ease;
            text-decoration: none;
        }

        .sidebar-menu .nav-link:hover,
        .sidebar-menu .nav-link.active {
            color: #ffffff;
            background-color: var(--primary-color);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        }

        .sidebar-menu .nav-link i {
            font-size: 1.2rem;
        }

        /* Main Dashboard Content Layout */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 25px 30px;
            min-height: 100vh;
        }

        /* Top Navbar */
        .top-navbar {
            background: #ffffff;
            border-radius: 14px;
            padding: 15px 25px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        /* Summary Stat Cards */
        .stat-card {
            background: #ffffff;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
            border: 1px solid #f1f5f9;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            height: 100%;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
        }

        .stat-icon {
            width: 54px;
            height: 54px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
            margin-bottom: 15px;
        }

        .stat-icon.primary { background: #dbeafe; color: #2563eb; }
        .stat-icon.info    { background: #e0f2fe; color: #0284c7; }
        .stat-icon.success { background: #dcfce7; color: #16a34a; }
        .stat-icon.danger  { background: #fee2e2; color: #dc2626; }
        .stat-icon.warning { background: #fef3c7; color: #d97706; }

        .stat-value {
            font-size: 1.85rem;
            font-weight: 700;
            color: #0f172a;
            line-height: 1.2;
        }

        .stat-label {
            color: #64748b;
            font-size: 0.875rem;
            font-weight: 500;
            margin-top: 4px;
        }

        /* Table Card */
        .content-card {
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
            border: 1px solid #f1f5f9;
            overflow: hidden;
        }

        .content-card-header {
            padding: 20px 25px;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .table > :not(caption) > * > * {
            padding: 14px 20px;
            vertical-align: middle;
        }

        .badge-working {
            background-color: #dcfce7;
            color: #15803d;
            font-weight: 600;
            padding: 6px 12px;
            border-radius: 20px;
        }

        .badge-damaged {
            background-color: #fee2e2;
            color: #b91c1c;
            font-weight: 600;
            padding: 6px 12px;
            border-radius: 20px;
        }

        @media (max-width: 992px) {
            .sidebar { margin-left: calc(-1 * var(--sidebar-width)); }
            .main-content { margin-left: 0; }
        }
    </style>
</head>
<body>

    <!-- Left Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-brand">
            <i class="bi bi-cpu-fill"></i>
            <span>Lab Inventory</span>
        </div>
        <ul class="sidebar-menu">
            <li class="nav-item">
                <a href="dashboard.php" class="nav-link active">
                    <i class="bi bi-grid-1x2-fill"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="categories.php" class="nav-link">
                    <i class="bi bi-tags-fill"></i>
                    <span>Categories</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="inventory.php" class="nav-link">
                    <i class="bi bi-box-seam-fill"></i>
                    <span>Inventory</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="reports.php" class="nav-link">
                    <i class="bi bi-file-earmark-bar-graph-fill"></i>
                    <span>Reports</span>
                </a>
            </li>
            <li class="nav-item mt-4">
                <a href="logout.php" class="nav-link text-danger">
                    <i class="bi bi-box-arrow-right"></i>
                    <span>Logout</span>
                </a>
            </li>
        </ul>
    </aside>

    <!-- Main Content Area -->
    <main class="main-content">
        <!-- Top Navbar -->
        <header class="top-navbar">
            <div>
                <h5 class="fw-bold mb-0 text-dark">Computer Laboratory Overview</h5>
                <small class="text-muted">Welcome back, <?php echo htmlspecialchars($user_full_name); ?></small>
            </div>
            <div class="d-flex align-items-center gap-3">
                <span class="badge bg-primary-subtle text-primary px-3 py-2 rounded-pill">
                    <i class="bi bi-person-circle me-1"></i> Admin
                </span>
                <a href="logout.php" class="btn btn-outline-danger btn-sm rounded-pill px-3">
                    <i class="bi bi-box-arrow-right me-1"></i> Logout
                </a>
            </div>
        </header>

        <!-- Summary Metric Cards Row -->
        <div class="row g-4 mb-4">
            <!-- Card 1: Total Item Types -->
            <div class="col-xl-2 col-md-4 col-sm-6">
                <div class="stat-card">
                    <div class="stat-icon primary">
                        <i class="bi bi-boxes"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($totalItemTypes); ?></div>
                    <div class="stat-label">Total Item Types</div>
                </div>
            </div>

            <!-- Card 2: Total Equipment Quantity -->
            <div class="col-xl-3 col-md-4 col-sm-6">
                <div class="stat-card">
                    <div class="stat-icon info">
                        <i class="bi bi-pc-display"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($totalEquipmentQty); ?></div>
                    <div class="stat-label">Total Equipment Qty</div>
                </div>
            </div>

            <!-- Card 3: Total Working Equipment -->
            <div class="col-xl-2 col-md-4 col-sm-6">
                <div class="stat-card">
                    <div class="stat-icon success">
                        <i class="bi bi-check-circle-fill"></i>
                    </div>
                    <div class="stat-value text-success"><?php echo number_format($totalWorkingQty); ?></div>
                    <div class="stat-label">Working Units</div>
                </div>
            </div>

            <!-- Card 4: Total Damaged Equipment -->
            <div class="col-xl-2 col-md-4 col-sm-6">
                <div class="stat-card">
                    <div class="stat-icon danger">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                    </div>
                    <div class="stat-value text-danger"><?php echo number_format($totalDamagedQty); ?></div>
                    <div class="stat-label">Damaged Units</div>
                </div>
            </div>

            <!-- Card 5: Total Categories -->
            <div class="col-xl-3 col-md-4 col-sm-6">
                <div class="stat-card">
                    <div class="stat-icon warning">
                        <i class="bi bi-folder-fill"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($totalCategories); ?></div>
                    <div class="stat-label">Active Categories</div>
                </div>
            </div>
        </div>

        <!-- Latest Inventory Items Table Card -->
        <div class="content-card">
            <div class="content-card-header">
                <div>
                    <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-clock-history me-2 text-primary"></i>Recent Inventory Entries</h6>
                    <small class="text-muted">Latest equipment added to the system</small>
                </div>
                <a href="inventory.php" class="btn btn-primary btn-sm rounded-pill px-3">
                    <i class="bi bi-plus-lg me-1"></i> View All Items
                </a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Item Name</th>
                            <th>Category</th>
                            <th>Brand / Model</th>
                            <th class="text-center">Total Qty</th>
                            <th class="text-center">Working</th>
                            <th class="text-center">Damaged</th>
                            <th>Location</th>
                            <th>Date Added</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($recentItemsList)): ?>
                            <?php foreach ($recentItemsList as $item): ?>
                                <tr>
                                    <td class="fw-semibold text-dark">
                                        <?php echo htmlspecialchars($item['item_name']); ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary-subtle text-secondary px-2 py-1 rounded">
                                            <?php echo htmlspecialchars($item['category_name'] ?? 'Uncategorized'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($item['brand']); ?> 
                                        <?php if (!empty($item['model'])): ?>
                                            <small class="text-muted">(<?php echo htmlspecialchars($item['model']); ?>)</small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center fw-bold"><?php echo (int)$item['total_qty']; ?></td>
                                    <td class="text-center">
                                        <span class="badge-working"><?php echo (int)$item['working_qty']; ?></span>
                                    </td>
                                    <td class="text-center">
                                        <?php if ((int)$item['damaged_qty'] > 0): ?>
                                            <span class="badge-damaged"><?php echo (int)$item['damaged_qty']; ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">0</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <i class="bi bi-geo-alt me-1 text-danger"></i>
                                        <?php echo htmlspecialchars($item['location']); ?>
                                    </td>
                                    <td class="text-muted small">
                                        <?php echo date('M d, Y', strtotime($item['date_added'])); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">
                                    <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                                    No inventory records found in the database.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Bootstrap 5 JavaScript Bundle CDN -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>