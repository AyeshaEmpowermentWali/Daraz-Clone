<?php
require_once 'db.php';
requireLogin();

$user = getUserData();

// Get cart items
$stmt = $pdo->prepare("
    SELECT c.*, p.name, p.price, p.discount_price, p.main_image, p.stock_quantity,
           cat.name as category_name, u.full_name as seller_name
    FROM cart c
    JOIN products p ON c.product_id = p.id
    JOIN categories cat ON p.category_id = cat.id
    JOIN users u ON p.seller_id = u.id
    WHERE c.user_id = ?
    ORDER BY c.added_at DESC
");
$stmt->execute([$user['id']]);
$cart_items = $stmt->fetchAll();

// Redirect if cart is empty
if (empty($cart_items)) {
    header('Location: cart.php');
    exit();
}

// Calculate totals
$subtotal = 0;
$total_items = 0;
foreach ($cart_items as $item) {
    $price = $item['discount_price'] ?: $item['price'];
    $subtotal += $price * $item['quantity'];
    $total_items += $item['quantity'];
}

$shipping = $subtotal > 50 ? 0 : 5.99;
$tax = $subtotal * 0.08; // 8% tax
$total = $subtotal + $shipping + $tax;

// Handle form submission
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $shipping_address = sanitize($_POST['shipping_address']);
    $payment_method = sanitize($_POST['payment_method']);
    $notes = sanitize($_POST['notes'] ?? '');
    
    if (empty($shipping_address) || empty($payment_method)) {
        $error = 'Please fill in all required fields';
    } else {
        try {
            $pdo->beginTransaction();
            
            // Generate order number
            $order_number = generateOrderNumber();
            
            // Create order
            $stmt = $pdo->prepare("
                INSERT INTO orders (user_id, order_number, total_amount, shipping_address, payment_method, notes, order_status, payment_status) 
                VALUES (?, ?, ?, ?, ?, ?, 'pending', 'pending')
            ");
            $stmt->execute([$user['id'], $order_number, $total, $shipping_address, $payment_method, $notes]);
            $order_id = $pdo->lastInsertId();
            
            // Add order items
            foreach ($cart_items as $item) {
                $price = $item['discount_price'] ?: $item['price'];
                $item_total = $price * $item['quantity'];
                
                $stmt = $pdo->prepare("
                    INSERT INTO order_items (order_id, product_id, seller_id, quantity, price, total) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $order_id, 
                    $item['product_id'], 
                    $item['seller_id'], 
                    $item['quantity'], 
                    $price, 
                    $item_total
                ]);
                
                // Update product stock
                $stmt = $pdo->prepare("UPDATE products SET stock_quantity = stock_quantity - ?, total_sold = total_sold + ? WHERE id = ?");
                $stmt->execute([$item['quantity'], $item['quantity'], $item['product_id']]);
            }
            
            // Clear cart
            $stmt = $pdo->prepare("DELETE FROM cart WHERE user_id = ?");
            $stmt->execute([$user['id']]);
            
            $pdo->commit();
            
            // Redirect to order confirmation
            header("Location: order_confirmation.php?order=" . $order_number);
            exit();
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Failed to process order. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - Daraz Clone</title>
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

        .checkout-container {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
            margin-top: 20px;
        }

        .checkout-form {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }

        .order-summary {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            height: fit-content;
            position: sticky;
            top: 20px;
        }

        .section-title {
            font-size: 1.5rem;
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
        }

        .form-group input,
        .form-group select,
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
        .form-group select:focus,
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

        .payment-methods {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 10px;
        }

        .payment-option {
            padding: 15px;
            border: 2px solid #e1e5e9;
            border-radius: 10px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            background: white;
        }

        .payment-option:hover {
            border-color: #ff6b35;
        }

        .payment-option.selected {
            border-color: #ff6b35;
            background: rgba(255,107,53,0.1);
        }

        .payment-option input {
            display: none;
        }

        .payment-option i {
            font-size: 2rem;
            color: #ff6b35;
            margin-bottom: 10px;
            display: block;
        }

        .payment-option h4 {
            color: #333;
            margin-bottom: 5px;
        }

        .payment-option p {
            color: #666;
            font-size: 0.9rem;
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

        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            padding: 10px 0;
        }

        .summary-row.total {
            border-top: 2px solid #eee;
            font-size: 1.2rem;
            font-weight: bold;
            color: #333;
            margin-top: 20px;
            padding-top: 20px;
        }

        .place-order-btn {
            width: 100%;
            background: linear-gradient(135deg, #ff6b35, #f7931e);
            color: white;
            border: none;
            padding: 15px;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 20px;
        }

        .place-order-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(255,107,53,0.4);
        }

        .place-order-btn:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
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

        .security-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
            border-left: 4px solid #28a745;
        }

        .security-info h4 {
            color: #28a745;
            margin-bottom: 10px;
        }

        .security-info p {
            color: #666;
            font-size: 0.9rem;
        }

        @media (max-width: 768px) {
            .checkout-container {
                grid-template-columns: 1fr;
            }

            .payment-methods {
                grid-template-columns: 1fr;
            }

            .order-item {
                flex-direction: column;
                text-align: center;
            }

            .item-details {
                text-align: center;
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
            </nav>
        </div>
    </header>

    <div class="container">
        <h1 class="page-title">Checkout</h1>

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

        <div class="checkout-container">
            <div class="checkout-form">
                <form method="POST" id="checkoutForm">
                    <!-- Shipping Information -->
                    <div class="section">
                        <h3 class="section-title">
                            <i class="fas fa-shipping-fast"></i>
                            Shipping Information
                        </h3>
                        
                        <div class="form-group">
                            <label for="shipping_address">Shipping Address *</label>
                            <textarea id="shipping_address" name="shipping_address" required placeholder="Enter your complete shipping address..."><?php echo htmlspecialchars($_POST['shipping_address'] ?? $user['address'] ?? ''); ?></textarea>
                        </div>
                    </div>

                    <!-- Payment Method -->
                    <div class="section">
                        <h3 class="section-title">
                            <i class="fas fa-credit-card"></i>
                            Payment Method
                        </h3>
                        
                        <div class="payment-methods">
                            <label class="payment-option" onclick="selectPayment('credit_card')">
                                <input type="radio" name="payment_method" value="credit_card" required>
                                <i class="fas fa-credit-card"></i>
                                <h4>Credit Card</h4>
                                <p>Visa, MasterCard, Amex</p>
                            </label>
                            
                            <label class="payment-option" onclick="selectPayment('debit_card')">
                                <input type="radio" name="payment_method" value="debit_card" required>
                                <i class="fas fa-money-check-alt"></i>
                                <h4>Debit Card</h4>
                                <p>Direct bank payment</p>
                            </label>
                            
                            <label class="payment-option" onclick="selectPayment('paypal')">
                                <input type="radio" name="payment_method" value="paypal" required>
                                <i class="fab fa-paypal"></i>
                                <h4>PayPal</h4>
                                <p>Secure online payment</p>
                            </label>
                            
                            <label class="payment-option" onclick="selectPayment('cash_on_delivery')">
                                <input type="radio" name="payment_method" value="cash_on_delivery" required>
                                <i class="fas fa-hand-holding-usd"></i>
                                <h4>Cash on Delivery</h4>
                                <p>Pay when you receive</p>
                            </label>
                        </div>
                    </div>

                    <!-- Order Notes -->
                    <div class="section">
                        <h3 class="section-title">
                            <i class="fas fa-sticky-note"></i>
                            Order Notes (Optional)
                        </h3>
                        
                        <div class="form-group">
                            <label for="notes">Special Instructions</label>
                            <textarea id="notes" name="notes" placeholder="Any special delivery instructions or notes..."><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
                        </div>
                    </div>

                    <div class="security-info">
                        <h4><i class="fas fa-shield-alt"></i> Secure Checkout</h4>
                        <p>Your payment information is encrypted and secure. We never store your credit card details.</p>
                    </div>
                </form>
            </div>

            <div class="order-summary">
                <h3 class="section-title">Order Summary</h3>
                
                <!-- Order Items -->
                <div class="order-items">
                    <?php foreach ($cart_items as $item): ?>
                        <div class="order-item">
                            <div class="item-image">
                                <img src="/placeholder.svg?height=60&width=60" alt="<?php echo htmlspecialchars($item['name']); ?>">
                            </div>
                            <div class="item-details">
                                <div class="item-name"><?php echo htmlspecialchars($item['name']); ?></div>
                                <div class="item-quantity">Qty: <?php echo $item['quantity']; ?></div>
                                <div class="item-price">
                                    <?php echo formatPrice(($item['discount_price'] ?: $item['price']) * $item['quantity']); ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Price Breakdown -->
                <div class="price-breakdown">
                    <div class="summary-row">
                        <span>Items (<?php echo $total_items; ?>):</span>
                        <span><?php echo formatPrice($subtotal); ?></span>
                    </div>
                    
                    <div class="summary-row">
                        <span>Shipping:</span>
                        <span><?php echo $shipping > 0 ? formatPrice($shipping) : 'FREE'; ?></span>
                    </div>
                    
                    <div class="summary-row">
                        <span>Tax:</span>
                        <span><?php echo formatPrice($tax); ?></span>
                    </div>
                    
                    <div class="summary-row total">
                        <span>Total:</span>
                        <span><?php echo formatPrice($total); ?></span>
                    </div>
                </div>

                <button type="submit" form="checkoutForm" class="place-order-btn" id="placeOrderBtn">
                    <i class="fas fa-lock"></i>
                    Place Order - <?php echo formatPrice($total); ?>
                </button>
            </div>
        </div>
    </div>

    <script>
        function selectPayment(method) {
            // Remove selected class from all options
            document.querySelectorAll('.payment-option').forEach(option => {
                option.classList.remove('selected');
            });
            
            // Add selected class to clicked option
            event.currentTarget.classList.add('selected');
            
            // Check the radio button
            document.querySelector(`input[value="${method}"]`).checked = true;
        }

        // Form submission with loading state
        document.getElementById('checkoutForm').addEventListener('submit', function(e) {
            const btn = document.getElementById('placeOrderBtn');
            btn.innerHTML = '<div class="loading"></div> Processing Order...';
            btn.disabled = true;
            
            // Validate required fields
            const shippingAddress = document.getElementById('shipping_address').value.trim();
            const paymentMethod = document.querySelector('input[name="payment_method"]:checked');
            
            if (!shippingAddress || !paymentMethod) {
                e.preventDefault();
                btn.innerHTML = '<i class="fas fa-lock"></i> Place Order - <?php echo formatPrice($total); ?>';
                btn.disabled = false;
                alert('Please fill in all required fields');
                return;
            }
        });

        // Auto-select first payment method if none selected
        document.addEventListener('DOMContentLoaded', function() {
            const selectedPayment = document.querySelector('input[name="payment_method"]:checked');
            if (!selectedPayment) {
                selectPayment('cash_on_delivery');
                document.querySelector('input[value="cash_on_delivery"]').checked = true;
            }
        });
    </script>
</body>
</html>
