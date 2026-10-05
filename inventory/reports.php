<?php
/**
 * Digital Inventory Management System for Computer Laboratories
 * File: reports.php
 * Description: Inventory Report Selection, Preview and Printing
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config.php';
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$page_title = "Reports";
$user_full_name = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Administrator';

/*
|--------------------------------------------------------------------------
| Load Laboratories
|--------------------------------------------------------------------------
| The current database uses the inventory "location" field.
| Therefore, available laboratory values are taken from location.
*/
$laboratories = [];

$lab_sql = "
    SELECT DISTINCT location
    FROM inventory
    WHERE location IS NOT NULL
      AND TRIM(location) <> ''
    ORDER BY location ASC
";

$lab_result = $conn->query($lab_sql);

if ($lab_result) {
    while ($row = $lab_result->fetch_assoc()) {
        $laboratories[] = $row['location'];
    }
}

/*
|--------------------------------------------------------------------------
| Load Categories
|--------------------------------------------------------------------------
*/
$categories = [];

$category_sql = "
    SELECT id, category_name
    FROM categories
    ORDER BY category_name ASC
";

$category_result = $conn->query($category_sql);

if ($category_result) {
    while ($row = $category_result->fetch_assoc()) {
        $categories[] = $row;
    }
}

/*
|--------------------------------------------------------------------------
| Variables
|--------------------------------------------------------------------------
*/
$report_type = $_POST['report_type'] ?? '';
$selected_lab = trim($_POST['selected_lab'] ?? '');
$selected_category = $_POST['selected_category'] ?? '';

$show_preview = false;
$error_message = '';
$report_title = '';
$report_data = [];

$total_quantity = 0;
$total_working = 0;
$total_damaged = 0;

/*
|--------------------------------------------------------------------------
| Generate Report Preview
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if ($report_type === '') {
        $error_message = "Please select a report type.";

    } elseif (
        in_array($report_type, [
            'laboratory_inventory',
            'lab_category_inventory'
        ]) &&
        $selected_lab === ''
    ) {
        $error_message = "Please select a laboratory.";

    } elseif (
        in_array($report_type, [
            'category_inventory',
            'lab_category_inventory'
        ]) &&
        $selected_category === ''
    ) {
        $error_message = "Please select a category.";

    } else {

        /*
        |--------------------------------------------------------------------------
        | Common SELECT
        |--------------------------------------------------------------------------
        */
        $base_select = "
            SELECT
                i.id,
                i.item_name,
                c.category_name,
                i.brand,
                i.model,
                i.total_qty,
                i.working_qty,
                i.damaged_qty,
                i.location,
                i.date_added
            FROM inventory i
            LEFT JOIN categories c ON i.category_id = c.id
        ";

        /*
        |--------------------------------------------------------------------------
        | Report Queries
        |--------------------------------------------------------------------------
        */
        switch ($report_type) {

            /*
            |--------------------------------------------------------------------------
            | 1. Complete Inventory
            |--------------------------------------------------------------------------
            */
            case 'complete_inventory':

                $report_title = "Complete Inventory Report";

                $sql = $base_select . "
                    ORDER BY i.item_name ASC
                ";

                $stmt = $conn->prepare($sql);

                if ($stmt) {
                    $stmt->execute();
                    $result = $stmt->get_result();

                    while ($row = $result->fetch_assoc()) {
                        $report_data[] = $row;

                        $total_quantity += (int)$row['total_qty'];
                        $total_working += (int)$row['working_qty'];
                        $total_damaged += (int)$row['damaged_qty'];
                    }

                    $stmt->close();
                }

                $show_preview = true;
                break;


            /*
            |--------------------------------------------------------------------------
            | 2. Working Equipment
            |--------------------------------------------------------------------------
            */
            case 'working_equipment':

                $report_title = "Working Equipment Report";

                $sql = $base_select . "
                    WHERE i.working_qty > 0
                    ORDER BY i.item_name ASC
                ";

                $stmt = $conn->prepare($sql);

                if ($stmt) {
                    $stmt->execute();
                    $result = $stmt->get_result();

                    while ($row = $result->fetch_assoc()) {
                        $report_data[] = $row;

                        $total_quantity += (int)$row['total_qty'];
                        $total_working += (int)$row['working_qty'];
                        $total_damaged += (int)$row['damaged_qty'];
                    }

                    $stmt->close();
                }

                $show_preview = true;
                break;


            /*
            |--------------------------------------------------------------------------
            | 3. Damaged Equipment
            |--------------------------------------------------------------------------
            */
            case 'damaged_equipment':

                $report_title = "Damaged Equipment Report";

                $sql = $base_select . "
                    WHERE i.damaged_qty > 0
                    ORDER BY i.item_name ASC
                ";

                $stmt = $conn->prepare($sql);

                if ($stmt) {
                    $stmt->execute();
                    $result = $stmt->get_result();

                    while ($row = $result->fetch_assoc()) {
                        $report_data[] = $row;

                        $total_quantity += (int)$row['total_qty'];
                        $total_working += (int)$row['working_qty'];
                        $total_damaged += (int)$row['damaged_qty'];
                    }

                    $stmt->close();
                }

                $show_preview = true;
                break;


            /*
            |--------------------------------------------------------------------------
            | 4. Category Summary
            |--------------------------------------------------------------------------
            */
            case 'category_summary':

                $report_title = "Category Summary Report";

                $sql = "
                    SELECT
                        c.category_name,
                        COUNT(i.id) AS item_types,
                        COALESCE(SUM(i.total_qty), 0) AS total_qty,
                        COALESCE(SUM(i.working_qty), 0) AS working_qty,
                        COALESCE(SUM(i.damaged_qty), 0) AS damaged_qty
                    FROM categories c
                    LEFT JOIN inventory i
                        ON c.id = i.category_id
                    GROUP BY c.id, c.category_name
                    ORDER BY c.category_name ASC
                ";

                $stmt = $conn->prepare($sql);

                if ($stmt) {
                    $stmt->execute();
                    $result = $stmt->get_result();

                    while ($row = $result->fetch_assoc()) {
                        $report_data[] = $row;

                        $total_quantity += (int)$row['total_qty'];
                        $total_working += (int)$row['working_qty'];
                        $total_damaged += (int)$row['damaged_qty'];
                    }

                    $stmt->close();
                }

                $show_preview = true;
                break;


            /*
            |--------------------------------------------------------------------------
            | 5. Laboratory Inventory
            |--------------------------------------------------------------------------
            */
            case 'laboratory_inventory':

                $report_title = "Laboratory Inventory Report";

                $sql = $base_select . "
                    WHERE i.location = ?
                    ORDER BY i.item_name ASC
                ";

                $stmt = $conn->prepare($sql);

                if ($stmt) {
                    $stmt->bind_param("s", $selected_lab);
                    $stmt->execute();

                    $result = $stmt->get_result();

                    while ($row = $result->fetch_assoc()) {
                        $report_data[] = $row;

                        $total_quantity += (int)$row['total_qty'];
                        $total_working += (int)$row['working_qty'];
                        $total_damaged += (int)$row['damaged_qty'];
                    }

                    $stmt->close();
                }

                $show_preview = true;
                break;


            /*
            |--------------------------------------------------------------------------
            | 6. Category Inventory
            |--------------------------------------------------------------------------
            */
            case 'category_inventory':

                $report_title = "Category Inventory Report";

                $sql = $base_select . "
                    WHERE i.category_id = ?
                    ORDER BY i.item_name ASC
                ";

                $stmt = $conn->prepare($sql);

                if ($stmt) {
                    $category_id = (int)$selected_category;

                    $stmt->bind_param("i", $category_id);
                    $stmt->execute();

                    $result = $stmt->get_result();

                    while ($row = $result->fetch_assoc()) {
                        $report_data[] = $row;

                        $total_quantity += (int)$row['total_qty'];
                        $total_working += (int)$row['working_qty'];
                        $total_damaged += (int)$row['damaged_qty'];
                    }

                    $stmt->close();
                }

                $show_preview = true;
                break;


            /*
            |--------------------------------------------------------------------------
            | 7. Laboratory + Category
            |--------------------------------------------------------------------------
            */
            case 'lab_category_inventory':

                $report_title = "Inventory by Laboratory and Category";

                $sql = $base_select . "
                    WHERE i.location = ?
                      AND i.category_id = ?
                    ORDER BY i.item_name ASC
                ";

                $stmt = $conn->prepare($sql);

                if ($stmt) {
                    $category_id = (int)$selected_category;

                    $stmt->bind_param(
                        "si",
                        $selected_lab,
                        $category_id
                    );

                    $stmt->execute();

                    $result = $stmt->get_result();

                    while ($row = $result->fetch_assoc()) {
                        $report_data[] = $row;

                        $total_quantity += (int)$row['total_qty'];
                        $total_working += (int)$row['working_qty'];
                        $total_damaged += (int)$row['damaged_qty'];
                    }

                    $stmt->close();
                }

                $show_preview = true;
                break;


            default:
                $error_message = "Invalid report type selected.";
                break;
        }
    }
}

/*
|--------------------------------------------------------------------------
| Selected Names
|--------------------------------------------------------------------------
*/
$selected_category_name = '';

if ($selected_category !== '') {
    foreach ($categories as $category) {
        if ((string)$category['id'] === (string)$selected_category) {
            $selected_category_name = $category['category_name'];
            break;
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?php echo htmlspecialchars($page_title); ?></title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
        rel="stylesheet"
    >

    <style>

        body {
            background: #f5f7fb;
            font-family: Arial, sans-serif;
        }

        .page-container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .report-card {
            background: #ffffff;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 4px 18px rgba(0,0,0,.06);
        }

        .report-header {
            text-align: center;
            border-bottom: 2px solid #222;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }

        .report-header h2 {
            margin-bottom: 5px;
            font-weight: 700;
        }

        .report-header h4 {
            margin: 0;
            font-weight: 500;
        }

        .report-info {
            margin-bottom: 20px;
            padding: 15px;
            border: 1px solid #ddd;
            background: #fafafa;
        }

        .summary-card {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            background: #fff;
        }

        .summary-card strong {
            display: block;
            font-size: 22px;
        }

        table th,
        table td {
            vertical-align: middle;
        }

        .no-print {
            display: block;
        }

        @media print {

            body {
                background: #ffffff !important;
                margin: 0;
            }

            .no-print {
                display: none !important;
            }

            .page-container {
                max-width: 100%;
                margin: 0;
                padding: 0;
            }

            .report-card {
                box-shadow: none !important;
                border: none !important;
                padding: 0 !important;
                margin: 0 !important;
            }

            .report-preview {
                display: block !important;
            }

            .table {
                width: 100% !important;
                border-collapse: collapse !important;
            }

            .table th,
            .table td {
                border: 1px solid #222 !important;
                color: #000 !important;
                background: #fff !important;
            }

            @page {
                size: A4 landscape;
                margin: 10mm;
            }
        }

    </style>

</head>

<body>

<div class="page-container">

    <!-- HEADER -->
    <div class="report-card no-print">

        <div class="d-flex justify-content-between align-items-center mb-4">

            <div>
                <h3 class="mb-1">
                    <i class="bi bi-file-earmark-bar-graph"></i>
                    Reports
                </h3>

                <small class="text-muted">
                    Select a report, preview it, then print it
                </small>
            </div>

            <a href="dashboard.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i>
                Dashboard
            </a>

        </div>

        <?php if ($error_message !== ''): ?>

            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle"></i>
                <?php echo htmlspecialchars($error_message); ?>
            </div>

        <?php endif; ?>


        <form method="POST" action="reports.php">

            <div class="row g-3">

                <!-- REPORT TYPE -->
                <div class="col-md-5">

                    <label class="form-label fw-bold">
                        Report Type
                    </label>

                    <select
                        name="report_type"
                        id="report_type"
                        class="form-select"
                        required
                    >

                        <option value="">
                            -- Select Report Type --
                        </option>

                        <option
                            value="complete_inventory"
                            <?php echo $report_type === 'complete_inventory' ? 'selected' : ''; ?>
                        >
                            1. Complete Inventory Report
                        </option>

                        <option
                            value="working_equipment"
                            <?php echo $report_type === 'working_equipment' ? 'selected' : ''; ?>
                        >
                            2. Working Equipment Report
                        </option>

                        <option
                            value="damaged_equipment"
                            <?php echo $report_type === 'damaged_equipment' ? 'selected' : ''; ?>
                        >
                            3. Damaged Equipment Report
                        </option>

                        <option
                            value="category_summary"
                            <?php echo $report_type === 'category_summary' ? 'selected' : ''; ?>
                        >
                            4. Category Summary Report
                        </option>

                        <option
                            value="laboratory_inventory"
                            <?php echo $report_type === 'laboratory_inventory' ? 'selected' : ''; ?>
                        >
                            5. Laboratory Inventory Report
                        </option>

                        <option
                            value="category_inventory"
                            <?php echo $report_type === 'category_inventory' ? 'selected' : ''; ?>
                        >
                            6. Category Inventory Report
                        </option>

                        <option
                            value="lab_category_inventory"
                            <?php echo $report_type === 'lab_category_inventory' ? 'selected' : ''; ?>
                        >
                            7. Laboratory + Category Report
                        </option>

                    </select>

                </div>


                <!-- LABORATORY -->
                <div
                    class="col-md-3"
                    id="laboratory_group"
                    style="display:none;"
                >

                    <label class="form-label fw-bold">
                        Laboratory
                    </label>

                    <select
                        name="selected_lab"
                        id="selected_lab"
                        class="form-select"
                    >

                        <option value="">
                            -- Select Laboratory --
                        </option>

                        <?php foreach ($laboratories as $lab): ?>

                            <option
                                value="<?php echo htmlspecialchars($lab); ?>"
                                <?php echo $selected_lab === $lab ? 'selected' : ''; ?>
                            >
                                <?php echo htmlspecialchars($lab); ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <!-- CATEGORY -->
                <div
                    class="col-md-3"
                    id="category_group"
                    style="display:none;"
                >

                    <label class="form-label fw-bold">
                        Category
                    </label>

                    <select
                        name="selected_category"
                        id="selected_category"
                        class="form-select"
                    >

                        <option value="">
                            -- Select Category --
                        </option>

                        <?php foreach ($categories as $category): ?>

                            <option
                                value="<?php echo (int)$category['id']; ?>"
                                <?php echo (string)$selected_category === (string)$category['id'] ? 'selected' : ''; ?>
                            >
                                <?php echo htmlspecialchars($category['category_name']); ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <!-- BUTTON -->
                <div class="col-md-4">

                    <label class="form-label d-block">
                        &nbsp;
                    </label>

                    <button
                        type="submit"
                        class="btn btn-primary w-100"
                    >
                        <i class="bi bi-eye"></i>
                        Generate Preview
                    </button>

                </div>

            </div>

        </form>

    </div>


    <!-- REPORT PREVIEW -->
    <?php if ($show_preview): ?>

        <div class="report-card report-preview" id="printable_report">

            <div class="report-header">

                <h2>
                    DIGITAL INVENTORY MANAGEMENT SYSTEM
                </h2>

                <h4>
                    COMPUTER LABORATORIES
                </h4>

                <p class="mb-0 mt-2">
                    <?php echo htmlspecialchars($report_title); ?>
                </p>

            </div>


            <div class="report-info">

                <div class="row">

                    <div class="col-md-6">

                        <strong>Generated By:</strong>
                        <?php echo htmlspecialchars($user_full_name); ?>

                    </div>

                    <div class="col-md-6 text-md-end">

                        <strong>Date:</strong>
                        <?php echo date('d M Y'); ?>

                    </div>

                </div>


                <?php if ($selected_lab !== ''): ?>

                    <div class="mt-2">

                        <strong>Laboratory:</strong>
                        <?php echo htmlspecialchars($selected_lab); ?>

                    </div>

                <?php endif; ?>


                <?php if ($selected_category_name !== ''): ?>

                    <div class="mt-2">

                        <strong>Category:</strong>
                        <?php echo htmlspecialchars($selected_category_name); ?>

                    </div>

                <?php endif; ?>

            </div>


            <!-- TABLE -->
            <div class="table-responsive">

                <?php if ($report_type === 'category_summary'): ?>

                    <table class="table table-bordered">

                        <thead class="table-dark">

                        <tr>
                            <th>S/N</th>
                            <th>Category</th>
                            <th>Item Types</th>
                            <th>Total Quantity</th>
                            <th>Working</th>
                            <th>Damaged</th>
                        </tr>

                        </thead>

                        <tbody>

                        <?php if (count($report_data) > 0): ?>

                            <?php $sn = 1; ?>

                            <?php foreach ($report_data as $row): ?>

                                <tr>

                                    <td><?php echo $sn++; ?></td>

                                    <td>
                                        <?php echo htmlspecialchars($row['category_name']); ?>
                                    </td>

                                    <td>
                                        <?php echo (int)$row['item_types']; ?>
                                    </td>

                                    <td>
                                        <?php echo (int)$row['total_qty']; ?>
                                    </td>

                                    <td>
                                        <?php echo (int)$row['working_qty']; ?>
                                    </td>

                                    <td>
                                        <?php echo (int)$row['damaged_qty']; ?>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php else: ?>

                            <tr>
                                <td colspan="6" class="text-center">
                                    No records found.
                                </td>
                            </tr>

                        <?php endif; ?>

                        </tbody>

                    </table>

                <?php else: ?>

                    <table class="table table-bordered">

                        <thead class="table-dark">

                        <tr>

                            <th>S/N</th>
                            <th>Item Name</th>
                            <th>Category</th>
                            <th>Brand</th>
                            <th>Model</th>
                            <th>Total</th>
                            <th>Working</th>
                            <th>Damaged</th>
                            <th>Laboratory / Location</th>
                            <th>Date Added</th>

                        </tr>

                        </thead>

                        <tbody>

                        <?php if (count($report_data) > 0): ?>

                            <?php $sn = 1; ?>

                            <?php foreach ($report_data as $row): ?>

                                <tr>

                                    <td><?php echo $sn++; ?></td>

                                    <td>
                                        <?php echo htmlspecialchars($row['item_name']); ?>
                                    </td>

                                    <td>
                                        <?php echo htmlspecialchars($row['category_name'] ?? ''); ?>
                                    </td>

                                    <td>
                                        <?php echo htmlspecialchars($row['brand'] ?? ''); ?>
                                    </td>

                                    <td>
                                        <?php echo htmlspecialchars($row['model'] ?? ''); ?>
                                    </td>

                                    <td>
                                        <?php echo (int)$row['total_qty']; ?>
                                    </td>

                                    <td>
                                        <?php echo (int)$row['working_qty']; ?>
                                    </td>

                                    <td>
                                        <?php echo (int)$row['damaged_qty']; ?>
                                    </td>

                                    <td>
                                        <?php echo htmlspecialchars($row['location'] ?? ''); ?>
                                    </td>

                                    <td>
                                        <?php echo htmlspecialchars($row['date_added'] ?? ''); ?>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php else: ?>

                            <tr>

                                <td colspan="10" class="text-center">

                                    No records found for this report.

                                </td>

                            </tr>

                        <?php endif; ?>

                        </tbody>

                    </table>

                <?php endif; ?>

            </div>


            <!-- TOTALS -->
            <?php if ($report_type !== 'category_summary'): ?>

                <div class="row g-3 mt-3">

                    <div class="col-md-4">

                        <div class="summary-card">

                            <span>Total Equipment</span>

                            <strong>
                                <?php echo number_format($total_quantity); ?>
                            </strong>

                        </div>

                    </div>

                    <div class="col-md-4">

                        <div class="summary-card">

                            <span>Working Equipment</span>

                            <strong>
                                <?php echo number_format($total_working); ?>
                            </strong>

                        </div>

                    </div>

                    <div class="col-md-4">

                        <div class="summary-card">

                            <span>Damaged Equipment</span>

                            <strong>
                                <?php echo number_format($total_damaged); ?>
                            </strong>

                        </div>

                    </div>

                </div>

            <?php endif; ?>


            <!-- SIGNATURE -->
            <div class="row mt-5">

                <div class="col-md-6">

                    <p>
                        ______________________________
                    </p>

                    <p>
                        Laboratory Administrator
                    </p>

                </div>

                <div class="col-md-6 text-md-end">

                    <p>
                        ______________________________
                    </p>

                    <p>
                        Date
                    </p>

                </div>

            </div>


            <!-- BUTTONS -->
            <div class="text-center mt-4 no-print">

                <a
                    href="reports.php"
                    class="btn btn-secondary me-2"
                >
                    <i class="bi bi-arrow-left"></i>
                    Back to Reports
                </a>

                <button
                    type="button"
                    onclick="window.print()"
                    class="btn btn-primary"
                >
                    <i class="bi bi-printer"></i>
                    Print Report
                </button>

            </div>

        </div>

    <?php endif; ?>

</div>


<script>

    const reportType = document.getElementById("report_type");
    const laboratoryGroup = document.getElementById("laboratory_group");
    const categoryGroup = document.getElementById("category_group");

    const laboratorySelect = document.getElementById("selected_lab");
    const categorySelect = document.getElementById("selected_category");


    function updateReportFields() {

        const selected = reportType.value;

        laboratoryGroup.style.display = "none";
        categoryGroup.style.display = "none";

        laboratorySelect.removeAttribute("required");
        categorySelect.removeAttribute("required");


        if (selected === "laboratory_inventory") {

            laboratoryGroup.style.display = "block";
            laboratorySelect.setAttribute("required", "required");

        }


        if (selected === "category_inventory") {

            categoryGroup.style.display = "block";
            categorySelect.setAttribute("required", "required");

        }


        if (selected === "lab_category_inventory") {

            laboratoryGroup.style.display = "block";
            categoryGroup.style.display = "block";

            laboratorySelect.setAttribute("required", "required");
            categorySelect.setAttribute("required", "required");

        }

    }


    reportType.addEventListener("change", updateReportFields);

    updateReportFields();

</script>

</body>
</html>