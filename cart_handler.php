<?php
require_once 'db.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Please login first']);
    exit();
}

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    // Verify user exists in database
    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    if (!$stmt->fetch()) {
        // User doesn't exist, clear session
        session_destroy();
        echo json_encode(['success' => false, 'message' => 'User session invalid. Please login again.']);
        exit();
    }

    switch ($action) {
        case 'add':
            $product_id = (int)($_POST['product_id'] ?? 0);
            $quantity = (int)($_POST['quantity'] ?? 1);
            
            if ($product_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
                exit();
            }
            
            if ($quantity <= 0) {
                $quantity = 1;
            }
            
            // Check if product exists and is available
            $stmt = $pdo->prepare("SELECT id, name, stock_quantity, status FROM products WHERE id = ? AND status = 'active'");
            $stmt->execute([$product_id]);
            $product = $stmt->fetch();
            
            if (!$product) {
                echo json_encode(['success' => false, 'message' => 'Product not found or unavailable']);
                exit();
            }
            
            if ($product['stock_quantity'] < $quantity) {
                echo json_encode(['success' => false, 'message' => 'Not enough stock available. Only ' . $product['stock_quantity'] . ' items left.']);
                exit();
            }
            
            // Check if item already in cart
            $stmt = $pdo->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ?");
            $stmt->execute([$user_id, $product_id]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                // Update quantity
                $new_quantity = $existing['quantity'] + $quantity;
                if ($new_quantity > $product['stock_quantity']) {
                    echo json_encode(['success' => false, 'message' => 'Cannot add more items. Total would exceed available stock (' . $product['stock_quantity'] . ' available).']);
                    exit();
                }
                
                $stmt = $pdo->prepare("UPDATE cart SET quantity = ?, added_at = CURRENT_TIMESTAMP WHERE id = ?");
                $result = $stmt->execute([$new_quantity, $existing['id']]);
                
                if ($result) {
                    echo json_encode(['success' => true, 'message' => 'Cart updated! Quantity: ' . $new_quantity]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to update cart']);
                }
            } else {
                // Add new item
                $stmt = $pdo->prepare("INSERT INTO cart (user_id, product_id, quantity, added_at) VALUES (?, ?, ?, CURRENT_TIMESTAMP)");
                $result = $stmt->execute([$user_id, $product_id, $quantity]);
                
                if ($result) {
                    echo json_encode(['success' => true, 'message' => 'Product added to cart successfully!']);
                } else {
                    $errorInfo = $stmt->errorInfo();
                    echo json_encode(['success' => false, 'message' => 'Failed to add product to cart: ' . $errorInfo[2]]);
                }
            }
            break;
            
        case 'update':
            $cart_id = (int)($_POST['cart_id'] ?? 0);
            $quantity = (int)($_POST['quantity'] ?? 1);
            
            if ($cart_id <= 0 || $quantity < 1) {
                echo json_encode(['success' => false, 'message' => 'Invalid cart ID or quantity']);
                exit();
            }
            
            // Verify cart item belongs to user and get product info
            $stmt = $pdo->prepare("
                SELECT c.id, c.quantity, p.stock_quantity, p.name 
                FROM cart c 
                JOIN products p ON c.product_id = p.id 
                WHERE c.id = ? AND c.user_id = ?
            ");
            $stmt->execute([$cart_id, $user_id]);
            $cart_item = $stmt->fetch();
            
            if (!$cart_item) {
                echo json_encode(['success' => false, 'message' => 'Cart item not found']);
                exit();
            }
            
            if ($quantity > $cart_item['stock_quantity']) {
                echo json_encode(['success' => false, 'message' => 'Not enough stock available. Only ' . $cart_item['stock_quantity'] . ' items available.']);
                exit();
            }
            
            $stmt = $pdo->prepare("UPDATE cart SET quantity = ? WHERE id = ?");
            $result = $stmt->execute([$quantity, $cart_id]);
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Cart updated successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to update cart']);
            }
            break;
            
        case 'remove':
            $cart_id = (int)($_POST['cart_id'] ?? 0);
            
            if ($cart_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid cart ID']);
                exit();
            }
            
            // Verify cart item belongs to user
            $stmt = $pdo->prepare("SELECT id FROM cart WHERE id = ? AND user_id = ?");
            $stmt->execute([$cart_id, $user_id]);
            
            if (!$stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Cart item not found']);
                exit();
            }
            
            $stmt = $pdo->prepare("DELETE FROM cart WHERE id = ?");
            $result = $stmt->execute([$cart_id]);
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Item removed from cart']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to remove item']);
            }
            break;
            
        case 'count':
            $stmt = $pdo->prepare("SELECT SUM(quantity) as count FROM cart WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $result = $stmt->fetch();
            
            echo json_encode(['success' => true, 'count' => (int)($result['count'] ?? 0)]);
            break;
            
        case 'clear':
            $stmt = $pdo->prepare("DELETE FROM cart WHERE user_id = ?");
            $result = $stmt->execute([$user_id]);
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Cart cleared successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to clear cart']);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action: ' . $action]);
    }
    
} catch (PDOException $e) {
    // Log the full error for debugging
    error_log("Cart Handler PDO Error: " . $e->getMessage());
    
    // Check for specific constraint violations
    if ($e->getCode() == 23000) {
        if (strpos($e->getMessage(), 'cart_user_fk') !== false) {
            // User foreign key violation
            session_destroy();
            echo json_encode(['success' => false, 'message' => 'User session invalid. Please login again.']);
        } elseif (strpos($e->getMessage(), 'cart_product_fk') !== false) {
            // Product foreign key violation
            echo json_encode(['success' => false, 'message' => 'Product no longer available.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database constraint error. Please try again.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error occurred. Please try again.']);
    }
} catch (Exception $e) {
    error_log("Cart Handler General Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An unexpected error occurred. Please try again.']);
}
?>
