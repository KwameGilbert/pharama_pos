<?php
// Set page title
$page_title = "Settings";

// Include header
include_once __DIR__ .'/../includes/head.php';

// Get the active tab from query parameter (default to profile)
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'profile';

// Handle profile updates
$profile_updated = false;
$profile_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $current_password = trim($_POST['current_password']);
    $new_password = trim($_POST['new_password']);
    $confirm_password = trim($_POST['confirm_password']);

    if (empty($name)) {
        $profile_error = "Name cannot be empty.";
    } else {
        // If password is being changed, validate current password
        if (!empty($new_password)) {
            // Verify current password
            $verify_query = "SELECT password FROM users WHERE user_id = ?";
            $verify_stmt = $conn->prepare($verify_query);
            $verify_stmt->bind_param("i", $_SESSION['admin_id']);
            $verify_stmt->execute();
            $verify_result = $verify_stmt->get_result();
            $verify_row = $verify_result->fetch_assoc();

            if (!password_verify($current_password, $verify_row['password'])) {
                $profile_error = "Current password is incorrect.";
            } elseif ($new_password !== $confirm_password) {
                $profile_error = "New passwords do not match.";
            } elseif (strlen($new_password) < 8) {
                $profile_error = "New password must be at least 8 characters.";
            } else {
                // Hash the new password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

                // Update user profile with new password
                $update_query = "UPDATE users SET name = ?, email = ?, phone = ?, password = ? WHERE user_id = ?";
                $update_stmt = $conn->prepare($update_query);
                $update_stmt->bind_param("ssssi", $name, $email, $phone, $hashed_password, $_SESSION['admin_id']);

                if ($update_stmt->execute()) {
                    $profile_updated = true;
                    $_SESSION['full_name'] = $name;
                } else {
                    $profile_error = "Error updating profile: " . $conn->error;
                }
            }
        } else {
            // Update user profile without changing password
            $update_query = "UPDATE users SET name = ?, email = ?, phone = ? WHERE user_id = ?";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bind_param("sssi", $name, $email, $phone, $_SESSION['admin_id']);

            if ($update_stmt->execute()) {
                $profile_updated = true;
                $_SESSION['full_name'] = $name;
            } else {
                $profile_error = "Error updating profile: " . $conn->error;
            }
        }
    }
}

// Handle pharmacy info updates
$pharmacy_updated = false;
$pharmacy_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_pharmacy'])) {
    $pharmacy_name = trim($_POST['pharmacy_name']);
    $pharmacy_address = trim($_POST['address']);
    $pharmacy_phone = trim($_POST['pharmacy_phone']);
    $pharmacy_email = trim($_POST['pharmacy_email']);

    if (empty($pharmacy_name)) {
        $pharmacy_error = "Pharmacy name cannot be empty.";
    } else {
        // Check if pharmacy info exists
        $check_query = "SELECT * FROM pharmacy LIMIT 1";
        $check_result = mysqli_query($conn, $check_query);

        if (mysqli_num_rows($check_result) > 0) {
            // Update existing pharmacy info
            $update_query = "UPDATE pharmacy SET name = ?, address = ?, phone = ?, email = ? WHERE pharmacy_id = ?";
            $pharmacy_id = mysqli_fetch_assoc($check_result)['pharmacy_id'];
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bind_param("ssssi", $pharmacy_name, $pharmacy_address, $pharmacy_phone, $pharmacy_email, $pharmacy_id);
        } else {
            // Insert new pharmacy info
            $update_query = "INSERT INTO pharmacy (name, address, phone, email) VALUES (?, ?, ?, ?)";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bind_param("ssss", $pharmacy_name, $pharmacy_address, $pharmacy_phone, $pharmacy_email);
        }

        if ($update_stmt->execute()) {
            $pharmacy_updated = true;
        } else {
            $pharmacy_error = "Error updating pharmacy information: " . $conn->error;
        }
    }
}

// Get fresh user data
$query = "SELECT * FROM users WHERE user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $_SESSION['admin_id']);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Get pharmacy information
$pharmacy_query = "SELECT * FROM pharmacy LIMIT 1";
$pharmacy_result = mysqli_query($conn, $pharmacy_query);
$pharmacy = mysqli_fetch_assoc($pharmacy_result);
?>

<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-cog me-2"></i> Settings</h5>
            </div>
            <div class="card-body">
                <!-- Tab navigation -->
                <ul class="nav nav-tabs mb-4" id="settingsTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?php echo ($active_tab == 'profile') ? 'active' : ''; ?>"
                            id="profile-tab"
                            data-bs-toggle="tab"
                            data-bs-target="#profile"
                            type="button"
                            role="tab"
                            aria-controls="profile"
                            aria-selected="<?php echo ($active_tab == 'profile') ? 'true' : 'false'; ?>">
                            <i class="fas fa-user-cog me-1"></i> Profile Settings
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?php echo ($active_tab == 'pharmacy') ? 'active' : ''; ?>"
                            id="pharmacy-tab"
                            data-bs-toggle="tab"
                            data-bs-target="#pharmacy"
                            type="button"
                            role="tab"
                            aria-controls="pharmacy"
                            aria-selected="<?php echo ($active_tab == 'pharmacy') ? 'true' : 'false'; ?>">
                            <i class="fas fa-store me-1"></i> Pharmacy Information
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?php echo ($active_tab == 'backup') ? 'active' : ''; ?>"
                            id="backup-tab"
                            data-bs-toggle="tab"
                            data-bs-target="#backup"
                            type="button"
                            role="tab"
                            aria-controls="backup"
                            aria-selected="<?php echo ($active_tab == 'backup') ? 'true' : 'false'; ?>">
                            <i class="fas fa-database me-1"></i> Backup & Restore
                        </button>
                    </li>
                </ul>

                <!-- Tab content -->
                <div class="tab-content" id="settingsTabContent">
                    <!-- Profile Settings Tab -->
                    <div class="tab-pane fade <?php echo ($active_tab == 'profile') ? 'show active' : ''; ?>" id="profile" role="tabpanel" aria-labelledby="profile-tab">
                        <?php if ($profile_updated): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="fas fa-check-circle me-2"></i> Profile updated successfully.
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($profile_error)): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="fas fa-exclamation-circle me-2"></i> <?php echo $profile_error; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <div class="row">
                            <div class="col-md-8">
                                <form method="post" action="settings.php?tab=profile" class="needs-validation" novalidate>
                                    <div class="mb-3">
                                        <label for="name" class="form-label">Full Name</label>
                                        <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                                    </div>

                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email Address</label>
                                        <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                    </div>

                                    <div class="mb-3">
                                        <label for="phone" class="form-label">Phone Number</label>
                                        <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>">
                                    </div>

                                    <hr class="my-4">
                                    <h5>Change Password</h5>
                                    <p class="text-muted small">Leave blank if you don't want to change your password.</p>

                                    <div class="mb-3">
                                        <label for="currentPassword" class="form-label">Current Password</label>
                                        <input type="password" class="form-control" id="currentPassword" name="current_password">
                                    </div>

                                    <div class="mb-3">
                                        <label for="newPassword" class="form-label">New Password</label>
                                        <input type="password" class="form-control" id="newPassword" name="new_password" minlength="8">
                                        <div class="form-text">Password must be at least 8 characters.</div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="confirmPassword" class="form-label">Confirm New Password</label>
                                        <input type="password" class="form-control" id="confirmPassword" name="confirm_password">
                                    </div>

                                    <button type="submit" name="update_profile" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i> Save Changes
                                    </button>
                                </form>
                            </div>
                            <div class="col-md-4">
                                <div class="card">
                                    <div class="card-body text-center">
                                        <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($user['name']); ?>&size=128&background=random" class="rounded-circle mb-3" alt="Profile Avatar">
                                        <h5><?php echo htmlspecialchars($user['name']); ?></h5>
                                        <p class="text-muted"><?php echo $user['role'] == 'manager' ? 'Admin / Manager' : 'Sales Rep'; ?></p>
                                        <div class="mt-3">
                                            <p><i class="fas fa-envelope me-2"></i> <?php echo htmlspecialchars($user['email']); ?></p>
                                            <?php if (!empty($user['phone'])): ?>
                                                <p><i class="fas fa-phone me-2"></i> <?php echo htmlspecialchars($user['phone']); ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Pharmacy Information Tab -->
                    <div class="tab-pane fade <?php echo ($active_tab == 'pharmacy') ? 'show active' : ''; ?>" id="pharmacy" role="tabpanel" aria-labelledby="pharmacy-tab">
                        <?php if ($pharmacy_updated): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="fas fa-check-circle me-2"></i> Pharmacy information updated successfully.
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($pharmacy_error)): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="fas fa-exclamation-circle me-2"></i> <?php echo $pharmacy_error; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <form method="post" action="settings.php?tab=pharmacy" class="needs-validation" novalidate>
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="mb-3">
                                        <label for="pharmacyName" class="form-label">Pharmacy Name</label>
                                        <input type="text" class="form-control" id="pharmacyName" name="pharmacy_name" value="<?php echo htmlspecialchars($pharmacy['name'] ?? ''); ?>" required>
                                    </div>

                                    <div class="mb-3">
                                        <label for="address" class="form-label">Address</label>
                                        <textarea class="form-control" id="address" name="address" rows="3"><?php echo htmlspecialchars($pharmacy['address'] ?? ''); ?></textarea>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="pharmacyPhone" class="form-label">Phone Number</label>
                                                <input type="tel" class="form-control" id="pharmacyPhone" name="pharmacy_phone" value="<?php echo htmlspecialchars($pharmacy['phone'] ?? ''); ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="pharmacyEmail" class="form-label">Email Address</label>
                                                <input type="email" class="form-control" id="pharmacyEmail" name="pharmacy_email" value="<?php echo htmlspecialchars($pharmacy['email'] ?? ''); ?>">
                                            </div>
                                        </div>
                                    </div>

                                    <button type="submit" name="update_pharmacy" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i> Save Changes
                                    </button>
                                </div>
                                <div class="col-md-4">
                                    <div class="card">
                                        <div class="card-body">
                                            <h5 class="card-title">Pharmacy Logo</h5>
                                            <p class="text-muted small">Upload your pharmacy logo here. This will be shown on receipts and reports.</p>
                                            <div class="text-center mb-3">
                                                <?php if (!empty($pharmacy['logo'])): ?>
                                                    <img src="<?php echo htmlspecialchars($pharmacy['logo']); ?>" class="img-fluid mb-3" alt="Pharmacy Logo" style="max-height: 150px;">
                                                <?php else: ?>
                                                    <div class="p-4 bg-light mb-3 rounded">
                                                        <i class="fas fa-store fa-4x text-secondary"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="d-grid">
                                                <button type="button" class="btn btn-outline-secondary" disabled>
                                                    <i class="fas fa-upload me-2"></i> Upload Logo
                                                </button>
                                                <small class="text-muted mt-2 text-center">Logo upload feature coming soon.</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- Backup & Restore Tab -->
                    <div class="tab-pane fade <?php echo ($active_tab == 'backup') ? 'show active' : ''; ?>" id="backup" role="tabpanel" aria-labelledby="backup-tab">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card mb-4">
                                    <div class="card-header">
                                        <h5 class="mb-0"><i class="fas fa-download me-2"></i> Backup Database</h5>
                                    </div>
                                    <div class="card-body">
                                        <p>Download a complete backup of your database. This includes all products, sales, customers, and system settings.</p>
                                        <p class="text-warning"><i class="fas fa-exclamation-triangle me-1"></i> Make regular backups to prevent data loss.</p>
                                        <form method="post" action="backup_database.php">
                                            <button type="submit" name="backup" class="btn btn-primary mt-2" disabled>
                                                <i class="fas fa-download me-2"></i> Download Backup
                                            </button>
                                            <small class="text-muted d-block mt-2">Backup feature coming soon.</small>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="mb-0"><i class="fas fa-upload me-2"></i> Restore Database</h5>
                                    </div>
                                    <div class="card-body">
                                        <p>Restore your database from a previous backup file.</p>
                                        <p class="text-danger"><i class="fas fa-exclamation-triangle me-1"></i> Warning: This will overwrite your current data!</p>
                                        <form method="post" action="restore_database.php" enctype="multipart/form-data">
                                            <div class="mb-3">
                                                <label for="backupFile" class="form-label">Select Backup File</label>
                                                <input class="form-control" type="file" id="backupFile" name="backup_file" disabled>
                                            </div>
                                            <button type="submit" name="restore" class="btn btn-danger" disabled>
                                                <i class="fas fa-upload me-2"></i> Restore Database
                                            </button>
                                            <small class="text-muted d-block mt-2">Restore feature coming soon.</small>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Password validation
        const newPasswordInput = document.getElementById('newPassword');
        const confirmPasswordInput = document.getElementById('confirmPassword');
        const currentPasswordInput = document.getElementById('currentPassword');

        function validatePasswords() {
            if (newPasswordInput.value || confirmPasswordInput.value) {
                // If either new password field has a value, current password is required
                currentPasswordInput.required = true;

                // Ensure both new password fields match
                if (newPasswordInput.value !== confirmPasswordInput.value) {
                    confirmPasswordInput.setCustomValidity('Passwords do not match');
                } else {
                    confirmPasswordInput.setCustomValidity('');
                }
            } else {
                // If no new password is being set, current password is not required
                currentPasswordInput.required = false;
            }
        }

        if (newPasswordInput && confirmPasswordInput && currentPasswordInput) {
            newPasswordInput.addEventListener('input', validatePasswords);
            confirmPasswordInput.addEventListener('input', validatePasswords);
        }

        // Form validation
        const forms = document.querySelectorAll('.needs-validation');
        Array.from(forms).forEach(form => {
            form.addEventListener('submit', event => {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });
    });
</script>

<?php
// Include footer
include_once __DIR__ .'/../includes/footer.php';
?>