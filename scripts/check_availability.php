<?php
/**
 * Batch Product Availability Checker (No API)
 * 
 * Checks Amazon product pages in batches to verify availability.
 * Runs via command line: php check_availability.php [batch_size] [delay_seconds]
 * 
 * Usage:
 *   php check_availability.php 10 5    # Check 10 products with 5s delay
 *   php check_availability.php         # Default: 20 products, 3s delay
 */

declare(strict_types=1);

// Configuration
$defaultBatchSize = 20;
$defaultDelay = 3; // seconds between requests
$maxBatchSize = 50; // Safety limit

// Get parameters from command line
$batchSize = (int)($argv[1] ?? $defaultBatchSize);
$delay = (int)($argv[2] ?? $defaultDelay);

// Validate parameters
if ($batchSize < 1 || $batchSize > $maxBatchSize) {
    echo "Error: Batch size must be between 1 and {$maxBatchSize}\n";
    exit(1);
}

if ($delay < 2) {
    echo "Warning: Delay less than 2 seconds may cause IP blocking. Setting to 3s.\n";
    $delay = 3;
}

echo "=== Product Availability Checker ===\n";
echo "Batch size: {$batchSize} products\n";
echo "Delay between requests: {$delay} seconds\n\n";

// Load database configuration
require_once __DIR__ . '/../database.php';

try {
    // Fetch products that need checking
    // Priority: oldest last_synced_at first, then by created_at
    $stmt = $pdo->prepare("
        SELECT id, asin, title, affiliate_url, status, last_synced_at
        FROM products
        WHERE status != 'discontinued'
        ORDER BY 
            CASE WHEN last_synced_at IS NULL THEN 0 ELSE 1 END,
            last_synced_at ASC,
            created_at ASC
        LIMIT :limit
    ");
    $stmt->bindValue(':limit', $batchSize, PDO::PARAM_INT);
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($products)) {
        echo "No products found to check.\n";
        exit(0);
    }

    echo "Found " . count($products) . " products to check.\n\n";

    $checked = 0;
    $available = 0;
    $unavailable = 0;
    $errors = 0;

    foreach ($products as $product) {
        $checked++;
        echo "[{$checked}/" . count($products) . "] Checking: {$product['title']}\n";
        echo "  ASIN: {$product['asin']}\n";

        // Extract Amazon URL or construct from ASIN
        $amazonUrl = extractAmazonUrl($product['affiliate_url'], $product['asin']);
        
        if (!$amazonUrl) {
            echo "  ⚠️  Skipping: Invalid URL\n";
            $errors++;
            sleep($delay);
            continue;
        }

        // Check availability
        $result = checkAvailability($amazonUrl);
        
        if ($result['error']) {
            echo "  ❌ Error: {$result['error']}\n";
            $errors++;
        } elseif ($result['available']) {
            echo "  ✅ Available\n";
            $available++;
            
            // Update database: mark as available, update sync time
            updateProductStatus($pdo, $product['id'], 'published', $result['price']);
        } else {
            echo "  🚫 OUT OF STOCK / UNAVAILABLE\n";
            $unavailable++;
            
            // Update database: mark as out of stock
            updateProductStatus($pdo, $product['id'], 'out_of_stock', null);
        }

        // Delay before next request (CRITICAL to avoid blocking)
        if ($checked < count($products)) {
            echo "  Waiting {$delay} seconds...\n\n";
            sleep($delay);
        }
    }

    // Summary
    echo "\n=== Summary ===\n";
    echo "Checked: {$checked}\n";
    echo "Available: {$available}\n";
    echo "Unavailable: {$unavailable}\n";
    echo "Errors: {$errors}\n";

} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
    exit(1);
}

/**
 * Extract clean Amazon URL from affiliate link or construct from ASIN
 */
function extractAmazonUrl(string $affiliateUrl, string $asin): ?string {
    // Try to extract the base Amazon URL
    if (preg_match('/https?:\/\/(?:www\.)?amazon\.com\/([^\s\'"]+)/', $affiliateUrl, $matches)) {
        return 'https://www.amazon.com/' . $matches[1];
    }
    
    // Fallback: construct standard product URL from ASIN
    if (!empty($asin)) {
        return "https://www.amazon.com/dp/{$asin}";
    }
    
    return null;
}

/**
 * Check product availability by fetching and parsing Amazon page
 */
function checkAvailability(string $url): array {
    $ch = curl_init();
    
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        CURLOPT_HTTPHEADER => [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.5',
            'Connection: keep-alive',
            'Upgrade-Insecure-Requests: 1',
        ],
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    
    $html = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    
    curl_close($ch);
    
    if ($error) {
        return ['available' => false, 'error' => "cURL error: {$error}", 'price' => null];
    }
    
    if ($httpCode === 404) {
        return ['available' => false, 'error' => null, 'price' => null, 'reason' => '404_not_found'];
    }
    
    if ($httpCode !== 200) {
        return ['available' => false, 'error' => "HTTP {$httpCode}", 'price' => null];
    }
    
    // Parse HTML for availability indicators
    $isAvailable = true;
    $reason = '';
    $price = null;
    
    // Check for common "out of stock" indicators
    $outOfStockPatterns = [
        '/Currently unavailable/i',
        '/Out of Stock/i',
        '/Temporarily out of stock/i',
        '/This item is no longer available/i',
        '/We don\'t know when.*available again/i',
        '/Currently we do not have pricing/i',
    ];
    
    foreach ($outOfStockPatterns as $pattern) {
        if (preg_match($pattern, $html)) {
            $isAvailable = false;
            $reason = 'out_of_stock';
            break;
        }
    }
    
    // Also check for "Add to Cart" or "Buy Now" buttons (indicates available)
    if (!$reason) {
        if (!preg_match('/Add to Cart|Buy Now|add-to-cart-button/i', $html)) {
            // No purchase buttons found - might be unavailable
            // But be conservative - only mark unavailable if we also see other signs
            if (preg_match('/unavailable|not available/i', $html)) {
                $isAvailable = false;
                $reason = 'no_purchase_option';
            }
        }
    }
    
    // Try to extract price if available
    if ($isAvailable) {
        if (preg_match('/\$([0-9]{1,3}(?:,[0-9]{3})*(?:\.[0-9]{2})?)/', $html, $priceMatches)) {
            $price = str_replace(',', '', $priceMatches[1]);
        }
    }
    
    return [
        'available' => $isAvailable,
        'error' => null,
        'price' => $price,
        'reason' => $reason
    ];
}

/**
 * Update product status in database
 */
function updateProductStatus(PDO $pdo, int $productId, string $status, ?string $price): void {
    $now = date('Y-m-d H:i:s');
    
    if ($price !== null) {
        $stmt = $pdo->prepare("
            UPDATE products 
            SET status = :status, 
                price_amount = :price,
                last_synced_at = :now
            WHERE id = :id
        ");
        $stmt->execute([
            ':status' => $status,
            ':price' => $price,
            ':now' => $now,
            ':id' => $productId
        ]);
    } else {
        $stmt = $pdo->prepare("
            UPDATE products 
            SET status = :status, 
                last_synced_at = :now
            WHERE id = :id
        ");
        $stmt->execute([
            ':status' => $status,
            ':now' => $now,
            ':id' => $productId
        ]);
    }
}
