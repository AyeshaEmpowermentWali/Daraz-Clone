<?php
require_once 'db.php';
requireLogin();

$user = getUserData();
$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_profile') {
        $full_name = sanitize($_POST['full_name']);
        $username = sanitize($_POST['username']);
        $email = sanitize($_POST['email']);
        $phone = sanitize($_POST['phone']);
        $address = sanitize($_POST['address']);
        
        // Validation
        if (empty($full_name) || empty($username) || empty($email)) {
            $error = 'Please fill in all required fields';
        } else {
            // Check if username or email already exists (excluding current user)
            $stmt = $pdo->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
            $stmt->execute([$username, $email, $user['id']]);
            
            if ($stmt->fetch()) {
                $error = 'Username or email already exists';
            } else {
                // Update user profile
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET full_name = ?, username = ?, email = ?, phone = ?, address = ?, updated_at = CURRENT_TIMESTAMP 
                    WHERE id = ?
                ");
                
                if ($stmt->execute([$full_name, $username, $email, $phone, $address, $user['id']])) {
                    $success = 'Profile updated successfully!';
                    // Refresh user data
                    $user = getUserData();
                } else {
                    $error = 'Failed to update profile. Please try again.';
                }
            }
        }
    } elseif ($action === 'change_password') {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error = 'Please fill in all password fields';
        } elseif ($new_password !== $confirm_password) {
            $error = 'New passwords do not match';
        } elseif (strlen($new_password) < 6) {
            $error = 'New password must be at least 6 characters long';
        } elseif (!password_verify($current_password, $user['password'])) {
            $error = 'Current password is incorrect';
        } else {
            // Update password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            
            if ($stmt->execute([$hashed_password, $user['id']])) {
                $success = 'Password changed successfully!';
            } else {
                $error = 'Failed to change password. Please try again.';
            }
        }
    }
}

// Get user statistics
$stmt = $pdo->prepare("SELECT COUNT(*) as total_orders FROM orders WHERE user_id = ?");
$stmt->execute([$user['id']]);
$total_orders = $stmt->fetch()['total_orders'];

$stmt = $pdo->prepare("SELECT SUM(total_amount) as total_spent FROM orders WHERE user_id = ? AND payment_status = 'paid'");
$stmt->execute([$user['id']]);
$total_spent = $stmt->fetch()['total_spent'] ?? 0;

$stmt = $pdo->prepare("SELECT COUNT(*) as cart_items FROM cart WHERE user_id = ?");
$stmt->execute([$user['id']]);
$cart_items = $stmt->fetch()['cart_items'];

$stmt = $pdo->prepare("SELECT COUNT(*) as wishlist_items FROM wishlist WHERE user_id = ?");
$stmt->execute([$user['id']]);
$wishlist_items = $stmt->fetch()['wishlist_items'];

// Get recent orders
$stmt = $pdo->prepare("
    SELECT o.*, COUNT(oi.id) as item_count 
    FROM orders o 
    LEFT JOIN order_items oi ON o.id = oi.order_id 
    WHERE o.user_id = ? 
    GROUP BY o.id 
    ORDER BY o.created_at DESC 
    LIMIT 5
");
$stmt->execute([$user['id']]);
$recent_orders = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Daraz Clone</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f5f5;
            line-height: 1.6;
        }

        .header {
            background: linear-gradient(135deg, #ff6b35, #f7931e);
            color: white;
            padding: 1rem 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .header-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-size: 1.8rem;
            font-weight: bold;
            text-decoration: none;
            color: white;
        }

        .header-nav {
            display: flex;
            gap: 20px;
        }

        .header-link {
            color: white;
            text-decoration: none;
            padding: 8px 15px;
            border-radius: 20px;
            transition: all 0.3s ease;
        }

        .header-link:hover {
            background: rgba(255,255,255,0.2);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        .page-title {
            font-size: 2.5rem;
            color: #333;
            margin-bottom: 30px;
            text-align: center;
        }

        .profile-container {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 30px;
        }

        .profile-sidebar {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            height: fit-content;
            position: sticky;
            top: 20px;
        }

        .profile-avatar {
            text-align: center;
            margin-bottom: 30px;
        }

        .avatar-circle {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, #ff6b35, #f7931e);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 3rem;
            color: white;
            font-weight: bold;
        }

        .user-name {
            font-size: 1.3rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }

        .user-email {
            color: #666;
            font-size: 0.9rem;
        }

        .user-type {
            display: inline-block;
            background: #e3f2fd;
            color: #1976d2;
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            margin-top: 10px;
        }

        .profile-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-top: 30px;
        }

        .stat-item {
            text-align: center;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
        }

        .stat-number {
            font-size: 1.5rem;
            font-weight: bold;
            color: #ff6b35;
            display: block;
        }

        .stat-label {
            font-size: 0.8rem;
            color: #666;
            text-transform: uppercase;
        }

        .profile-menu {
            list-style: none;
            margin-top: 30px;
        }

        .profile-menu li {
            margin-bottom: 10px;
        }

        .profile-menu a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 15px;
            color: #333;
            text-decoration: none;
            border-radius: 10px;
            transition: all 0.3s ease;
        }

        .profile-menu a:hover,
        .profile-menu a.active {
            background: #ff6b35;
            color: white;
        }

        .profile-content {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .section-title {
            font-size: 1.5rem;
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #ff6b35;
            background: white;
            box-shadow: 0 0 0 3px rgba(255,107,53,0.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #ff6b35, #f7931e);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(255,107,53,0.4);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-weight: 500;
        }

        .alert-error {
            background: #fee;
            color: #c33;
            border: 1px solid #fcc;
        }

        .alert-success {
            background: #efe;
            color: #363;
            border: 1px solid #cfc;
        }

        .order-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid #eee;
        }

        .order-item:last-child {
            border-bottom: none;
        }

        .order-info h4 {
            color: #333;
            margin-bottom: 5px;
        }

        .order-meta {
            color: #666;
            font-size: 0.9rem;
        }

        .order-status {
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-confirmed {
            background: #d4edda;
            color: #155724;
        }

        .status-processing {
            background: #cce7ff;
            color: #004085;
        }

        .status-shipped {
            background: #e2e3e5;
            color: #383d41;
        }

        .status-delivered {
            background: #d1ecf1;
            color: #0c5460;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #666;
        }

        .empty-state i {
            font-size: 3rem;
            color: #ddd;
            margin-bottom: 15px;
        }

        @media (max-width: 768px) {
            .profile-container {
                grid-template-columns: 1fr;
            }

            .profile-sidebar {
                position: static;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .profile-stats {
                grid-template-columns: repeat(4, 1fr);
                gap: 10px;
            }

            .stat-item {
                padding: 10px;
            }

            .stat-number {
                font-size: 1.2rem;
            }
        }

        /* Loading animation */
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid #ffffff;
            border-radius: 50%;
            border-top-color: transparent;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="header-container">
            <a href="index.php" class="logo">
                <i class="fas fa-shopping-bag"></i>
                Daraz Clone
            </a>
            <nav class="header-nav">
                <a href="index.php" class="header-link">
                    <i class="fas fa-home"></i>
                    Home
                </a>
                <a href="products.php" class="header-link">
                    <i class="fas fa-th-large"></i>
                    Products
                </a>
                <a href="cart.php" class="header-link">
                    <i class="fas fa-shopping-cart"></i>
                    Cart
                </a>
                <a href="orders.php" class="header-link">
                    <i class="fas fa-box"></i>
                    Orders
                </a>
                <a href="logout.php" class="header-link" style="background: rgba(255,255,255,0.2); border-radius: 20px;">
                    <i class="fas fa-sign-out-alt"></i>
                    Logout
                </a>
            </nav>
        </div>
    </header>

    <div class="container">
        <h1 class="page-title">My Profile</h1>

        <div class="profile-container">
            <!-- Profile Sidebar -->
            <div class="profile-sidebar">
                <div class="profile-avatar">
                    <div class="avatar-circle">
                        <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                    </div>
                    <div class="user-name"><?php echo htmlspecialchars($user['full_name']); ?></div>
                    <div class="user-email"><?php echo htmlspecialchars($user['email']); ?></div>
                    <span class="user-type"><?php echo htmlspecialchars($user['user_type']); ?></span>
                </div>

                <div class="profile-stats">
                    <div class="stat-item">
                        <span class="stat-number"><?php echo $total_orders; ?></span>
                        <span class="stat-label">Orders</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number"><?php echo formatPrice($total_spent); ?></span>
                        <span class="stat-label">Spent</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number"><?php echo $cart_items; ?></span>
                        <span class="stat-label">Cart</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number"><?php echo $wishlist_items; ?></span>
                        <span class="stat-label">Wishlist</span>
                    </div>
                </div>

                <ul class="profile-menu">
                    <li><a href="#" class="tab-link active" data-tab="profile"><i class="fas fa-user"></i> Profile Info</a></li>
                    <li><a href="#" class="tab-link" data-tab="security"><i class="fas fa-lock"></i> Security</a></li>
                    <li><a href="#" class="tab-link" data-tab="orders"><i class="fas fa-box"></i> Recent Orders</a></li>
                    <li><a href="orders.php"><i class="fas fa-list"></i> All Orders</a></li>
                    <li><a href="cart.php"><i class="fas fa-shopping-cart"></i> Shopping Cart</a></li>
                    <li><a href="#" class="tab-link" data-tab="wishlist"><i class="fas fa-heart"></i> Wishlist</a></li>
                    <li style="border-top: 1px solid #eee; margin-top: 15px; padding-top: 15px;">
                        <a href="logout.php" style="color: #dc3545;">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Profile Content -->
            <div class="profile-content">
                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>

                <!-- Profile Information Tab -->
                <div id="profile" class="tab-content active">
                    <h2 class="section-title">Profile Information</h2>
                    <form method="POST">
                        <input type="hidden" name="action" value="update_profile">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="full_name">Full Name *</label>
                                <input type="text" id="full_name" name="full_name" required value="<?php echo htmlspecialchars($user['full_name']); ?>">
                            </div>
                            <div class="form-group">
                                <label for="username">Username *</label>
                                <input type="text" id="username" name="username" required value="<?php echo htmlspecialchars($user['username']); ?>">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="email">Email Address *</label>
                                <input type="email" id="email" name="email" required value="<?php echo htmlspecialchars($user['email']); ?>">
                            </div>
                            <div class="form-group">
                                <label for="phone">Phone Number</label>
                                <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="form-group full-width">
                            <label for="address">Address</label>
                            <textarea id="address" name="address" placeholder="Enter your complete address..."><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i>
                            Update Profile
                        </button>
                    </form>
                </div>

                <!-- Security Tab -->
                <div id="security" class="tab-content">
                    <h2 class="section-title">Change Password</h2>
                    <form method="POST">
                        <input type="hidden" name="action" value="change_password">
                        
                        <div class="form-group">
                            <label for="current_password">Current Password *</label>
                            <input type="password" id="current_password" name="current_password" required>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="new_password">New Password *</label>
                                <input type="password" id="new_password" name="new_password" required>
                            </div>
                            <div class="form-group">
                                <label for="confirm_password">Confirm New Password *</label>
                                <input type="password" id="confirm_password" name="confirm_password" required>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-key"></i>
                            Change Password
                        </button>
                    </form>

                    <div style="margin-top: 30px; padding: 20px; background: #f8f9fa; border-radius: 10px;">
                        <h4 style="color: #333; margin-bottom: 10px;">
                            <i class="fas fa-shield-alt"></i>
                            Security Tips
                        </h4>
                        <ul style="color: #666; padding-left: 20px;">
                            <li>Use a strong password with at least 8 characters</li>
                            <li>Include uppercase, lowercase, numbers, and symbols</li>
                            <li>Don't use the same password for multiple accounts</li>
                            <li>Change your password regularly</li>
                        </ul>
                    </div>
                </div>

                <!-- Recent Orders Tab -->
                <div id="orders" class="tab-content">
                    <h2 class="section-title">Recent Orders</h2>
                    <?php if (empty($recent_orders)): ?>
                        <div class="empty-state">
                            <i class="fas fa-box-open"></i>
                            <h3>No orders yet</h3>
                            <p>You haven't placed any orders yet.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recent_orders as $order): ?>
                            <div class="order-item">
                                <div class="order-info">
                                    <h4>Order #<?php echo htmlspecialchars($order['order_number']); ?></h4>
                                    <div class="order-meta">
                                        <?php echo date('M j, Y', strtotime($order['created_at'])); ?> • 
                                        <?php echo $order['item_count']; ?> items • 
                                        <?php echo formatPrice($order['total_amount']); ?>
                                    </div>
                                </div>
                                <span class="order-status status-<?php echo $order['order_status']; ?>">
                                    <?php echo ucfirst($order['order_status']); ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                        <div style="text-align: center; margin-top: 20px;">
                            <a href="orders.php" class="btn btn-secondary">
                                <i class="fas fa-list"></i>
                                View All Orders
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Wishlist Tab -->
                <div id="wishlist" class="tab-content">
                    <h2 class="section-title">My Wishlist</h2>
                    <div class="empty-state">
                        <i class="fas fa-heart"></i>
                        <h3>Wishlist feature coming soon!</h3>
                        <p>We're working on bringing you a better wishlist experience.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Tab switching functionality
        document.querySelectorAll('.tab-link').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                
                const tabId = this.getAttribute('data-tab');
                if (!tabId) return;
                
                // Remove active class from all tabs and links
                document.querySelectorAll('.tab-content').forEach(tab => {
                    tab.classList.remove('active');
                });
                document.querySelectorAll('.tab-link').forEach(link => {
                    link.classList.remove('active');
                });
                
                // Add active class to clicked link and corresponding tab
                this.classList.add('active');
                document.getElementById(tabId).classList.add('active');
            });
        });

        // Form submission with loading state
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const submitBtn = this.querySelector('button[type="submit"]');
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<div class="loading"></div> Processing...';
                submitBtn.disabled = true;
                
                // Re-enable button after a delay if form doesn't submit
                setTimeout(() => {
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                }, 5000);
            });
        });

        // Password confirmation validation
        document.getElementById('confirm_password')?.addEventListener('input', function() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = this.value;
            
            if (newPassword !== confirmPassword) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });
    </script>
</body>
</html>
