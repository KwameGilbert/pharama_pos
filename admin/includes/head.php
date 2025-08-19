<?php
// Start the session for user authentication
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
ob_start();



// Check if user is logged in as admin
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login/index.php");
    exit;
}

// Include the database connection
include_once __DIR__ . '/../../config/database.php';

// Get user information
$user_id = $_SESSION['admin_id'];
$query = "SELECT * FROM users WHERE user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// Get pharmacy information
$pharmacy_query = "SELECT * FROM pharmacy LIMIT 1";
$pharmacy_result = mysqli_query($conn, $pharmacy_query);
$pharmacy = mysqli_fetch_assoc($pharmacy_result);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Pharmacy POS</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.5/css/dataTables.bootstrap5.min.css">

 
    <!-- Custom styles -->
    <style>
        :root {
            --primary: #2c3e50;
            --secondary: #3498db;
            --light: #ecf0f1;
            --dark: #34495e;
            --accent: #e74c3c;
        }

        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .sidebar {
            background-color: var(--primary);
            min-height: 100vh;
            color: white;
            position: fixed;
            width: 250px;
            z-index: 100;
        }

        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            margin-bottom: 5px;
            border-radius: 5px;
            padding: 10px 15px;
        }

        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background-color: var(--secondary);
            color: white;
        }

        .sidebar .nav-link i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }

        .content-wrapper {
            margin-left: 250px;
            transition: margin-left 0.3s;
            min-height: 100vh;
            padding-bottom: 60px;
        }

        .dashboard-card {
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            transition: transform 0.2s;
            background-color: white;
        }

        .dashboard-card:hover {
            transform: translateY(-5px);
        }

        .top-bar {
            background-color: white;
            padding: 15px 20px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            z-index: 99;
        }

        .btn-primary {
            background-color: var(--secondary);
            border-color: var(--secondary);
        }

        .btn-primary:hover {
            background-color: #2980b9;
            border-color: #2980b9;
        }

        .stats-card {
            border-left: 4px solid;
            overflow: hidden;
            position: relative;
        }

        .stats-card .icon {
            position: absolute;
            right: 20px;
            top: 15px;
            opacity: 0.2;
            font-size: 4rem;
        }

        .sales-card {
            border-left-color: #2ecc71;
        }

        .invoices-card {
            border-left-color: #f39c12;
        }

        .customers-card {
            border-left-color: #3498db;
        }

        .alerts-card {
            border-left-color: #e74c3c;
        }

        .stats-card h3 {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .stats-card p {
            color: #7f8c8d;
            margin-bottom: 0;
        }

        @media (max-width: 992px) {
            .sidebar {
                width: 70px;
                overflow: hidden;
            }

            .sidebar .nav-link span {
                display: none;
            }

            .sidebar .nav-link i {
                margin-right: 0;
                font-size: 1.2rem;
            }

            .content-wrapper {
                margin-left: 70px;
            }

            .sidebar-brand {
                display: none;
            }
        }
    </style>
</head>

<body>
    <div class="d-flex">
        <!-- Sidebar -->
        <?php include_once __DIR__ . '/sidebar.php'; ?>

        <!-- Main content -->
        <div class="content-wrapper">
            <!-- Top Navigation Bar -->
            <div class="top-bar d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="mb-0"><?php echo isset($page_title) ? $page_title : 'Admin Dashboard'; ?></h4>
                </div>
                <div class="d-flex align-items-center">
                    <div class="dropdown me-3">
                        <a class="btn btn-light position-relative" href="#" role="button" id="alertsDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-bell"></i>
                            <?php
                            // Count alerts (low stock + expired products)
                            $alerts_query = "SELECT 
                                (SELECT COUNT(*) FROM products WHERE stock_qty <= reorder_level) +
                                (SELECT COUNT(*) FROM products WHERE expiry_date <= CURDATE()) as total_alerts";
                            $alerts_result = mysqli_query($conn, $alerts_query);
                            $alerts_count = mysqli_fetch_assoc($alerts_result)['total_alerts'];

                            if ($alerts_count > 0) {
                                echo '<span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">' .
                                    $alerts_count . '<span class="visually-hidden">alerts</span></span>';
                            }
                            ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="alertsDropdown" style="min-width: 300px;">
                            <li>
                                <h6 class="dropdown-header">Alerts</h6>
                            </li>
                            <?php
                            // Get low stock products
                            $low_stock_query = "SELECT name, stock_qty FROM products WHERE stock_qty <= reorder_level LIMIT 5";
                            $low_stock_result = mysqli_query($conn, $low_stock_query);

                            if (mysqli_num_rows($low_stock_result) > 0) {
                                while ($product = mysqli_fetch_assoc($low_stock_result)) {
                                    echo '<li><a class="dropdown-item" href="#">
                                          <i class="fas fa-exclamation-triangle text-warning me-2"></i> Low stock: ' .
                                        htmlspecialchars($product['name']) . ' (' . $product['stock_qty'] . ' left)</a></li>';
                                }
                            }

                            // Get expired products
                            $expired_query = "SELECT name, expiry_date FROM products WHERE expiry_date <= CURDATE() LIMIT 5";
                            $expired_result = mysqli_query($conn, $expired_query);

                            if (mysqli_num_rows($expired_result) > 0) {
                                while ($product = mysqli_fetch_assoc($expired_result)) {
                                    echo '<li><a class="dropdown-item" href="#">
                                          <i class="fas fa-calendar-times text-danger me-2"></i> Expired: ' .
                                        htmlspecialchars($product['name']) . ' (' . date('M d, Y', strtotime($product['expiry_date'])) . ')</a></li>';
                                }
                            }

                            if ($alerts_count > 10) {
                                echo '<li><hr class="dropdown-divider"></li>';
                                echo '<li><a class="dropdown-item text-center" href="../reports/reports.php?type=alerts">View all alerts</a></li>';
                            } elseif ($alerts_count == 0) {
                                echo '<li><a class="dropdown-item text-center" href="#">No alerts</a></li>';
                            }
                            ?>
                        </ul>
                    </div>
                    <div class="dropdown">
                        <a href="#" class="d-flex align-items-center text-decoration-none dropdown-toggle" id="userMenu" data-bs-toggle="dropdown" aria-expanded="false">
                            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($user['name']); ?>&background=random" alt="Profile" width="32" height="32" class="rounded-circle me-2">
                            <span><?php echo htmlspecialchars($user['name']); ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userMenu">
                            <li><a class="dropdown-item" href="../settings/settings.php?tab=profile"><i class="fas fa-user-cog me-2"></i> Profile Settings</a></li>
                            <li><a class="dropdown-item" href="../settings/settings.php?tab=pharmacy"><i class="fas fa-store me-2"></i> Pharmacy Settings</a></li>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li><a class="dropdown-item" href="../login/logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Main content container -->
            <div class="container-fluid px-4">