<?php
require_once 'db.php';

// Get filter parameters
$category_id = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$search_query = isset($_GET['q']) ? sanitize($_GET['q']) : '';
$sort_by = isset($_GET['sort']) ? sanitize($_GET['sort']) : 'newest';
$min_price = isset($_GET['min_price']) ? (float)$_GET['min_price'] : 0;
$max_price = isset($_GET['max_price']) ? (float)$_GET['max_price'] : 0;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 12;
$offset = ($page - 1) * $per_page;

// Build WHERE clause
$where_conditions = ["p.status = 'active'"];
$params = [];

if ($category_id > 0) {
    $where_conditions[] = "p.category_id = ?";
    $params[] = $category_id;
}

if (!empty($search_query)) {
    $where_conditions[] = "(p.name LIKE ? OR p.description LIKE ? OR p.brand LIKE ?)";
    $search_param = "%{$search_query}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if ($min_price > 0) {
    $where_conditions[] = "COALESCE(p.discount_price, p.price) >= ?";
    $params[] = $min_price;
}

if ($max_price > 0) {
    $where_conditions[] = "COALESCE(p.discount_price, p.price) <= ?";
    $params[] = $max_price;
}

$where_clause = implode(' AND ', $where_conditions);

// Build ORDER BY clause
$order_by = "p.created_at DESC";
switch ($sort_by) {
    case 'price_low':
        $order_by = "COALESCE(p.discount_price, p.price) ASC";
        break;
    case 'price_high':
        $order_by = "COALESCE(p.discount_price, p.price) DESC";
        break;
    case 'rating':
        $order_by = "p.rating DESC";
        break;
    case 'popular':
        $order_by = "p.total_sold DESC";
        break;
    case 'newest':
    default:
        $order_by = "p.created_at DESC";
        break;
}

// Get total count for pagination
$count_sql = "
    SELECT COUNT(*) as total
    FROM products p
    JOIN categories c ON p.category_id = c.id
    JOIN users u ON p.seller_id = u.id
    WHERE {$where_clause}
";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_products = $count_stmt->fetch()['total'];
$total_pages = ceil($total_products / $per_page);

// Get products
$sql = "
    SELECT p.*, c.name as category_name, u.full_name as seller_name
    FROM products p
    JOIN categories c ON p.category_id = c.id
    JOIN users u ON p.seller_id = u.id
    WHERE {$where_clause}
    ORDER BY {$order_by}
    LIMIT {$per_page} OFFSET {$offset}
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Get categories for filter
$categories_stmt = $pdo->prepare("SELECT * FROM categories WHERE status = 'active' ORDER BY name");
$categories_stmt->execute();
$categories = $categories_stmt->fetchAll();

// Get current category name
$current_category = null;
if ($category_id > 0) {
    $cat_stmt = $pdo->prepare("SELECT name FROM categories WHERE id = ?");
    $cat_stmt->execute([$category_id]);
    $current_category = $cat_stmt->fetch();
}

$user = getUserData();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $current_category ? htmlspecialchars($current_category['name']) . ' - ' : ''; ?>Products - Daraz Clone</title>
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
            font-size: 1.8rem;
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

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .page-header {
            background: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }

        .page-title {
            font-size: 2.5rem;
            color: #333;
            margin-bottom: 10px;
        }

        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #666;
            margin-bottom: 20px;
        }

        .breadcrumb a {
            color: #ff6b35;
            text-decoration: none;
        }

        .breadcrumb a:hover {
            text-decoration: underline;
        }

        .results-info {
            color: #666;
            font-size: 1.1rem;
        }

        .main-content {
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 30px;
        }

        /* Filters Sidebar */
        .filters-sidebar {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            height: fit-content;
            position: sticky;
            top: 100px;
        }

        .filter-section {
            margin-bottom: 30px;
        }

        .filter-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }

        .filter-options {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .filter-option {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 0;
            cursor: pointer;
            transition: color 0.3s ease;
        }

        .filter-option:hover {
            color: #ff6b35;
        }

        .filter-option input {
            margin: 0;
        }

        .price-range {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .price-input {
            flex: 1;
            padding: 8px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 14px;
        }

        .price-input:focus {
            outline: none;
            border-color: #ff6b35;
        }

        .apply-filters {
            background: #ff6b35;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 8px;
            cursor: pointer;
            width: 100%;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .apply-filters:hover {
            background: #e55a2b;
        }

        /* Products Section */
        .products-section {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }

        .products-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 20px;
        }

        .view-options {
            display: flex;
            gap: 10px;
        }

        .view-btn {
            padding: 8px 12px;
            border: 2px solid #e1e5e9;
            background: white;
            cursor: pointer;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .view-btn.active {
            border-color: #ff6b35;
            color: #ff6b35;
        }

        .sort-select {
            padding: 10px 15px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 14px;
            cursor: pointer;
        }

        .sort-select:focus {
            outline: none;
            border-color: #ff6b35;
        }

        /* Product Grid */
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 30px;
            margin-bottom: 40px;
        }

        .product-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            position: relative;
            border: 2px solid transparent;
        }

        .product-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.2);
            border-color: #ff6b35;
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

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 40px;
        }

        .page-btn {
            padding: 10px 15px;
            border: 2px solid #e1e5e9;
            background: white;
            color: #333;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .page-btn:hover {
            border-color: #ff6b35;
            color: #ff6b35;
        }

        .page-btn.active {
            background: #ff6b35;
            color: white;
            border-color: #ff6b35;
        }

        .page-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* No Results */
        .no-results {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }

        .no-results i {
            font-size: 4rem;
            color: #ddd;
            margin-bottom: 20px;
        }

        .no-results h3 {
            font-size: 1.5rem;
            margin-bottom: 15px;
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

            .main-content {
                grid-template-columns: 1fr;
            }

            .filters-sidebar {
                position: static;
            }

            .products-header {
                flex-direction: column;
                align-items: stretch;
            }

            .product-grid {
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                gap: 20px;
            }

            .pagination {
                flex-wrap: wrap;
            }
        }

        @media (max-width: 480px) {
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
                <form action="products.php" method="GET">
                    <?php if ($category_id): ?>
                        <input type="hidden" name="category" value="<?php echo $category_id; ?>">
                    <?php endif; ?>
                    <input type="text" name="q" class="search-box" placeholder="Search for products, brands and more..." value="<?php echo htmlspecialchars($search_query); ?>">
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

    <div class="container">
        <!-- Page Header -->
        <div class="page-header">
            <div class="breadcrumb">
                <a href="index.php">Home</a>
                <i class="fas fa-chevron-right"></i>
                <?php if ($current_category): ?>
                    <span><?php echo htmlspecialchars($current_category['name']); ?></span>
                <?php else: ?>
                    <span>All Products</span>
                <?php endif; ?>
            </div>
            
            <h1 class="page-title">
                <?php if ($current_category): ?>
                    <?php echo htmlspecialchars($current_category['name']); ?>
                <?php elseif (!empty($search_query)): ?>
                    Search Results for "<?php echo htmlspecialchars($search_query); ?>"
                <?php else: ?>
                    All Products
                <?php endif; ?>
            </h1>
            
            <div class="results-info">
                Showing <?php echo min($per_page, $total_products); ?> of <?php echo $total_products; ?> products
            </div>
        </div>

        <div class="main-content">
            <!-- Filters Sidebar -->
            <div class="filters-sidebar">
                <form method="GET" action="products.php" id="filtersForm">
                    <?php if ($category_id): ?>
                        <input type="hidden" name="category" value="<?php echo $category_id; ?>">
                    <?php endif; ?>
                    <?php if (!empty($search_query)): ?>
                        <input type="hidden" name="q" value="<?php echo htmlspecialchars($search_query); ?>">
                    <?php endif; ?>
                    
                    <!-- Categories Filter -->
                    <?php if (!$category_id): ?>
                        <div class="filter-section">
                            <h3 class="filter-title">Categories</h3>
                            <div class="filter-options">
                                <?php foreach ($categories as $cat): ?>
                                    <label class="filter-option">
                                        <input type="radio" name="category" value="<?php echo $cat['id']; ?>" 
                                               <?php echo $category_id == $cat['id'] ? 'checked' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['name']); ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Price Range Filter -->
                    <div class="filter-section">
                        <h3 class="filter-title">Price Range</h3>
                        <div class="price-range">
                            <input type="number" name="min_price" class="price-input" placeholder="Min" 
                                   value="<?php echo $min_price > 0 ? $min_price : ''; ?>">
                            <span>-</span>
                            <input type="number" name="max_price" class="price-input" placeholder="Max" 
                                   value="<?php echo $max_price > 0 ? $max_price : ''; ?>">
                        </div>
                    </div>

                    <!-- Sort Filter -->
                    <div class="filter-section">
                        <h3 class="filter-title">Sort By</h3>
                        <select name="sort" class="sort-select">
                            <option value="newest" <?php echo $sort_by == 'newest' ? 'selected' : ''; ?>>Newest First</option>
                            <option value="price_low" <?php echo $sort_by == 'price_low' ? 'selected' : ''; ?>>Price: Low to High</option>
                            <option value="price_high" <?php echo $sort_by == 'price_high' ? 'selected' : ''; ?>>Price: High to Low</option>
                            <option value="rating" <?php echo $sort_by == 'rating' ? 'selected' : ''; ?>>Highest Rated</option>
                            <option value="popular" <?php echo $sort_by == 'popular' ? 'selected' : ''; ?>>Most Popular</option>
                        </select>
                    </div>

                    <button type="submit" class="apply-filters">Apply Filters</button>
                </form>
            </div>

            <!-- Products Section -->
            <div class="products-section">
                <?php if (empty($products)): ?>
                    <div class="no-results">
                        <i class="fas fa-search"></i>
                        <h3>No products found</h3>
                        <p>Try adjusting your search criteria or browse our categories.</p>
                    </div>
                <?php else: ?>
                    <div class="product-grid">
                        <?php foreach ($products as $product): ?>
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

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="page-btn">
                                    <i class="fas fa-chevron-left"></i> Previous
                                </a>
                            <?php endif; ?>

                            <?php
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);
                            
                            for ($i = $start_page; $i <= $end_page; $i++):
                            ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                                   class="page-btn <?php echo $i == $page ? 'active' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>

                            <?php if ($page < $total_pages): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="page-btn">
                                    Next <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

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

        // Auto-submit filters on change
        document.querySelectorAll('#filtersForm input, #filtersForm select').forEach(element => {
            element.addEventListener('change', function() {
                document.getElementById('filtersForm').submit();
            });
        });
    </script>
</body>
</html>
