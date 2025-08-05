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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart - Daraz Clone</title>
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

        .cart-container {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
            margin-top: 20px;
        }

        .cart-items {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }

        .cart-summary {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            height: fit-content;
            position: sticky;
            top: 20px;
        }

        .cart-item {
            display: grid;
            grid-template-columns: 100px 1fr auto;
            gap: 20px;
            padding: 20px 0;
            border-bottom: 1px solid #eee;
            align-items: center;
        }

        .cart-item:last-child {
            border-bottom: none;
        }

        .item-image {
            width: 100px;
            height: 100px;
            background: #f8f9fa;
            border-radius: 10px;
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

        .item-details h3 {
            font-size: 1.2rem;
            color: #333;
            margin-bottom: 5px;
        }

        .item-category {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 10px;
        }

        .item-price {
            font-size: 1.1rem;
            font-weight: bold;
            color: #ff6b35;
            margin-bottom: 15px;
        }

        .quantity-controls {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }

        .qty-btn {
            width: 35px;
            height: 35px;
            border: 2px solid #ff6b35;
            background: white;
            color: #ff6b35;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .qty-btn:hover {
            background: #ff6b35;
            color: white;
        }

        .qty-input {
            width: 60px;
            text-align: center;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            padding: 8px;
            font-weight: bold;
        }

        .item-actions {
            text-align: right;
        }

        .item-total {
            font-size: 1.3rem;
            font-weight: bold;
            color: #333;
            margin-bottom: 15px;
        }

        .remove-btn {
            background: #ff4757;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 20px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .remove-btn:hover {
            background: #ff3742;
            transform: translateY(-2px);
        }

        .empty-cart {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }

        .empty-cart i {
            font-size: 4rem;
            color: #ddd;
            margin-bottom: 20px;
        }

        .empty-cart h3 {
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

        .summary-title {
            font-size: 1.5rem;
            color: #333;
            margin-bottom: 20px;
            text-align: center;
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

        .checkout-btn {
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

        .checkout-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(255,107,53,0.4);
        }

        .checkout-btn:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }

        .promo-code {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }

        .promo-input {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }

        .promo-input input {
            flex: 1;
            padding: 10px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
        }

        .promo-btn {
            background: #28a745;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
        }

        @media (max-width: 768px) {
            .cart-container {
                grid-template-columns: 1fr;
            }

            .cart-item {
                grid-template-columns: 80px 1fr;
                gap: 15px;
            }

            .item-actions {
                grid-column: 1 / -1;
                text-align: left;
                margin-top: 15px;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }

            .quantity-controls {
                margin-bottom: 0;
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

        /* Notification styles */
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            border-radius: 8px;
            color: white;
            font-weight: 500;
            z-index: 10000;
            transform: translateX(400px);
            transition: transform 0.3s ease;
        }

        .notification.show {
            transform: translateX(0);
        }

        .notification.success {
            background: #4caf50;
        }

        .notification.error {
            background: #f44336;
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
                <a href="orders.php" class="header-link">
                    <i class="fas fa-box"></i>
                    Orders
                </a>
                <a href="profile.php" class="header-link">
                    <i class="fas fa-user"></i>
                    Profile
                </a>
                <a href="logout.php" class="header-link" style="background: rgba(255,255,255,0.2);">
                    <i class="fas fa-sign-out-alt"></i>
                    Logout
                </a>
            </nav>
        </div>
    </header>

    <div class="container">
        <h1 class="page-title">Shopping Cart</h1>

        <?php if (empty($cart_items)): ?>
            <div class="empty-cart">
                <i class="fas fa-shopping-cart"></i>
                <h3>Your cart is empty</h3>
                <p>Add some products to your cart to see them here.</p>
                <a href="products.php" class="continue-shopping">
                    <i class="fas fa-arrow-left"></i>
                    Continue Shopping
                </a>
            </div>
        <?php else: ?>
            <div class="cart-container">
                <div class="cart-items">
                    <?php foreach ($cart_items as $item): ?>
                        <div class="cart-item" data-item-id="<?php echo $item['id']; ?>">
                            <div class="item-image">
                                <img src="/placeholder.svg?height=100&width=100" alt="<?php echo htmlspecialchars($item['name']); ?>">
                            </div>
                            
                            <div class="item-details">
                                <h3><?php echo htmlspecialchars($item['name']); ?></h3>
                                <p class="item-category"><?php echo htmlspecialchars($item['category_name']); ?></p>
                                <p class="item-price">
                                    <?php echo formatPrice($item['discount_price'] ?: $item['price']); ?>
                                </p>
                                <div class="quantity-controls">
                                    <button class="qty-btn" onclick="updateQuantity(<?php echo $item['id']; ?>, -1)">
                                        <i class="fas fa-minus"></i>
                                    </button>
                                    <input type="number" class="qty-input" value="<?php echo $item['quantity']; ?>" 
                                           min="1" max="<?php echo $item['stock_quantity']; ?>"
                                           onchange="updateQuantity(<?php echo $item['id']; ?>, 0, this.value)">
                                    <button class="qty-btn" onclick="updateQuantity(<?php echo $item['id']; ?>, 1)">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="item-actions">
                                <div class="item-total">
                                    <?php echo formatPrice(($item['discount_price'] ?: $item['price']) * $item['quantity']); ?>
                                </div>
                                <button class="remove-btn" onclick="removeFromCart(<?php echo $item['id']; ?>)">
                                    <i class="fas fa-trash"></i>
                                    Remove
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="cart-summary">
                    <h3 class="summary-title">Order Summary</h3>
                    
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

                    <div class="promo-code">
                        <label>Promo Code:</label>
                        <div class="promo-input">
                            <input type="text" placeholder="Enter code" id="promoCode">
                            <button class="promo-btn" onclick="applyPromoCode()">Apply</button>
                        </div>
                    </div>

                    <button class="checkout-btn" onclick="proceedToCheckout()">
                        Proceed to Checkout
                    </button>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function updateQuantity(cartId, change, newValue = null) {
            const cartItem = document.querySelector(`[data-item-id="${cartId}"]`);
            const qtyInput = cartItem.querySelector('.qty-input');
            
            let quantity;
            if (newValue !== null) {
                quantity = parseInt(newValue);
            } else {
                quantity = parseInt(qtyInput.value) + change;
            }
            
            if (quantity < 1) quantity = 1;
            if (quantity > parseInt(qtyInput.max)) quantity = parseInt(qtyInput.max);
            
            qtyInput.value = quantity;
            
            // Update cart via AJAX
            fetch('cart_handler.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=update&cart_id=${cartId}&quantity=${quantity}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload(); // Reload to update totals
                } else {
                    showNotification(data.message || 'Failed to update quantity', 'error');
                }
            })
            .catch(error => {
                showNotification('An error occurred', 'error');
            });
        }

        function removeFromCart(cartId) {
            if (!confirm('Are you sure you want to remove this item from your cart?')) {
                return;
            }

            fetch('cart_handler.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=remove&cart_id=${cartId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Item removed from cart', 'success');
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                } else {
                    showNotification(data.message || 'Failed to remove item', 'error');
                }
            })
            .catch(error => {
                showNotification('An error occurred', 'error');
            });
        }

        function applyPromoCode() {
            const promoCode = document.getElementById('promoCode').value.trim();
            if (!promoCode) {
                showNotification('Please enter a promo code', 'error');
                return;
            }

            // This would typically make an AJAX call to validate the promo code
            showNotification('Promo code functionality will be implemented soon!', 'info');
        }

        function proceedToCheckout() {
            window.location.href = 'checkout.php';
        }

        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            notification.textContent = message;
            document.body.appendChild(notification);

            setTimeout(() => {
                notification.classList.add('show');
            }, 100);

            setTimeout(() => {
                notification.classList.remove('show');
                setTimeout(() => {
                    document.body.removeChild(notification);
                }, 300);
            }, 3000);
        }
    </script>
</body>
</html>
