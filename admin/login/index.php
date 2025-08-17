<?php
// Initialize session
session_start();

// Check if already logged in
if (isset($_SESSION['admin_id'])) {
    header("Location: ../dashboard.php");
    exit();
}

// Include database connection
require_once "../../config/database.php";

$error = "";

// Process login
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    // Validate input
    if (empty($username) || empty($password)) {
        $error = "Please enter both username and password";
    } else {
        // Prepare a select statement
        $sql = "SELECT user_id, name, password FROM users WHERE email = ? AND role = 'manager' AND status = 'active'";

        if ($stmt = $conn->prepare($sql)) {
            // Bind variables to the prepared statement as parameters
            $stmt->bind_param("s", $param_email);

            // Set parameters
            $param_email = $username;            // Attempt to execute the prepared statement
            if ($stmt->execute()) {
                // Store result
                $stmt->store_result();

                // Check if user exists, if yes then verify password
                if ($stmt->num_rows == 1) {
                    // Bind result variables
                    $stmt->bind_result($user_id, $name, $hashed_password);
                    if ($stmt->fetch()) {
                        if (password_verify($password, $hashed_password)) {
                            // Password is correct, start a new session
                            session_start();

                            // Store data in session variables
                            $_SESSION["loggedin"] = true;
                            $_SESSION["admin_id"] = $user_id;
                            $_SESSION["username"] = $username; // Using the input username (email)
                            $_SESSION["full_name"] = $name;
                            $_SESSION["role"] = "admin";                            // Redirect user to dashboard
                            header("location: ../dashboard.php");
                        } else {
                            // Password is not valid
                            $error = "Invalid username or password";
                        }
                    }
                } else {
                    // Username doesn't exist
                    $error = "Invalid username or password";
                }
            } else {
                $error = "Oops! Something went wrong. Please try again later.";
            }

            // Close statement
            $stmt->close();
        }
    }

    // Close connection
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Pharmacy POS System</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <style>
        body {
            background-color: #f8f9fa;
            height: 100vh;
            display: flex;
            align-items: center;
            padding-top: 40px;
            padding-bottom: 40px;
        }

        .form-signin {
            width: 100%;
            max-width: 400px;
            padding: 15px;
            margin: auto;
        }

        .form-signin .card {
            border-radius: 10px;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }

        .form-signin .card-header {
            background: #4e73df;
            color: white;
            text-align: center;
            font-size: 24px;
            font-weight: 600;
            border-top-left-radius: 10px;
            border-top-right-radius: 10px;
        }

        .form-signin .btn-primary {
            background-color: #4e73df;
            border-color: #4e73df;
        }

        .form-signin .btn-primary:hover {
            background-color: #2e59d9;
            border-color: #2653d4;
        }

        .form-signin .form-floating:focus-within {
            z-index: 2;
        }

        .logo-text {
            font-size: 24px;
            font-weight: bold;
            color: #4e73df;
            margin-bottom: 20px;
            text-align: center;
        }
    </style>
</head>

<body class="text-center">
    <main class="form-signin">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-clinic-medical me-2"></i> Pharmacy POS
            </div>
            <div class="card-body p-4">
                <h2 class="mb-3">Manager Login</h2>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                    <div class="form-floating mb-3">
                        <input type="email" class="form-control" id="username" name="username" placeholder="Email Address" required>
                        <label for="username"><i class="fas fa-envelope me-2"></i>Email Address</label>
                    </div>

                    <div class="form-floating mb-3">
                        <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
                        <label for="password"><i class="fas fa-lock me-2"></i>Password</label>
                    </div>

                    <div class="d-grid gap-2">
                        <button class="btn btn-primary btn-lg" type="submit">
                            <i class="fas fa-sign-in-alt me-2"></i> Login
                        </button>
                    </div>
                </form>
            </div>
            <div class="card-footer text-center">
                <a href="../../sales_rep/login" class="text-decoration-none">Login as Sales Rep</a>
            </div>
        </div>
        <p class="mt-4 mb-3 text-muted">&copy; <?php echo date('Y'); ?> Pharmacy POS System</p>
    </main>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>