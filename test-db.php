<?php
require_once 'db.php';

echo "<h2>Database Connection & Cart Test</h2>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .success { color: green; }
    .error { color: red; }
    .info { color: blue; }
    .warning { color: orange; }
    .test-section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
</style>";

try {
    // Test database connection
    if ($pdo) {
        echo "<div class='test-section'>";
        echo "<h3>Database Connection</h3>";
        echo "<p class='success'>✅ Database connection successful!</p>";
        
        // Test if tables exist
        $tables = ['users', 'products', 'categories', 'cart'];
        foreach ($tables as $table) {
            $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
            if ($stmt->rowCount() > 0) {
                echo "<p class='success'>✅ Table '$table' exists</p>";
            } else {
                echo "<p class='error'>❌ Table '$table' missing</p>";
            }
        }
        echo "</div>";
        
        // Test sample data
        echo "<div class='test-section'>";
        echo "<h3>Data Counts</h3>";
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM products WHERE status = 'active'");
        $result = $stmt->fetch();
        echo "<p class='info'>📊 Active products: " . $result['count'] . "</p>";
        
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM categories WHERE status = 'active'");
        $result = $stmt->fetch();
        echo "<p class='info'>📊 Active categories: " . $result['count'] . "</p>";
        
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
        $result = $stmt->fetch();
        echo "<p class='info'>📊 Total users: " . $result['count'] . "</p>";
        echo "</div>";
        
        // Test session and user
        echo "<div class='test-section'>";
        echo "<h3>Session & Authentication</h3>";
        if (session_status() == PHP_SESSION_ACTIVE) {
            echo "<p class='success'>✅ Session is active</p>";
            if (isLoggedIn()) {
                $user = getUserData();
                if ($user) {
                    echo "<p class='success'>👤 Logged in as: " . htmlspecialchars($user['full_name']) . " (ID: " . $user['id'] . ")</p>";
                    echo "<p class='info'>📧 Email: " . htmlspecialchars($user['email']) . "</p>";
                    echo "<p class='info'>👥 User type: " . htmlspecialchars($user['user_type']) . "</p>";
                    
                    // Test if user exists in database
                    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE id = ?");
                    $stmt->execute([$user['id']]);
                    $userExists = $stmt->fetch();
                    if ($userExists['count'] > 0) {
                        echo "<p class='success'>✅ User exists in database</p>";
                    } else {
                        echo "<p class='error'>❌ User ID not found in database!</p>";
                    }
                } else {
                    echo "<p class='error'>❌ User data not found</p>";
                }
            } else {
                echo "<p class='warning'>👤 Not logged in</p>";
                echo "<p class='info'>🔗 <a href='login.php'>Login here</a> | <a href='register.php'>Register here</a></p>";
            }
        } else {
            echo "<p class='error'>❌ Session not active</p>";
        }
        echo "</div>";
        
        // Test cart functionality if logged in
        if (isLoggedIn()) {
            echo "<div class='test-section'>";
            echo "<h3>Cart Test</h3>";
            $user = getUserData();
            
            // Check current cart items
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM cart WHERE user_id = ?");
            $stmt->execute([$user['id']]);
            $cartCount = $stmt->fetch();
            echo "<p class='info'>🛒 Current cart items: " . $cartCount['count'] . "</p>";
            
            // Test foreign key constraints
            $stmt = $pdo->query("
                SELECT 
                    CONSTRAINT_NAME, 
                    REFERENCED_TABLE_NAME, 
                    REFERENCED_COLUMN_NAME 
                FROM information_schema.KEY_COLUMN_USAGE 
                WHERE TABLE_NAME = 'cart' 
                AND TABLE_SCHEMA = DATABASE()
                AND REFERENCED_TABLE_NAME IS NOT NULL
            ");
            $constraints = $stmt->fetchAll();
            
            if ($constraints) {
                echo "<p class='success'>✅ Cart foreign key constraints:</p>";
                foreach ($constraints as $constraint) {
                    echo "<p class='info'>   - " . $constraint['CONSTRAINT_NAME'] . " → " . 
                         $constraint['REFERENCED_TABLE_NAME'] . "." . $constraint['REFERENCED_COLUMN_NAME'] . "</p>";
                }
            } else {
                echo "<p class='warning'>⚠️ No foreign key constraints found for cart table</p>";
            }
            echo "</div>";
        }
        
        // Show sample products
        echo "<div class='test-section'>";
        echo "<h3>Sample Products</h3>";
        $stmt = $pdo->query("
            SELECT p.id, p.name, p.price, p.stock_quantity, c.name as category 
            FROM products p 
            JOIN categories c ON p.category_id = c.id 
            WHERE p.status = 'active' 
            LIMIT 5
        ");
        $products = $stmt->fetchAll();
        
        if ($products) {
            echo "<table border='1' cellpadding='5' cellspacing='0'>";
            echo "<tr><th>ID</th><th>Name</th><th>Category</th><th>Price</th><th>Stock</th></tr>";
            foreach ($products as $product) {
                echo "<tr>";
                echo "<td>" . $product['id'] . "</td>";
                echo "<td>" . htmlspecialchars($product['name']) . "</td>";
                echo "<td>" . htmlspecialchars($product['category']) . "</td>";
                echo "<td>$" . number_format($product['price'], 2) . "</td>";
                echo "<td>" . $product['stock_quantity'] . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p class='warning'>⚠️ No products found</p>";
        }
        echo "</div>";
        
    } else {
        echo "<p class='error'>❌ Database connection failed!</p>";
    }
    
} catch (Exception $e) {
    echo "<p class='error'>❌ Error: " . $e->getMessage() . "</p>";
}

echo "<div class='test-section'>";
echo "<h3>Quick Actions</h3>";
echo "<p><a href='index.php'>🏠 Home</a> | ";
echo "<a href='products.php'>🛍️ Products</a> | ";
echo "<a href='cart.php'>🛒 Cart</a> | ";
if (!isLoggedIn()) {
    echo "<a href='login.php'>🔐 Login</a> | <a href='register.php'>📝 Register</a>";
} else {
    echo "<a href='logout.php'>🚪 Logout</a>";
}
echo "</p>";
echo "</div>";
?>
