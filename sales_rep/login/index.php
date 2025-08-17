<?php
// Start session
session_start();

// Include database connection
require_once '../../config/database.php';

// Check if user is already logged in as sales rep
if (isset($_SESSION['user_id']) && $_SESSION['role'] == 'sales_rep') {
    header("Location: ../dashboard/index.php");
    exit;
}

// Process login form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = $_POST['password'];

    // Validate input
    if (empty($email) || empty($password)) {
        $error_message = "Please enter both email and password";
    } else {
        // Check if user exists and is active
        $query = "SELECT * FROM users WHERE email = '$email' AND status = 'active'";
        $result = mysqli_query($conn, $query);

        if (mysqli_num_rows($result) == 1) {
            $user = mysqli_fetch_assoc($result);

            // Verify password
            // if (password_verify($password, $user['password'])) {
            if ($password === $user['password']){
                // Check role
                if ($user['role'] == 'sales_rep') {
                    // Set session variables
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['name'] = $user['name'];
                    $_SESSION['role'] = $user['role'];

                    // Log login
                    $user_id = $user['user_id'];
                    $action = "User logged in";
                    mysqli_query($conn, "INSERT INTO audit_logs (user_id, action) VALUES ('$user_id', '$action')");

                    // Redirect to dashboard
                    header("Location: ../dashboard/index.php");
                    exit;
                } else {
                    $error_message = "You do not have permission to access the sales representative panel";
                }
            } else {
                $error_message = "Invalid password";
            }
        } else {
            $error_message = "User not found or account is inactive";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Rep Login - Pharmacy POS</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #2c3e50;
            --secondary: #1abc9c;
            --light: #ecf0f1;
            --dark: #34495e;
        }

        body {
            background-color: var(--light);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-container {
            max-width: 400px;
            width: 100%;
        }

        .card {
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }

        .card-header {
            background-color: var(--primary);
            color: white;
            text-align: center;
            border-radius: 10px 10px 0 0 !important;
            padding: 1.5rem;
        }

        .form-control:focus {
            border-color: var(--secondary);
            box-shadow: 0 0 0 0.25rem rgba(26, 188, 156, 0.25);
        }

        .btn-primary {
            background-color: var(--secondary);
            border-color: var(--secondary);
        }

        .btn-primary:hover,
        .btn-primary:focus {
            background-color: #16a085;
            border-color: #16a085;
        }

        .login-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }

        .role-switch {
            text-align: center;
            margin-top: 20px;
        }

        .role-switch a {
            color: var(--primary);
            text-decoration: none;
        }

        .role-switch a:hover {
            text-decoration: underline;
        }
    </style>
</head>

<body>
    <div class="login-container">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-user-tag login-icon"></i>
                <h4 class="mb-0">Sales Representative Login</h4>
            </div>
            <div class="card-body p-4">
                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $error_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <form method="post" action="">
                    <div class="mb-3">
                        <label for="email" class="form-label">Email Address</label>
                        <input type="email" class="form-control" id="email" name="email" required autofocus>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-sign-in-alt me-2"></i> Login
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="role-switch">
            <a href="../../admin/login/">Login as Manager/Admin</a>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>