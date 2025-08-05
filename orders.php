<?php
require_once 'db.php';
requireLogin();

$user = getUserData();

// Get user's orders
$stmt = $pdo->prepare("
    SELECT o.*, COUNT(oi.id) as item_count, SUM(oi.quantity) as total_items
    FROM orders o
    LEFT JOIN order_items oi ON o.id = oi.order_id
    WHERE o.user_id = ?
    GROUP BY o.id
    ORDER BY o.created_at DESC
");
$stmt->execute([$user['id']]);
$orders = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders - Daraz Clone</title>
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

        .orders-container {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .order-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }

        .order-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }

        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .order-number {
            font-size: 1.2rem;
            font-weight: bold;
            color: #333;
        }

        .order-date {
            color: #666;
            font-size: 0.9rem;
        }

        .status-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
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

        .order-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .info-item {
            text-align: center;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
        }

        .info-label {
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
            font-size: 0.9rem;
        }

        .info-value {
            color: #666;
        }

        .order-total {
            font-size: 1.2rem;
            font-weight: bold;
            color: #ff6b35;
        }

        .order-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            flex-wrap: wrap;
        }

        .btn {
            padding: 8px 20px;
            border-radius: 20px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
        }

        .btn-primary {
            background: #ff6b35;
            color: white;
        }

        .btn-primary:hover {
            background: #e55a2b;
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: white;
            color: #333;
            border: 2px solid #e1e5e9;
        }

        .btn-secondary:hover {
            border-color: #ff6b35;
            color: #ff6b35;
        }

        .empty-orders {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }

        .empty-orders i {
            font-size: 4rem;
            color: #ddd;
            margin-bottom: 20px;
        }

        .empty-orders h3 {
            font-size: 1.5rem;
            margin-bottom: 15px;
        }

        .continue-shopping {
            display: inline-block;
            background: #ff6b35;
            color: white;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 25px;
            margin-top: 20px;
            transition: all 0.3s ease;
        }

        .continue-shopping:hover {
            background: #e55a2b;
            transform: translateY(-2px);
        }

        @media (max-width: 768px) {
            .order-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .order-info {
                grid-template-columns: 1fr 1fr;
            }

            .order-actions {
                justify-content: center;
            }
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
                <a href="profile.php" class="header-link">
                    <i class="fas fa-user"></i>
                    Profile
                </a>
            </nav>
        </div>
    </header>

    <div class="container">
        <h1 class="page-title">My Orders</h1>

        <?php if (empty($orders)): ?>
            <div class="empty-orders">
                <i class="fas fa-box-open"></i>
                <h3>No orders yet</h3>
                <p>You haven't placed any orders yet. Start shopping to see your orders here.</p>
                <a href="products.php" class="continue-shopping">
                    <i class="fas fa-shopping-bag"></i>
                    Start Shopping
                </a>
            </div>
        <?php else: ?>
            <div class="orders-container">
                <?php foreach ($orders as $order): ?>
                    <div class="order-card">
                        <div class="order-header">
                            <div>
                                <div class="order-number">Order #<?php echo htmlspecialchars($order['order_number']); ?></div>
                                <div class="order-date">Placed on <?php echo date('M j, Y', strtotime($order['created_at'])); ?></div>
                            </div>
                            <span class="status-badge status-<?php echo $order['order_status']; ?>">
                                <?php echo ucfirst($order['order_status']); ?>
                            </span>
                        </div>

                        <div class="order-info">
                            <div class="info-item">
                                <div class="info-label">Items</div>
                                <div class="info-value"><?php echo $order['total_items']; ?> items</div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Payment</div>
                                <div class="info-value"><?php echo ucwords(str_replace('_', ' ', $order['payment_method'])); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Total</div>
                                <div class="info-value order-total"><?php echo formatPrice($order['total_amount']); ?></div>
                            </div>
                        </div>

                        <div class="order-actions">
                            <a href="order_details.php?order=<?php echo $order['order_number']; ?>" class="btn btn-primary">
                                <i class="fas fa-eye"></i>
                                View Details
                            </a>
                            <?php if ($order['order_status'] === 'pending'): ?>
                                <a href="#" class="btn btn-secondary" onclick="cancelOrder('<?php echo $order['order_number']; ?>')">
                                    <i class="fas fa-times"></i>
                                    Cancel Order
                                </a>
                            <?php endif; ?>
                            <?php if ($order['order_status'] === 'delivered'): ?>
                                <a href="#" class="btn btn-secondary">
                                    <i class="fas fa-star"></i>
                                    Rate Products
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function cancelOrder(orderNumber) {
            if (confirm('Are you sure you want to cancel this order?')) {
                // This would typically make an AJAX call to cancel the order
                alert('Order cancellation functionality will be implemented soon!');
            }
        }
    </script>
</body>
</html>
