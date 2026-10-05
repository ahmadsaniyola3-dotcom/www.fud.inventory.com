<?php
/**
 * Digital Inventory Management System
 * File: inventory.php
 * Description: Inventory Management Module
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config.php';

/* Authentication */
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_full_name = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Administrator';

$success_message = '';
$error_message = '';

/* Flash messages */
if (isset($_SESSION['success'])) {
    $success_message = $_SESSION['success'];
    unset($_SESSION['success']);
}

if (isset($_SESSION['error'])) {
    $error_message = $_SESSION['error'];
    unset($_SESSION['error']);
}

/* =========================================================
   ADD INVENTORY ITEM
   ========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    if ($action === 'add') {

        $item_name   = trim($_POST['item_name'] ?? '');
        $category_id = (int)($_POST['category_id'] ?? 0);
        $brand       = trim($_POST['brand'] ?? '');
        $model       = trim($_POST['model'] ?? '');
        $total_qty   = (int)($_POST['total_qty'] ?? 0);
        $working_qty = (int)($_POST['working_qty'] ?? 0);
        $location    = trim($_POST['location'] ?? '');
        $damaged_qty = $total_qty - $working_qty;
        if (
            empty($item_name) ||
            $category_id <= 0 ||
            $total_qty <= 0 ||
            $working_qty < 0 ||
            $working_qty > $total_qty ||
            empty($location)
        ) {
            $_SESSION['error'] = "Please provide valid inventory details.";
        } else {

            $stmt = $conn->prepare(
                "INSERT INTO inventory
                (item_name, category_id, brand, model, total_qty, working_qty, damaged_qty, location, date_added)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())"
            );

            if ($stmt) {

                $stmt->bind_param(
                    "sissiiis",
                    $item_name,
                    $category_id,
                    $brand,
                    $model,
                    $total_qty,
                    $working_qty,
                    $damaged_qty,
                    $location
                );

                if ($stmt->execute()) {
                    $_SESSION['success'] = "Inventory item added successfully!";
                } else {
                    $_SESSION['error'] = "Failed to add inventory item: " . $stmt->error;
                }

                $stmt->close();

            } else {
                $_SESSION['error'] = "Database error: " . $conn->error;
            }
        }

        header("Location: inventory.php");
        exit();
    }

    /* =====================================================
       EDIT INVENTORY ITEM
       ===================================================== */
    if ($action === 'edit') {

        $id          = (int)($_POST['id'] ?? 0);
        $item_name   = trim($_POST['item_name'] ?? '');
        $category_id = (int)($_POST['category_id'] ?? 0);
        $brand       = trim($_POST['brand'] ?? '');
        $model       = trim($_POST['model'] ?? '');
        $total_qty   = (int)($_POST['total_qty'] ?? 0);
        $working_qty = (int)($_POST['working_qty'] ?? 0);
        $location    = trim($_POST['location'] ?? '');

        $damaged_qty = $total_qty - $working_qty;

        if (
            $id <= 0 ||
            empty($item_name) ||
            $category_id <= 0 ||
            $total_qty <= 0 ||
            $working_qty < 0 ||
            $working_qty > $total_qty ||
            empty($location)
        ) {
            $_SESSION['error'] = "Invalid inventory details provided.";
        } else {

            $stmt = $conn->prepare(
                "UPDATE inventory SET
                    item_name = ?,
                    category_id = ?,
                    brand = ?,
                    model = ?,
                    total_qty = ?,
                    working_qty = ?,
                    damaged_qty = ?,
                    location = ?
                 WHERE id = ?"
            );

            if ($stmt) {

                $stmt->bind_param(
                    "sissiiisi",
                    $item_name,
                    $category_id,
                    $brand,
                    $model,
                    $total_qty,
                    $working_qty,
                    $damaged_qty,
                    $location,
                    $id
                );

                if ($stmt->execute()) {
                    $_SESSION['success'] = "Inventory item updated successfully!";
                } else {
                    $_SESSION['error'] = "Failed to update inventory item: " . $stmt->error;
                }

                $stmt->close();

            } else {
                $_SESSION['error'] = "Database error: " . $conn->error;
            }
        }

        header("Location: inventory.php");
        exit();
    }
}

/* =========================================================
   DELETE INVENTORY ITEM
   ========================================================= */
if (isset($_GET['action']) && $_GET['action'] === 'delete') {

    $delete_id = (int)($_GET['id'] ?? 0);

    if ($delete_id > 0) {

        $stmt = $conn->prepare("DELETE FROM inventory WHERE id = ?");

        if ($stmt) {

            $stmt->bind_param("i", $delete_id);

            if ($stmt->execute()) {
                $_SESSION['success'] = "Inventory item deleted successfully!";
            } else {
                $_SESSION['error'] = "Failed to delete inventory item: " . $stmt->error;
            }

            $stmt->close();

        } else {
            $_SESSION['error'] = "Database error: " . $conn->error;
        }
    }

    header("Location: inventory.php");
    exit();
}

/* =========================================================
   GET CATEGORIES
   ========================================================= */

$categories = [];

$categoryResult = $conn->query(
    "SELECT id, category_name FROM categories ORDER BY category_name ASC"
);

if ($categoryResult) {
    while ($row = $categoryResult->fetch_assoc()) {
        $categories[] = $row;
    }
}

/* =========================================================
   SEARCH INVENTORY
   ========================================================= */

$search_term = trim($_GET['search'] ?? '');

$inventory = [];

if (!empty($search_term)) {

    $search_param = "%" . $search_term . "%";

    $stmt = $conn->prepare(
        "SELECT
            i.id,
            i.item_name,
            i.category_id,
            i.brand,
            i.model,
            i.total_qty,
            i.working_qty,
            i.damaged_qty,
            i.location,
            i.date_added,
            c.category_name
         FROM inventory i
         LEFT JOIN categories c ON i.category_id = c.id
         WHERE
            i.item_name LIKE ?
            OR i.brand LIKE ?
            OR i.model LIKE ?
            OR i.location LIKE ?
            OR c.category_name LIKE ?
         ORDER BY i.id DESC"
    );

    $stmt->bind_param(
        "sssss",
        $search_param,
        $search_param,
        $search_param,
        $search_param,
        $search_param
    );

    $stmt->execute();

    $result = $stmt->get_result();

} else {

    $result = $conn->query(
        "SELECT
            i.id,
            i.item_name,
            i.category_id,
            i.brand,
            i.model,
            i.total_qty,
            i.working_qty,
            i.damaged_qty,
            i.location,
            i.date_added,
            c.category_name
         FROM inventory i
         LEFT JOIN categories c ON i.category_id = c.id
         ORDER BY i.id DESC"
    );
}

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $inventory[] = $row;
    }
}

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Inventory - Lab Inventory Management System</title>

    <!-- Bootstrap -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <!-- Bootstrap Icons -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css"
        rel="stylesheet"
    >

    <!-- Google Font -->
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap"
        rel="stylesheet"
    >

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

        /* Sidebar */

        .sidebar {
            width: var(--sidebar-width);
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            background-color: var(--dark-bg);
            color: #ffffff;
            z-index: 1000;
            box-shadow: 4px 0 10px rgba(0,0,0,0.1);
        }

        .sidebar-brand {
            padding: 25px 20px;
            font-size: 1.15rem;
            font-weight: 700;
            border-bottom: 1px solid rgba(255,255,255,0.1);
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
            text-decoration: none;
        }

        .sidebar-menu .nav-link:hover,
        .sidebar-menu .nav-link.active {
            color: #ffffff;
            background-color: var(--primary-color);
        }

        .sidebar-menu .nav-link i {
            font-size: 1.2rem;
        }

        /* Main Content */

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
            box-shadow: 0 4px 20px rgba(0,0,0,0.03);
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        /* Content Card */

        .content-card {
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.04);
            border: 1px solid #f1f5f9;
            overflow: hidden;
        }

        .content-card-header {
            padding: 20px 25px;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }

        .table > :not(caption) > * > * {
            padding: 14px 18px;
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

        .modal-content {
            border-radius: 16px;
            border: none;
        }

        .modal-header {
            background: linear-gradient(
                135deg,
                #2563eb 0%,
                #1d4ed8 100%
            );
            color: #ffffff;
        }

        .modal-header .btn-close {
            filter: brightness(0) invert(1);
        }

        @media (max-width: 992px) {

            .sidebar {
                margin-left: calc(-1 * var(--sidebar-width));
            }

            .main-content {
                margin-left: 0;
            }
        }

    </style>

</head>

<body>

<!-- ======================================================
     SIDEBAR
     ====================================================== -->

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

            <a href="categories.php" class="nav-link">

                <i class="bi bi-tags-fill"></i>

                <span>Categories</span>

            </a>

        </li>

        <li class="nav-item">

            <a href="inventory.php" class="nav-link active">

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


<!-- ======================================================
     MAIN CONTENT
     ====================================================== -->

<main class="main-content">

    <!-- Top Navbar -->

    <header class="top-navbar">

        <div>

            <h5 class="fw-bold mb-0 text-dark">

                Inventory Management

            </h5>

            <small class="text-muted">

                Manage computer laboratory equipment

            </small>

        </div>

        <div class="d-flex align-items-center gap-3">

            <span class="badge bg-primary-subtle text-primary px-3 py-2 rounded-pill">

                <i class="bi bi-person-circle me-1"></i>

                Admin

            </span>

            <a
                href="logout.php"
                class="btn btn-outline-danger btn-sm rounded-pill px-3"
            >

                <i class="bi bi-box-arrow-right me-1"></i>

                Logout

            </a>

        </div>

    </header>


    <!-- Success Message -->

    <?php if (!empty($success_message)): ?>

        <div
            class="alert alert-success alert-dismissible fade show"
            role="alert"
        >

            <i class="bi bi-check-circle-fill me-2"></i>

            <?php echo htmlspecialchars($success_message); ?>

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
            ></button>

        </div>

    <?php endif; ?>


    <!-- Error Message -->

    <?php if (!empty($error_message)): ?>

        <div
            class="alert alert-danger alert-dismissible fade show"
            role="alert"
        >

            <i class="bi bi-exclamation-triangle-fill me-2"></i>

            <?php echo htmlspecialchars($error_message); ?>

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
            ></button>

        </div>

    <?php endif; ?>


    <!-- ==================================================
         INVENTORY CARD
         ================================================== -->

    <div class="content-card">

        <div class="content-card-header">

            <!-- Search -->

            <form
                action="inventory.php"
                method="GET"
                class="d-flex gap-2"
                style="max-width: 550px; width: 100%;"
            >

                <div class="input-group">

                    <span class="input-group-text bg-light">

                        <i class="bi bi-search text-muted"></i>

                    </span>

                    <input
                        type="text"
                        name="search"
                        class="form-control bg-light"
                        placeholder="Search equipment, brand, model, location..."
                        value="<?php echo htmlspecialchars($search_term); ?>"
                    >

                </div>

                <?php if (!empty($search_term)): ?>

                    <a
                        href="inventory.php"
                        class="btn btn-outline-secondary"
                    >

                        <i class="bi bi-x-lg"></i>

                    </a>

                <?php endif; ?>

                <button
                    type="submit"
                    class="btn btn-secondary"
                >

                    Search

                </button>

            </form>


            <!-- Add Item -->

            <button
                type="button"
                class="btn btn-primary rounded-pill px-4"
                data-bs-toggle="modal"
                data-bs-target="#addInventoryModal"
            >

                <i class="bi bi-plus-lg me-1"></i>

                Add Equipment

            </button>

        </div>


        <!-- ==================================================
             INVENTORY TABLE
             ================================================== -->

        <div class="table-responsive">

            <table class="table table-hover align-middle mb-0">

                <thead class="table-light">

                    <tr>

                        <th>#ID</th>

                        <th>Equipment</th>

                        <th>Category</th>

                        <th>Brand / Model</th>

                        <th class="text-center">
                            Total
                        </th>

                        <th class="text-center">
                            Working
                        </th>

                        <th class="text-center">
                            Damaged
                        </th>

                        <th>Location</th>

                        <th>Date Added</th>

                        <th class="text-end">
                            Actions
                        </th>

                    </tr>

                </thead>


                <tbody>

                <?php if (!empty($inventory)): ?>

                    <?php foreach ($inventory as $item): ?>

                        <tr>

                            <td class="fw-bold text-secondary">

                                #<?php echo (int)$item['id']; ?>

                            </td>


                            <td class="fw-semibold text-dark">

                                <i class="bi bi-pc-display me-2 text-primary"></i>

                                <?php
                                echo htmlspecialchars($item['item_name']);
                                ?>

                            </td>


                            <td>

                                <span
                                    class="badge bg-secondary-subtle text-secondary"
                                >

                                    <?php
                                    echo htmlspecialchars(
                                        $item['category_name'] ?? 'Uncategorized'
                                    );
                                    ?>

                                </span>

                            </td>


                            <td>

                                <?php
                                echo htmlspecialchars(
                                    $item['brand'] ?: 'N/A'
                                );
                                ?>

                                <?php if (!empty($item['model'])): ?>

                                    <small class="text-muted">

                                        (
                                        <?php
                                        echo htmlspecialchars($item['model']);
                                        ?>
                                        )

                                    </small>

                                <?php endif; ?>

                            </td>


                            <td class="text-center fw-bold">

                                <?php
                                echo (int)$item['total_qty'];
                                ?>

                            </td>


                            <td class="text-center">

                                <span class="badge-working">

                                    <?php
                                    echo (int)$item['working_qty'];
                                    ?>

                                </span>

                            </td>


                            <td class="text-center">

                                <?php if ((int)$item['damaged_qty'] > 0): ?>

                                    <span class="badge-damaged">

                                        <?php
                                        echo (int)$item['damaged_qty'];
                                        ?>

                                    </span>

                                <?php else: ?>

                                    <span class="text-muted">
                                        0
                                    </span>

                                <?php endif; ?>

                            </td>


                            <td>

                                <i class="bi bi-geo-alt text-danger me-1"></i>

                                <?php
                                echo htmlspecialchars($item['location']);
                                ?>

                            </td>


                            <td class="small text-muted">

                                <?php
                                echo date(
                                    'M d, Y',
                                    strtotime($item['date_added'])
                                );
                                ?>

                            </td>


                            <td class="text-end">

                                <!-- EDIT -->

                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-primary rounded-circle me-1"
                                    data-bs-toggle="modal"
                                    data-bs-target="#editInventoryModal<?php echo $item['id']; ?>"
                                    title="Edit"
                                >

                                    <i class="bi bi-pencil-fill"></i>

                                </button>


                                <!-- DELETE -->

                                <a
                                    href="inventory.php?action=delete&id=<?php echo $item['id']; ?>"
                                    class="btn btn-sm btn-outline-danger rounded-circle"
                                    onclick="return confirm('Are you sure you want to delete this inventory item?');"
                                    title="Delete"
                                >

                                    <i class="bi bi-trash-fill"></i>

                                </a>


                                <!-- EDIT MODAL -->

                                <div
                                    class="modal fade text-start"
                                    id="editInventoryModal<?php echo $item['id']; ?>"
                                    tabindex="-1"
                                >

                                    <div class="modal-dialog modal-lg modal-dialog-centered">

                                        <div class="modal-content">

                                            <div class="modal-header">

                                                <h5 class="modal-title fw-bold">

                                                    <i class="bi bi-pencil-square me-2"></i>

                                                    Edit Equipment

                                                </h5>

                                                <button
                                                    type="button"
                                                    class="btn-close"
                                                    data-bs-dismiss="modal"
                                                ></button>

                                            </div>


                                            <form
                                                action="inventory.php"
                                                method="POST"
                                            >

                                                <input
                                                    type="hidden"
                                                    name="action"
                                                    value="edit"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="id"
                                                    value="<?php echo $item['id']; ?>"
                                                >


                                                <div class="modal-body">

                                                    <div class="row g-3">

                                                        <div class="col-md-6">

                                                            <label class="form-label fw-semibold">
                                                                Equipment Name *
                                                            </label>

                                                            <input
                                                                type="text"
                                                                name="item_name"
                                                                class="form-control"
                                                                value="<?php echo htmlspecialchars($item['item_name']); ?>"
                                                                required
                                                            >

                                                        </div>


                                                        <div class="col-md-6">

                                                            <label class="form-label fw-semibold">
                                                                Category *
                                                            </label>

                                                            <select
                                                                name="category_id"
                                                                class="form-select"
                                                                required
                                                            >

                                                                <option value="">
                                                                    Select Category
                                                                </option>

                                                                <?php foreach ($categories as $category): ?>

                                                                    <option
                                                                        value="<?php echo $category['id']; ?>"
                                                                        <?php
                                                                        echo (
                                                                            $item['category_id'] == $category['id']
                                                                        )
                                                                            ? 'selected'
                                                                            : '';
                                                                        ?>
                                                                    >

                                                                        <?php
                                                                        echo htmlspecialchars(
                                                                            $category['category_name']
                                                                        );
                                                                        ?>

                                                                    </option>

                                                                <?php endforeach; ?>

                                                            </select>

                                                        </div>


                                                        <div class="col-md-6">

                                                            <label class="form-label fw-semibold">
                                                                Brand
                                                            </label>

                                                            <input
                                                                type="text"
                                                                name="brand"
                                                                class="form-control"
                                                                value="<?php echo htmlspecialchars($item['brand']); ?>"
                                                            >

                                                        </div>


                                                        <div class="col-md-6">

                                                            <label class="form-label fw-semibold">
                                                                Model
                                                            </label>

                                                            <input
                                                                type="text"
                                                                name="model"
                                                                class="form-control"
                                                                value="<?php echo htmlspecialchars($item['model']); ?>"
                                                            >

                                                        </div>


                                                        <div class="col-md-4">

                                                            <label class="form-label fw-semibold">
                                                                Total Quantity *
                                                            </label>

                                                            <input
                                                                type="number"
                                                                name="total_qty"
                                                                class="form-control"
                                                                min="1"
                                                                value="<?php echo (int)$item['total_qty']; ?>"
                                                                required
                                                            >

                                                        </div>


                                                        <div class="col-md-4">

                                                            <label class="form-label fw-semibold">
                                                                Working Quantity *
                                                            </label>

                                                            <input
                                                                type="number"
                                                                name="working_qty"
                                                                class="form-control"
                                                                min="0"
                                                                max="<?php echo (int)$item['total_qty']; ?>"
                                                                value="<?php echo (int)$item['working_qty']; ?>"
                                                                required
                                                            >

                                                        </div>


                                                        <div class="col-md-4">

                                                            <label class="form-label fw-semibold">
                                                                Damaged Quantity
                                                            </label>

                                                            <input
                                                                type="text"
                                                                class="form-control"
                                                                value="<?php echo (int)$item['damaged_qty']; ?>"
                                                                readonly
                                                            >

                                                            <small class="text-muted">
                                                                Automatically calculated
                                                            </small>

                                                        </div>


                                                        <div class="col-12">

                                                            <label class="form-label fw-semibold">
                                                                Location *
                                                            </label>

                                                            <input
                                                                type="text"
                                                                name="location"
                                                                class="form-control"
                                                                value="<?php echo htmlspecialchars($item['location']); ?>"
                                                                placeholder="e.g. Computer Lab 1"
                                                                required
                                                            >

                                                        </div>

                                                    </div>

                                                </div>


                                                <div class="modal-footer">

                                                    <button
                                                        type="button"
                                                        class="btn btn-light"
                                                        data-bs-dismiss="modal"
                                                    >
                                                        Cancel
                                                    </button>

                                                    <button
                                                        type="submit"
                                                        class="btn btn-primary px-4"
                                                    >

                                                        Update Equipment

                                                    </button>

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

                        <td
                            colspan="10"
                            class="text-center py-5 text-muted"
                        >

                            <i class="bi bi-box-seam fs-1 d-block mb-3"></i>

                            <strong>No inventory records found.</strong>

                            <br>

                            Click
                            <strong>Add Equipment</strong>
                            to add your first item.

                        </td>

                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</main>


<!-- ======================================================
     ADD INVENTORY MODAL
     ====================================================== -->

<div
    class="modal fade"
    id="addInventoryModal"
    tabindex="-1"
>

    <div class="modal-dialog modal-lg modal-dialog-centered">

        <div class="modal-content">

            <div class="modal-header">

                <h5 class="modal-title fw-bold">

                    <i class="bi bi-box-seam me-2"></i>

                    Add New Equipment

                </h5>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"
                ></button>

            </div>


            <form
                action="inventory.php"
                method="POST"
                autocomplete="off"
            >

                <input
                    type="hidden"
                    name="action"
                    value="add"
                >


                <div class="modal-body">

                    <div class="row g-3">

                        <!-- Equipment -->

                        <div class="col-md-6">

                            <label class="form-label fw-semibold">
                                Equipment Name *
                            </label>

                            <input
                                type="text"
                                name="item_name"
                                class="form-control"
                                placeholder="e.g. Desktop Computer"
                                required
                            >

                        </div>


                        <!-- Category -->

                        <div class="col-md-6">

                            <label class="form-label fw-semibold">
                                Category *
                            </label>

                            <select
                                name="category_id"
                                class="form-select"
                                required
                            >

                                <option value="">
                                    Select Category
                                </option>

                                <?php foreach ($categories as $category): ?>

                                    <option
                                        value="<?php echo $category['id']; ?>"
                                    >

                                        <?php
                                        echo htmlspecialchars(
                                            $category['category_name']
                                        );
                                        ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                            <?php if (empty($categories)): ?>

                                <small class="text-danger">

                                    No categories found.
                                    Please create a category first.

                                </small>

                            <?php endif; ?>

                        </div>


                        <!-- Brand -->

                        <div class="col-md-6">

                            <label class="form-label fw-semibold">
                                Brand
                            </label>

                            <input
                                type="text"
                                name="brand"
                                class="form-control"
                                placeholder="e.g. HP, Dell, Lenovo"
                            >

                        </div>


                        <!-- Model -->

                        <div class="col-md-6">

                            <label class="form-label fw-semibold">
                                Model
                            </label>

                            <input
                                type="text"
                                name="model"
                                class="form-control"
                                placeholder="e.g. ProDesk 600 G3"
                            >

                        </div>


                        <!-- Total -->

                        <div class="col-md-4">

                            <label class="form-label fw-semibold">
                                Total Quantity *
                            </label>

                            <input
                                type="number"
                                name="total_qty"
                                id="total_qty"
                                class="form-control"
                                min="1"
                                value="1"
                                required
                            >

                        </div>


                        <!-- Working -->

                        <div class="col-md-4">

                            <label class="form-label fw-semibold">
                                Working Quantity *
                            </label>

                            <input
                                type="number"
                                name="working_qty"
                                id="working_qty"
                                class="form-control"
                                min="0"
                                value="1"
                                required
                            >

                        </div>


                        <!-- Damaged -->

                        <div class="col-md-4">

                            <label class="form-label fw-semibold">
                                Damaged Quantity
                            </label>

                            <input
                                type="text"
                                id="damaged_qty_display"
                                class="form-control"
                                value="0"
                                readonly
                            >

                            <small class="text-muted">
                                Automatically calculated
                            </small>

                        </div>


                        <!-- Location -->

                        <div class="col-12">

                            <label class="form-label fw-semibold">
                                Location *
                            </label>

                            <input
                                type="text"
                                name="location"
                                class="form-control"
                                placeholder="e.g. Computer Laboratory 1"
                                required
                            >

                        </div>

                    </div>

                </div>


                <div class="modal-footer">

                    <button
                        type="button"
                        class="btn btn-light"
                        data-bs-dismiss="modal"
                    >

                        Cancel

                    </button>

                    <button
                        type="submit"
                        class="btn btn-primary px-4"
                    >

                        <i class="bi bi-save me-1"></i>

                        Save Equipment

                    </button>

                </div>

            </form>

        </div>

    </div>

</div>


<!-- Bootstrap JavaScript -->

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"
></script>


<!-- Calculate damaged quantity -->

<script>

    const totalInput = document.getElementById('total_qty');
    const workingInput = document.getElementById('working_qty');
    const damagedDisplay = document.getElementById('damaged_qty_display');

    function calculateDamaged() {

        const total = parseInt(totalInput.value) || 0;
        const working = parseInt(workingInput.value) || 0;

        let damaged = total - working;

        if (damaged < 0) {
            damaged = 0;
        }

        damagedDisplay.value = damaged;
    }

    totalInput.addEventListener('input', calculateDamaged);

    workingInput.addEventListener('input', calculateDamaged);

</script>

</body>

</html>