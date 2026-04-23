<?php
/**
 * Amazon Associates Site - Database Maintenance Tool
 * 
 * This script adds necessary columns for availability tracking if they don't exist.
 * Run this once via browser or CLI to prepare your database.
 * 
 * USAGE:
 *   Browser: http://yoursite.com/admin/maintenance.php
 *   CLI: php maintenance.php
 * 
 * SECURITY: Change the ADMIN_PASSWORD below before using!
 */

// ================= CONFIGURATION =================
define('DB_HOST', 'localhost');
define('DB_NAME', 'your_database_name');     // CHANGE THIS
define('DB_USER', 'your_database_user');     // CHANGE THIS
define('DB_PASS', 'your_database_password'); // CHANGE THIS
define('PRODUCTS_TABLE', 'products');         // Your products table name
define('ADMIN_PASSWORD', 'CHANGE_THIS_PASSWORD_123'); // CHANGE THIS!

// ================= NO EDITING BELOW =================

// Simple authentication for web access
if (php_sapi_name() !== 'cli') {
    session_start();
    
    // Handle login form submission
    if (isset($_POST['password'])) {
        if ($_POST['password'] === ADMIN_PASSWORD) {
            $_SESSION['authenticated'] = true;
        } else {
            $error = "Invalid password";
        }
    }
    
    // Check authentication
    if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>Maintenance - Login</title>
            <style>
                body { font-family: Arial, sans-serif; max-width: 400px; margin: 50px auto; padding: 20px; }
                input[type="password"], input[type="submit"] { width: 100%; padding: 10px; margin: 10px 0; }
                .error { color: red; }
            </style>
        </head>
        <body>
            <h2>Database Maintenance</h2>
            <?php if (isset($error)) echo "<p class='error'>$error</p>"; ?>
            <form method="post">
                <input type="password" name="password" placeholder="Enter admin password" required>
                <input type="submit" value="Login">
            </form>
            <p><small>This tool adds availability tracking columns to your products table.</small></p>
        </body>
        </html>
        <?php
        exit;
    }
}

// Database connection
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "✓ Connected to database successfully.\n\n";
} catch (PDOException $e) {
    die("✗ Database connection failed: " . $e->getMessage() . "\n");
}

// Columns to add
$columns = [
    [
        'name' => 'is_available',
        'type' => 'TINYINT(1)',
        'default' => '1',
        'description' => 'Product availability status (1=available, 0=out of stock)'
    ],
    [
        'name' => 'last_checked_at',
        'type' => 'DATETIME',
        'default' => 'NULL',
        'description' => 'Timestamp of last availability check'
    ],
    [
        'name' => 'check_priority',
        'type' => 'TINYINT UNSIGNED',
        'default' => '5',
        'description' => 'Check priority (1=highest, 10=lowest)'
    ]
];

echo "Checking table: " . PRODUCTS_TABLE . "\n";
echo str_repeat("-", 60) . "\n";

$changesMade = false;

foreach ($columns as $column) {
    $colName = $column['name'];
    
    // Check if column exists
    $stmt = $pdo->prepare("SHOW COLUMNS FROM " . PRODUCTS_TABLE . " LIKE ?");
    $stmt->execute([$colName]);
    
    if ($stmt->rowCount() === 0) {
        // Column doesn't exist, add it
        $alterSQL = "ALTER TABLE " . PRODUCTS_TABLE . " 
                     ADD COLUMN {$colName} {$column['type']} 
                     DEFAULT {$column['default']}";
        
        try {
            $pdo->exec($alterSQL);
            echo "✓ Added column: {$colName} ({$column['type']})\n";
            echo "  Description: {$column['description']}\n";
            $changesMade = true;
        } catch (PDOException $e) {
            echo "✗ Failed to add {$colName}: " . $e->getMessage() . "\n";
        }
    } else {
        echo "✓ Column already exists: {$colName}\n";
    }
}

echo str_repeat("-", 60) . "\n";

if ($changesMade) {
    echo "\n✓ Database structure updated successfully!\n";
    echo "\nNext steps:\n";
    echo "1. Review the new columns in your database\n";
    echo "2. Set up the availability checker script\n";
    echo "3. Configure cron job for automated checks\n";
} else {
    echo "\n✓ All required columns already exist. No changes needed.\n";
}

// Show current table structure
echo "\nCurrent table structure:\n";
echo str_repeat("-", 60) . "\n";
$stmt = $pdo->query("SHOW COLUMNS FROM " . PRODUCTS_TABLE);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    printf("%-25s %-20s %-10s %s\n", 
           $row['Field'], 
           $row['Type'], 
           $row['Null'], 
           $row['Default'] ?? 'NULL');
}

echo "\n✓ Maintenance complete.\n";

if (php_sapi_name() === 'cli') {
    exit(0);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Maintenance Complete</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; line-height: 1.6; }
        pre { background: #f4f4f4; padding: 15px; overflow-x: auto; border-radius: 5px; }
        .success { color: green; }
        .info { background: #e7f3ff; padding: 15px; border-left: 4px solid #2196F3; margin: 20px 0; }
        code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; }
    </style>
</head>
<body>
    <h1 class="success">✓ Maintenance Complete</h1>
    
    <div class="info">
        <h3>What was done:</h3>
        <ul>
            <li>Added <code>is_available</code> column for stock status</li>
            <li>Added <code>last_checked_at</code> column for tracking check times</li>
            <li>Added <code>check_priority</code> column for prioritizing important products</li>
        </ul>
    </div>
    
    <h3>Next Steps:</h3>
    <ol>
        <li>Create the availability checker script (<code>check_availability.php</code>)</li>
        <li>Test it manually with a few products</li>
        <li>Set up a cron job to run every minute:
            <pre>*/1 * * * * php /path/to/check_availability.php</pre>
        </li>
        <li>Monitor logs to ensure everything works smoothly</li>
    </ol>
    
    <p><a href="maintenance.php">↻ Refresh</a> | <a href="index.php">← Back to Site</a></p>
</body>
</html>
