<?php
require_once 'db.php';
requireLogin();

$user = getUserData();
$order_number = $_GET['order'] ?? '';

if (empty($order_number)) {
    header('Location: orders.php');
    exit();
}

// Get order details
$stmt = $pdo->prepare("
    SELECT o.*, COUNT(oi.id) as item_count
    FROM orders o
    LEFT JOIN order_items oi ON o.id = oi.order_id
    WHERE o.order_number = ? AND o.user_id = ?
    GROUP BY o.id
");
$stmt->execute([$order_number, $user['id']]);
$order = $stmt->fetch();

if (!$order) {
    header('Location: orders.php');
    exit();
}

// Get order items
$stmt = $pdo->prepare("
    SELECT oi.*, p.name, p.main_image, c.name as category_name
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    JOIN categories c ON p.category_id = c.id
    WHERE oi.order_id = ?
");
$stmt->execute([$order['id']]);
$order_items = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmation - Daraz Clone</title>
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

        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        .confirmation-card {
            background: white;
            border-radius: 15px;
            padding: 40px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            text-align: center;
            margin-bottom: 30px;
        }

        .success-icon {
            font-size: 4rem;
            color: #28a745;
            margin-bottom: 20px;
        }

        .confirmation-title {
            font-size: 2.5rem;
            color: #333;
            margin-bottom: 15px;
        }

        .confirmation-message {
            font-size: 1.2rem;
            color: #666;
            margin-bottom: 30px;
        }

        .order-number {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            font-size: 1.1rem;
            margin-bottom: 30px;
        }

        .order-details {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .section-title {
            font-size: 1.5rem;
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }

        .order-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .info-item {
            text-align: center;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
        }

        .info-label {
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }

        .info-value {
            color: #666;
        }

        .order-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px 0;
            border-bottom: 1px solid #eee;
        }

        .order-item:last-child {
            border-bottom: none;
        }

        .item-image {
            width: 60px;
            height: 60px;
            background: #f8f9fa;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .item-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .item-details {
            flex: 1;
        }

        .item-name {
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }

        .item-price {
            color: #ff6b35;
            font-weight: bold;
        }

        .item-quantity {
            color: #666;
            font-size: 0.9rem;
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 30px;
        }

        .btn {
            padding: 12px 30px;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 600;
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
            background: white;
            color: #333;
            border: 2px solid #e1e5e9;
        }

        .btn-secondary:hover {
            border-color: #ff6b35;
            color: #ff6b35;
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

        @media (max-width: 768px) {
            .confirmation-card {
                padding: 30px 20px;
            }

            .confirmation-title {
                font-size: 2rem;
            }

            .action-buttons {
                flex-direction: column;
                align-items: center;
            }

            .btn {
                width: 100%;
                max-width: 300px;
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
        </div>
    </header>

    <div class="container">
        <!-- Confirmation Message -->
        <div class="confirmation-card">
            <div class="success-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <h1 class="confirmation-title">Order Confirmed!</h1>
            <p class="confirmation-message">
                Thank you for your order! We've received your order and will process it shortly.
            </p>
            <div class="order-number">
                <strong>Order Number: <?php echo htmlspecialchars($order['order_number']); ?></strong>
            </div>
        </div>

        <!-- Order Details -->
        <div class="order-details">
            <h2 class="section-title">Order Details</h2>
            
            <div class="order-info">
                <div class="info-item">
                    <div class="info-label">Order Date</div>
                    <div class="info-value"><?php echo date('M j, Y', strtotime($order['created_at'])); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Total Amount</div>
                    <div class="info-value"><?php echo formatPrice($order['total_amount']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Payment Method</div>
                    <div class="info-value"><?php echo ucwords(str_replace('_', ' ', $order['payment_method'])); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Order Status</div>
                    <div class="info-value">
                        <span class="status-badge status-<?php echo $order['order_status']; ?>">
                            <?php echo ucfirst($order['order_status']); ?>
                        </span>
                    </div>
                </div>
            </div>

            <h3 class="section-title">Items Ordered (<?php echo $order['item_count']; ?>)</h3>
            <div class="order-items">
                <?php foreach ($order_items as $item): ?>
                    <div class="order-item">
                        <div class="item-image">
                            <img src="/placeholder.svg?height=60&width=60" alt="<?php echo htmlspecialchars($item['name']); ?>">
                        </div>
                        <div class="item-details">
                            <div class="item-name"><?php echo htmlspecialchars($item['name']); ?></div>
                            <div class="item-quantity">Quantity: <?php echo $item['quantity']; ?></div>
                            <div class="item-price"><?php echo formatPrice($item['total']); ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if (!empty($order['shipping_address'])): ?>
                <h3 class="section-title">Shipping Address</h3>
                <div class="info-item">
                    <div class="info-value"><?php echo nl2br(htmlspecialchars($order['shipping_address'])); ?></div>
                </div>
            <?php endif; ?>

            <?php if (!empty($order['notes'])): ?>
                <h3 class="section-title">Order Notes</h3>
                <div class="info-item">
                    <div class="info-value"><?php echo nl2br(htmlspecialchars($order['notes'])); ?></div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Action Buttons -->
        <div class="action-buttons">
            <a href="orders.php" class="btn btn-primary">
                <i class="fas fa-list"></i>
                View All Orders
            </a>
            <a href="products.php" class="btn btn-secondary">
                <i class="fas fa-shopping-bag"></i>
                Continue Shopping
            </a>
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-home"></i>
                Back to Home
            </a>
        </div>
    </div>
</body>
</html>
