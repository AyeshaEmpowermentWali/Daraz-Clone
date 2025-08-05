<?php
require_once 'db.php';

// Get featured products
$stmt = $pdo->prepare("
    SELECT p.*, c.name as category_name, u.full_name as seller_name 
    FROM products p 
    JOIN categories c ON p.category_id = c.id 
    JOIN users u ON p.seller_id = u.id 
    WHERE p.status = 'active' AND p.featured = 1 
    ORDER BY p.created_at DESC 
    LIMIT 8
");
$stmt->execute();
$featured_products = $stmt->fetchAll();

// Get categories
$stmt = $pdo->prepare("SELECT * FROM categories WHERE status = 'active' ORDER BY name");
$stmt->execute();
$categories = $stmt->fetchAll();

// Get latest products
$stmt = $pdo->prepare("
    SELECT p.*, c.name as category_name, u.full_name as seller_name 
    FROM products p 
    JOIN categories c ON p.category_id = c.id 
    JOIN users u ON p.seller_id = u.id 
    WHERE p.status = 'active' 
    ORDER BY p.created_at DESC 
    LIMIT 12
");
$stmt->execute();
$latest_products = $stmt->fetchAll();

$user = getUserData();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daraz Clone - Online Shopping</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f5f5f5;
        }

        /* Header Styles */
        .header {
            background: linear-gradient(135deg, #ff6b35, #f7931e);
            color: white;
            padding: 1rem 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .header-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }

        .logo {
            font-size: 2rem;
            font-weight: bold;
            text-decoration: none;
            color: white;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .search-container {
            flex: 1;
            max-width: 500px;
            margin: 0 20px;
            position: relative;
        }

        .search-box {
            width: 100%;
            padding: 12px 50px 12px 20px;
            border: none;
            border-radius: 25px;
            font-size: 16px;
            outline: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .search-btn {
            position: absolute;
            right: 5px;
            top: 50%;
            transform: translateY(-50%);
            background: #ff6b35;
            border: none;
            padding: 8px 15px;
            border-radius: 20px;
            color: white;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .search-btn:hover {
            background: #e55a2b;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .header-link {
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 5px;
            padding: 8px 15px;
            border-radius: 20px;
            transition: all 0.3s ease;
            position: relative;
        }

        .header-link:hover {
            background: rgba(255,255,255,0.2);
        }

        .cart-count {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #ff1744;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Navigation */
        .nav {
            background: white;
            padding: 15px 0;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .nav-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 30px;
        }

        .nav-link {
            color: #333;
            text-decoration: none;
            font-weight: 500;
            padding: 10px 20px;
            border-radius: 25px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .nav-link:hover {
            background: #ff6b35;
            color: white;
            transform: translateY(-2px);
        }

        /* Hero Section */
        .hero {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 80px 0;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('/placeholder.svg?height=600&width=1200') center/cover;
            opacity: 0.1;
        }

        .hero-content {
            max-width: 800px;
            margin: 0 auto;
            padding: 0 20px;
            position: relative;
            z-index: 2;
        }

        .hero h1 {
            font-size: 3.5rem;
            margin-bottom: 20px;
            font-weight: 700;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }

        .hero p {
            font-size: 1.3rem;
            margin-bottom: 30px;
            opacity: 0.9;
        }

        .cta-button {
            display: inline-block;
            background: #ff6b35;
            color: white;
            padding: 15px 40px;
            text-decoration: none;
            border-radius: 30px;
            font-size: 1.1rem;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(255,107,53,0.4);
        }

        .cta-button:hover {
            background: #e55a2b;
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(255,107,53,0.6);
        }

        /* Section Styles */
        .section {
            padding: 60px 0;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .section-title {
            text-align: center;
            font-size: 2.5rem;
            margin-bottom: 50px;
            color: #333;
            position: relative;
        }

        .section-title::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 4px;
            background: linear-gradient(135deg, #ff6b35, #f7931e);
            border-radius: 2px;
        }

        /* Product Grid */
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 30px;
            margin-top: 40px;
        }

        .product-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            position: relative;
        }

        .product-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.2);
        }

        .product-image {
            width: 100%;
            height: 250px;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: #dee2e6;
            position: relative;
            overflow: hidden;
        }

        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }

        .product-card:hover .product-image img {
            transform: scale(1.1);
        }

        .discount-badge {
            position: absolute;
            top: 15px;
            left: 15px;
            background: #ff1744;
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }

        .wishlist-btn {
            position: absolute;
            top: 15px;
            right: 15px;
            background: rgba(255,255,255,0.9);
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .wishlist-btn:hover {
            background: #ff6b35;
            color: white;
        }

        .product-info {
            padding: 20px;
        }

        .product-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 10px;
            color: #333;
            line-height: 1.4;
        }

        .product-category {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 10px;
        }

        .product-price {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
        }

        .current-price {
            font-size: 1.3rem;
            font-weight: bold;
            color: #ff6b35;
        }

        .original-price {
            font-size: 1rem;
            color: #999;
            text-decoration: line-through;
        }

        .product-rating {
            display: flex;
            align-items: center;
            gap: 5px;
            margin-bottom: 15px;
        }

        .stars {
            color: #ffc107;
        }

        .rating-text {
            color: #666;
            font-size: 0.9rem;
        }

        .add-to-cart {
            width: 100%;
            background: #ff6b35;
            color: white;
            border: none;
            padding: 12px;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .add-to-cart:hover {
            background: #e55a2b;
            transform: translateY(-2px);
        }

        /* Categories Section */
        .categories-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 30px;
            margin-top: 40px;
        }

        .category-card {
            background: white;
            border-radius: 15px;
            padding: 30px 20px;
            text-align: center;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            text-decoration: none;
            color: #333;
        }

        .category-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.2);
            color: #ff6b35;
        }

        .category-icon {
            font-size: 3rem;
            margin-bottom: 20px;
            color: #ff6b35;
        }

        .category-name {
            font-size: 1.2rem;
            font-weight: 600;
        }

        /* Footer */
        .footer {
            background: #2c3e50;
            color: white;
            padding: 50px 0 20px;
            margin-top: 80px;
        }

        .footer-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 40px;
        }

        .footer-section h3 {
            margin-bottom: 20px;
            color: #ff6b35;
        }

        .footer-section ul {
            list-style: none;
        }

        .footer-section ul li {
            margin-bottom: 10px;
        }

        .footer-section ul li a {
            color: #bdc3c7;
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .footer-section ul li a:hover {
            color: #ff6b35;
        }

        .footer-bottom {
            text-align: center;
            padding-top: 30px;
            margin-top: 30px;
            border-top: 1px solid #34495e;
            color: #bdc3c7;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .header-container {
                flex-direction: column;
                gap: 15px;
            }

            .search-container {
                order: 3;
                max-width: 100%;
                margin: 0;
            }

            .hero h1 {
                font-size: 2.5rem;
            }

            .hero p {
                font-size: 1.1rem;
            }

            .nav-container {
                gap: 15px;
            }

            .nav-link {
                padding: 8px 15px;
                font-size: 0.9rem;
            }

            .product-grid {
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                gap: 20px;
            }

            .categories-grid {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                gap: 20px;
            }
        }

        @media (max-width: 480px) {
            .hero h1 {
                font-size: 2rem;
            }

            .section-title {
                font-size: 2rem;
            }

            .product-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Loading Animation */
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #ff6b35;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Notification Styles */
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

        .notification.info {
            background: #2196f3;
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
            
            <div class="search-container">
                <form action="search.php" method="GET">
                    <input type="text" name="q" class="search-box" placeholder="Search for products, brands and more...">
                    <button type="submit" class="search-btn">
                        <i class="fas fa-search"></i>
                    </button>
                </form>
            </div>
            
            <div class="header-actions">
                <?php if (isLoggedIn()): ?>
                    <a href="cart.php" class="header-link">
                        <i class="fas fa-shopping-cart"></i>
                        Cart
                        <span class="cart-count" id="cart-count">0</span>
                    </a>
                    <a href="orders.php" class="header-link">
                        <i class="fas fa-box"></i>
                        Orders
                    </a>
                    <a href="profile.php" class="header-link">
                        <i class="fas fa-user"></i>
                        <?php echo htmlspecialchars($user['full_name']); ?>
                    </a>
                    <a href="logout.php" class="header-link" style="background: rgba(255,255,255,0.2);">
                        <i class="fas fa-sign-out-alt"></i>
                        Logout
                    </a>
                <?php else: ?>
                    <a href="login.php" class="header-link">
                        <i class="fas fa-sign-in-alt"></i>
                        Login
                    </a>
                    <a href="register.php" class="header-link">
                        <i class="fas fa-user-plus"></i>
                        Register
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- Navigation -->
    <nav class="nav">
        <div class="nav-container">
            <?php foreach ($categories as $category): ?>
                <a href="products.php?category=<?php echo $category['id']; ?>" class="nav-link">
                    <i class="fas fa-<?php echo getCategoryIcon($category['name']); ?>"></i>
                    <?php echo htmlspecialchars($category['name']); ?>
                </a>
            <?php endforeach; ?>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-content">
            <h1>Welcome to Daraz Clone</h1>
            <p>Discover amazing products at unbeatable prices. Shop now and save big!</p>
            <a href="products.php" class="cta-button">Start Shopping</a>
        </div>
    </section>

    <!-- Featured Products -->
    <section class="section">
        <div class="container">
            <h2 class="section-title">Featured Products</h2>
            <div class="product-grid">
                <?php foreach ($featured_products as $product): ?>
                    <div class="product-card">
                        <div class="product-image">
                            <img src="/placeholder.svg?height=250&width=280" alt="<?php echo htmlspecialchars($product['name']); ?>">
                            <?php if ($product['discount_price']): ?>
                                <div class="discount-badge">
                                    <?php echo round((($product['price'] - $product['discount_price']) / $product['price']) * 100); ?>% OFF
                                </div>
                            <?php endif; ?>
                            <button class="wishlist-btn" onclick="toggleWishlist(<?php echo $product['id']; ?>)">
                                <i class="far fa-heart"></i>
                            </button>
                        </div>
                        <div class="product-info">
                            <h3 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h3>
                            <p class="product-category"><?php echo htmlspecialchars($product['category_name']); ?></p>
                            <div class="product-price">
                                <span class="current-price">
                                    <?php echo formatPrice($product['discount_price'] ?: $product['price']); ?>
                                </span>
                                <?php if ($product['discount_price']): ?>
                                    <span class="original-price"><?php echo formatPrice($product['price']); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="product-rating">
                                <div class="stars">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="<?php echo $i <= $product['rating'] ? 'fas' : 'far'; ?> fa-star"></i>
                                    <?php endfor; ?>
                                </div>
                                <span class="rating-text">(<?php echo $product['total_reviews']; ?> reviews)</span>
                            </div>
                            <button class="add-to-cart" onclick="addToCart(<?php echo $product['id']; ?>)">
                                Add to Cart
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Categories -->
    <section class="section" style="background: white;">
        <div class="container">
            <h2 class="section-title">Shop by Category</h2>
            <div class="categories-grid">
                <?php foreach ($categories as $category): ?>
                    <a href="products.php?category=<?php echo $category['id']; ?>" class="category-card">
                        <div class="category-icon">
                            <i class="fas fa-<?php echo getCategoryIcon($category['name']); ?>"></i>
                        </div>
                        <div class="category-name"><?php echo htmlspecialchars($category['name']); ?></div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Latest Products -->
    <section class="section">
        <div class="container">
            <h2 class="section-title">Latest Products</h2>
            <div class="product-grid">
                <?php foreach ($latest_products as $product): ?>
                    <div class="product-card">
                        <div class="product-image">
                            <img src="/placeholder.svg?height=250&width=280" alt="<?php echo htmlspecialchars($product['name']); ?>">
                            <?php if ($product['discount_price']): ?>
                                <div class="discount-badge">
                                    <?php echo round((($product['price'] - $product['discount_price']) / $product['price']) * 100); ?>% OFF
                                </div>
                            <?php endif; ?>
                            <button class="wishlist-btn" onclick="toggleWishlist(<?php echo $product['id']; ?>)">
                                <i class="far fa-heart"></i>
                            </button>
                        </div>
                        <div class="product-info">
                            <h3 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h3>
                            <p class="product-category"><?php echo htmlspecialchars($product['category_name']); ?></p>
                            <div class="product-price">
                                <span class="current-price">
                                    <?php echo formatPrice($product['discount_price'] ?: $product['price']); ?>
                                </span>
                                <?php if ($product['discount_price']): ?>
                                    <span class="original-price"><?php echo formatPrice($product['price']); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="product-rating">
                                <div class="stars">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="<?php echo $i <= $product['rating'] ? 'fas' : 'far'; ?> fa-star"></i>
                                    <?php endfor; ?>
                                </div>
                                <span class="rating-text">(<?php echo $product['total_reviews']; ?> reviews)</span>
                            </div>
                            <button class="add-to-cart" onclick="addToCart(<?php echo $product['id']; ?>)">
                                Add to Cart
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="footer-content">
            <div class="footer-section">
                <h3>Customer Service</h3>
                <ul>
                    <li><a href="#">Help Center</a></li>
                    <li><a href="#">How to Buy</a></li>
                    <li><a href="#">Returns & Refunds</a></li>
                    <li><a href="#">Contact Us</a></li>
                </ul>
            </div>
            <div class="footer-section">
                <h3>About Daraz</h3>
                <ul>
                    <li><a href="#">About Us</a></li>
                    <li><a href="#">Careers</a></li>
                    <li><a href="#">Press & Media</a></li>
                    <li><a href="#">Investor Relations</a></li>
                </ul>
            </div>
            <div class="footer-section">
                <h3>Payment Methods</h3>
                <ul>
                    <li><a href="#">Credit Card</a></li>
                    <li><a href="#">Debit Card</a></li>
                    <li><a href="#">PayPal</a></li>
                    <li><a href="#">Cash on Delivery</a></li>
                </ul>
            </div>
            <div class="footer-section">
                <h3>Follow Us</h3>
                <ul>
                    <li><a href="#"><i class="fab fa-facebook"></i> Facebook</a></li>
                    <li><a href="#"><i class="fab fa-twitter"></i> Twitter</a></li>
                    <li><a href="#"><i class="fab fa-instagram"></i> Instagram</a></li>
                    <li><a href="#"><i class="fab fa-youtube"></i> YouTube</a></li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; 2024 Daraz Clone. All rights reserved.</p>
        </div>
    </footer>

    <script>
        // Cart functionality
        function addToCart(productId) {
            <?php if (!isLoggedIn()): ?>
                showNotification('Please login to add items to cart', 'error');
                setTimeout(() => {
                    window.location.href = 'login.php';
                }, 1500);
                return;
            <?php endif; ?>

            const button = event.target;
            const originalText = button.textContent;
            button.innerHTML = '<div class="loading"></div>';
            button.disabled = true;

            fetch('cart_handler.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=add&product_id=${productId}&quantity=1`
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.text();
            })
            .then(text => {
                console.log('Raw response:', text); // Debug log
                try {
                    const data = JSON.parse(text);
                    if (data.success) {
                        showNotification('Product added to cart!', 'success');
                        updateCartCount();
                    } else {
                        showNotification(data.message || 'Failed to add product to cart', 'error');
                    }
                } catch (e) {
                    console.error('JSON parse error:', e);
                    console.error('Response text:', text);
                    showNotification('Server error occurred', 'error');
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                showNotification('Network error occurred', 'error');
            })
            .finally(() => {
                button.textContent = originalText;
                button.disabled = false;
            });
        }

        // Wishlist functionality
        function toggleWishlist(productId) {
            <?php if (!isLoggedIn()): ?>
                showNotification('Please login to add items to wishlist', 'error');
                setTimeout(() => {
                    window.location.href = 'login.php';
                }, 1500);
                return;
            <?php endif; ?>

            const button = event.target.closest('.wishlist-btn');
            const icon = button.querySelector('i');
            
            fetch('wishlist_handler.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=toggle&product_id=${productId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (data.added) {
                        icon.className = 'fas fa-heart';
                        showNotification('Added to wishlist!', 'success');
                    } else {
                        icon.className = 'far fa-heart';
                        showNotification('Removed from wishlist!', 'info');
                    }
                } else {
                    showNotification(data.message || 'Failed to update wishlist', 'error');
                }
            })
            .catch(error => {
                showNotification('An error occurred', 'error');
            });
        }

        // Update cart count with better error handling
        function updateCartCount() {
            <?php if (isLoggedIn()): ?>
                fetch('cart_handler.php?action=count')
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success !== false) {
                        const cartCount = document.getElementById('cart-count');
                        if (cartCount) {
                            const count = data.count || 0;
                            cartCount.textContent = count;
                            cartCount.style.display = count > 0 ? 'flex' : 'none';
                        }
                    }
                })
                .catch(error => {
                    console.error('Error updating cart count:', error);
                });
            <?php endif; ?>
        }

        // Show notification
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

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            updateCartCount();
        });

        // Search functionality
        document.querySelector('.search-box').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                const query = this.value.trim();
                if (query) {
                    window.location.href = `search.php?q=${encodeURIComponent(query)}`;
                }
            }
        });
    </script>
</body>
</html>

<?php
function getCategoryIcon($categoryName) {
    $icons = [
        'Electronics' => 'laptop',
        'Fashion' => 'tshirt',
        'Home & Kitchen' => 'home',
        'Sports & Outdoors' => 'football-ball',
        'Books & Media' => 'book',
        'Health & Beauty' => 'heart'
    ];
    return $icons[$categoryName] ?? 'tag';
}
?>
