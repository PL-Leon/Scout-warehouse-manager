<?php
session_start();

// Check if user is logged in
if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Include database connection
require_once 'config/database.php';

// Check if item ID is provided
if(!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$item_id = $_GET['id'];
$user_id = $_SESSION['user_id'];
$is_admin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] ? true : false;

// Get item details
$stmt = $pdo->prepare("
    SELECT i.*, w.name AS warehouse_name, w.id AS warehouse_id
    FROM items i 
    JOIN warehouses w ON i.warehouse_id = w.id
    WHERE i.id = ?
");
$stmt->execute([$item_id]);
$item = $stmt->fetch();

// If item doesn't exist, redirect
if(!$item) {
    header("Location: index.php");
    exit;
}

// Initialize variables
$error = '';
$success = '';

// Process form submission
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $quantity = (int)$_POST['quantity'];
    
    // Validate input
    if(empty($name)) {
        $error = "Item name is required.";
    } elseif($quantity <= 0) {
        $error = "Quantity must be greater than 0.";
    } else {
        // Check if item is borrowed
        if($item['is_borrowed']) {
            // If item is borrowed, only update name and description
            $stmt = $pdo->prepare("
                UPDATE items 
                SET name = ?, description = ? 
                WHERE id = ?
            ");
            $params = [$name, $description, $item_id];
        } else {
            // If item is not borrowed, update all fields
            $stmt = $pdo->prepare("
                UPDATE items 
                SET name = ?, description = ?, quantity = ? 
                WHERE id = ?
            ");
            $params = [$name, $description, $quantity, $item_id];
        }
        
        try {
            $stmt->execute($params);
            
            // Redirect back to warehouse
            header("Location: warehouse.php?id=" . $item['warehouse_id'] . "&success=updated");
            exit;
        } catch(PDOException $e) {
            $error = "There was error when updating item: " . $e->getMessage();
        }
    }
}

// Get all warehouses for the dropdown
$stmt = $pdo->query("SELECT id, name FROM warehouses ORDER BY name");
$warehouses = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit item - Scout Warehouse Management</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <?php include 'includes/header.php'; ?>
        
        <div class="content">
            <div class="page-header">
                <h1>Edit item</h1>
                <a href="warehouse.php?id=<?php echo $item['warehouse_id']; ?>" class="btn btn-secondary">Back to warehouse</a>
            </div>
            
            <?php if($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <div class="form-container">
                <form method="POST" action="edit_item.php?id=<?php echo $item_id; ?>">
                    <div class="form-group">
                        <label for="name">Name:</label>
                        <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($item['name']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Description:</label>
                        <textarea id="description" name="description" rows="4"><?php echo htmlspecialchars($item['description']); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="quantity">Množství:</label>
                        <input type="number" id="quantity" name="quantity" value="<?php echo $item['quantity']; ?>" min="1" <?php echo $item['is_borrowed'] ? 'disabled' : ''; ?>>
                        <?php if($item['is_borrowed']): ?>
                            <small>The quantity cannot be changed because the item is borrowed.</small>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label for="warehouse">Warehouse:</label>
                        <select id="warehouse" disabled>
                            <?php foreach($warehouses as $warehouse): ?>
                                <option value="<?php echo $warehouse['id']; ?>" <?php echo $warehouse['id'] == $item['warehouse_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($warehouse['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small>To move an item to another warehouse, contact the administrator.</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Status:</label>
                        <div>
                            <?php if($item['is_borrowed']): ?>
                                <span class="status-borrowed">Borrowed</span>
                                <p class="borrow-info">
                                    <?php 
                                    // Get borrower name
                                    $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
                                    $stmt->execute([$item['borrowed_by']]);
                                    $borrower = $stmt->fetch();
                                    
                                    echo "Borrowed by user: " . htmlspecialchars($borrower['name']);
                                    echo "<br>Date of borrowing: " . date('d.m.Y H:i', strtotime($item['borrowed_at']));
                                    ?>
                                </p>
                            <?php else: ?>
                                <span class="status-available">Dostupné</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">Save changes</button>
                        <a href="warehouse.php?id=<?php echo $item['warehouse_id']; ?>" class="btn btn-secondary">Zrušit</a>
                    </div>
                </form>
                
                <?php if(!$item['is_borrowed'] && ($is_admin || $_SESSION['user_id'] == $item['created_by'])): ?>
                <div class="danger-zone">
                    <h3>Danger zone</h3>
                    <p>This action is irreversible. Be careful.</p>
                    <a href="delete_item.php?id=<?php echo $item_id; ?>" class="btn btn-danger" onclick="return confirm('Do you really want to delete this item?')">Smazat položku</a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="js/script.js"></script>
</body>
</html>