<?php
// Redirect to products.php with search query
$search_query = isset($_GET['q']) ? $_GET['q'] : '';
if (!empty($search_query)) {
    header("Location: products.php?q=" . urlencode($search_query));
} else {
    header("Location: products.php");
}
exit();
?>
