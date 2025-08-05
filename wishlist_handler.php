<?php
require_once 'db.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Please login first']);
    exit();
}

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

switch ($action) {
    case 'toggle':
        $product_id = (int)$_POST['product_id'];
        
        // Check if product exists
        $stmt = $pdo->prepare("SELECT id FROM products WHERE id = ? AND status = 'active'");
        $stmt->execute([$product_id]);
        
        if (!$stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Product not found']);
            exit();
        }
        
        // Check if already in wishlist
        $stmt = $pdo->prepare("SELECT id FROM wishlist WHERE user_id = ? AND product_id = ?");
        $stmt->execute([$user_id, $product_id]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            // Remove from wishlist
            $stmt = $pdo->prepare("DELETE FROM wishlist WHERE id = ?");
            $stmt->execute([$existing['id']]);
            echo json_encode(['success' => true, 'added' => false, 'message' => 'Removed from wishlist']);
        } else {
            // Add to wishlist
            $stmt = $pdo->prepare("INSERT INTO wishlist (user_id, product_id) VALUES (?, ?)");
            $stmt->execute([$user_id, $product_id]);
            echo json_encode(['success' => true, 'added' => true, 'message' => 'Added to wishlist']);
        }
        break;
        
    case 'list':
        // Get user's wishlist
        $stmt = $pdo->prepare("
            SELECT w.*, p.name, p.price, p.discount_price, p.main_image, 
                   c.name as category_name, u.full_name as seller_name
            FROM wishlist w
            JOIN products p ON w.product_id = p.id
            JOIN categories c ON p.category_id = c.id
            JOIN users u ON p.seller_id = u.id
            WHERE w.user_id = ?
            ORDER BY w.added_at DESC
        ");
        $stmt->execute([$user_id]);
        $wishlist = $stmt->fetchAll();
        
        echo json_encode(['success' => true, 'wishlist' => $wishlist]);
        break;
        
    case 'remove':
        $wishlist_id = (int)$_POST['wishlist_id'];
        
        // Verify wishlist item belongs to user
        $stmt = $pdo->prepare("SELECT id FROM wishlist WHERE id = ? AND user_id = ?");
        $stmt->execute([$wishlist_id, $user_id]);
        
        if (!$stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Wishlist item not found']);
            exit();
        }
        
        $stmt = $pdo->prepare("DELETE FROM wishlist WHERE id = ?");
        $stmt->execute([$wishlist_id]);
        
        echo json_encode(['success' => true, 'message' => 'Item removed from wishlist']);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
?>
