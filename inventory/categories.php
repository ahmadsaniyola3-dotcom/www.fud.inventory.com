<?php
/**
 * Digital Inventory Management System for Computer Laboratories
 * B.Sc. Computer Science Final Year Project
 * 
 * File: categories.php
 * Path: C:\laragon\www\inventory\categories.php
 * Description: Category Management Module (Add, Edit, Delete, Search) using MySQLi Prepared Statements
 */

// Step 1: Start Session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Step 2: Include Configuration (defines $conn MySQLi connection)
require_once 'config.php';

// Step 3: Check Authentication - Redirect to login.php if not logged in
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// User Info
$user_full_name = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Administrator';

$success_message = '';
$error_message = '';

// Retrieve session flash messages if set
if (isset($_SESSION['success'])) {
    $success_message = $_SESSION['success'];
    unset($_SESSION['success']);
}
if (isset($_SESSION['error'])) {
    $error_message = $_SESSION['error'];
    unset($_SESSION['error']);
}

// ==========================================
// HANDLE FORM SUBMISSIONS (ADD & EDIT)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // --- ADD CATEGORY ---
    if ($action === 'add') {
        $category_name = trim($_POST['category_name'] ?? '');
        $description   = trim($_POST['description'] ?? '');

        if (empty($category_name)) {
            $_SESSION['error'] = "Category name is required.";
        } else {
            // Check for duplicate category name using MySQLi prepared statement
            $checkStmt = $conn->prepare("SELECT id FROM categories WHERE category_name = ? LIMIT 1");
            $checkStmt->bind_param("s", $category_name);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();

            if ($checkResult && $checkResult->num_rows > 0) {
                $_SESSION['error'] = "Category '$category_name' already exists!";
            } else {
                // Insert new category
                $insertStmt = $conn->prepare("INSERT INTO categories (category_name, description) VALUES (?, ?)");
                $insertStmt->bind_param("ss", $category_name, $description);

                if ($insertStmt->execute()) {
                    $_SESSION['success'] = "Category '$category_name' added successfully!";
                } else {
                    $_SESSION['error'] = "Failed to add category: " . $conn->error;
                }
                $insertStmt->close();
            }
            $checkStmt->close();
        }
        header("Location: categories.php");
        exit();
    }

    // --- EDIT CATEGORY ---
    if ($action === 'edit') {
        $id            = (int)($_POST['id'] ?? 0);
        $category_name = trim($_POST['category_name'] ?? '');
        $description   = trim($_POST['description'] ?? '');

        if ($id <= 0 || empty($category_name)) {
            $_SESSION['error'] = "Invalid category details provided.";
        } else {
            // Check if duplicate category name exists under a different ID
            $checkStmt = $conn->prepare("SELECT id FROM categories WHERE category_name = ? AND id != ? LIMIT 1");
            $checkStmt->bind_param("si", $category_name, $id);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();

            if ($checkResult && $checkResult->num_rows > 0) {
                $_SESSION['error'] = "Another category with name '$category_name' already exists!";
            } else {
                // Update category
                $updateStmt = $conn->prepare("UPDATE categories SET category_name = ?, description = ? WHERE id = ?");
                $updateStmt->bind_param("ssi", $category_name, $description, $id);

                if ($updateStmt->execute()) {
                    $_SESSION['success'] = "Category updated successfully!";
                } else {
                    $_SESSION['error'] = "Failed to update category: " . $conn->error;
                }
                $updateStmt->close();
            }
            $checkStmt->close();
        }
        header("Location: categories.php");
        exit();
    }
}

// ==========================================
// HANDLE DELETE ACTION (GET)
// ==========================================
if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    $delete_id = (int)($_GET['id'] ?? 0);

    if ($delete_id > 0) {
        // Delete category using prepared statement
        $deleteStmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
        $deleteStmt->bind_param("i", $delete_id);

        if ($deleteStmt->execute()) {
            $_SESSION['success'] = "Category deleted successfully!";
        } else {
            $_SESSION['error'] = "Cannot delete category: It may contain inventory items.";
        }
        $deleteStmt->close();
    }
    header("Location: categories.php");
    exit();
}

// ==========================================
// SEARCH & FETCH CATEGORIES
// ==========================================
$search_term = trim($_GET['search'] ?? '');
$categories = [];

if (!empty($search_term)) {
    $search_param = "%" . $search_term . "%";
    $stmt = $conn->prepare("SELECT * FROM categories WHERE category_name LIKE ? OR description LIKE ? ORDER BY id DESC");
    $stmt->bind_param("ss", $search_param, $search_param);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query("SELECT * FROM categories ORDER BY id DESC");
}

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categories - Lab Inventory Management System</title>
    
    <!-- Bootstrap 5 CSS CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Google Fonts (Inter) -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --sidebar-width: 260px;
            --primary-color: #2563eb;
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

        /* Sidebar Styling */
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

        /* Main Content Layout */
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
            flex-wrap: wrap;
            gap: 15px;
        }

        .table > :not(caption) > * > * {
            padding: 14px 20px;
            vertical-align: middle;
        }

        .modal-content {
            border-radius: 16px;
            border: none;
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }

        .modal-header {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            color: #ffffff;
            border-top-left-radius: 16px;
            border-top-right-radius: 16px;
        }

        .modal-header .btn-close {
            filter: brightness(0) invert(1);
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
                <a href="dashboard.php" class="nav-link">
                    <i class="bi bi-grid-1x2-fill"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="categories.php" class="nav-link active">
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
                <h5 class="fw-bold mb-0 text-dark">Equipment Categories</h5>
                <small class="text-muted">Manage equipment category classifications</small>
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

        <!-- Success & Error Alert Messages -->
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show d-flex align-items-center mb-4" role="alert">
                <i class="bi bi-check-circle-fill me-2 fs-5"></i>
                <div><?php echo htmlspecialchars($success_message); ?></div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center mb-4" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                <div><?php echo htmlspecialchars($error_message); ?></div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Categories Content Card -->
        <div class="content-card">
            <div class="content-card-header">
                <!-- Search Box -->
                <form action="categories.php" method="GET" class="d-flex gap-2" style="max-width: 380px; width: 100%;">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" 
                               name="search" 
                               class="form-control border-start-0 bg-light" 
                               placeholder="Search categories..." 
                               value="<?php echo htmlspecialchars($search_term); ?>">
                    </div>
                    <?php if (!empty($search_term)): ?>
                        <a href="categories.php" class="btn btn-outline-secondary" title="Clear Search">
                            <i class="bi bi-x-lg"></i>
                        </a>
                    <?php endif; ?>
                    <button type="submit" class="btn btn-secondary">Search</button>
                </form>

                <!-- Add Category Button (Triggers Modal) -->
                <button type="button" class="btn btn-primary rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                    <i class="bi bi-plus-lg me-1"></i> Add Category
                </button>
            </div>

            <!-- Categories Table -->
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 70px;">#ID</th>
                            <th>Category Name</th>
                            <th>Description</th>
                            <th>Date Created</th>
                            <th class="text-end" style="width: 150px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($categories)): ?>
                            <?php foreach ($categories as $cat): ?>
                                <tr>
                                    <td class="fw-bold text-secondary">#<?php echo $cat['id']; ?></td>
                                    <td class="fw-semibold text-dark">
                                        <i class="bi bi-folder2 me-2 text-primary"></i>
                                        <?php echo htmlspecialchars($cat['category_name']); ?>
                                    </td>
                                    <td class="text-muted">
                                        <?php echo !empty($cat['description']) ? htmlspecialchars($cat['description']) : '<span class="fst-italic text-black-50">No description</span>'; ?>
                                    </td>
                                    <td class="small text-muted">
                                        <?php echo date('M d, Y', strtotime($cat['created_at'])); ?>
                                    </td>
                                    <td class="text-end">
                                        <!-- Edit Button (Triggers Modal) -->
                                        <button type="button" 
                                                class="btn btn-sm btn-outline-primary me-1 rounded-circle" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#editCategoryModal<?php echo $cat['id']; ?>"
                                                title="Edit Category">
                                            <i class="bi bi-pencil-fill"></i>
                                        </button>

                                        <!-- Delete Button with JS Confirmation -->
                                        <a href="categories.php?action=delete&id=<?php echo $cat['id']; ?>" 
                                           class="btn btn-sm btn-outline-danger rounded-circle" 
                                           onclick="return confirm('Are you sure you want to delete category \'<?php echo addslashes($cat['category_name']); ?>\'?');"
                                           title="Delete Category">
                                            <i class="bi bi-trash-fill"></i>
                                        </a>

                                        <!-- EDIT CATEGORY MODAL -->
                                        <div class="modal fade text-start" id="editCategoryModal<?php echo $cat['id']; ?>" tabindex="-1" aria-hidden="true">
                                            <div class="modal-dialog modal-dialog-centered">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2"></i>Edit Category</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <form action="categories.php" method="POST">
                                                        <div class="modal-body">
                                                            <input type="hidden" name="action" value="edit">
                                                            <input type="hidden" name="id" value="<?php echo $cat['id']; ?>">
                                                            
                                                            <div class="mb-3">
                                                                <label class="form-label fw-semibold">Category Name <span class="text-danger">*</span></label>
                                                                <input type="text" 
                                                                       name="category_name" 
                                                                       class="form-control" 
                                                                       value="<?php echo htmlspecialchars($cat['category_name']); ?>" 
                                                                       required>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label fw-semibold">Description</label>
                                                                <textarea name="description" 
                                                                          class="form-control" 
                                                                          rows="3" 
                                                                          placeholder="Enter category description..."><?php echo htmlspecialchars($cat['description'] ?? ''); ?></textarea>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                                                            <button type="submit" class="btn btn-primary px-4">Update Category</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">
                                    <i class="bi bi-folder-x fs-3 d-block mb-2"></i>
                                    No categories found. Click "Add Category" to create your first category.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- ADD CATEGORY MODAL -->
    <div class="modal fade" id="addCategoryModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="bi bi-folder-plus me-2"></i>Add New Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="categories.php" method="POST" autocomplete="off">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        
                        <div class="mb-3">
                            <label for="category_name" class="form-label fw-semibold">Category Name <span class="text-danger">*</span></label>
                            <input type="text" 
                                   name="category_name" 
                                   id="category_name" 
                                   class="form-control" 
                                   placeholder="e.g. Desktop Computers, Monitors, Routers" 
                                   required>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label fw-semibold">Description (Optional)</label>
                            <textarea name="description" 
                                      id="description" 
                                      class="form-control" 
                                      rows="3" 
                                      placeholder="Brief details about equipment in this category..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary px-4">Save Category</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JavaScript Bundle CDN -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
  </htmL>