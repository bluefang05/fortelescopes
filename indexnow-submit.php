<?php
/**
 * IndexNow One-Time Submission Script for fortelescopes.com
 * 
 * INSTRUCTIONS:
 * 1. Generate a hex key (32 chars recommended): https://www.bing.com/webmaster/indexnow
 * 2. Create a file named [YOUR_KEY].txt in your website root containing ONLY the key
 * 3. Update $apiKey below with your key
 * 4. Upload this script to your server
 * 5. Run once via browser: https://fortelescopes.com/indexnow-submit.php
 * 6. DELETE THIS SCRIPT IMMEDIATELY AFTER USE for security
 */

// ================= CONFIGURATION =================
$apiKey = 'YOUR_32_CHAR_HEX_KEY_HERE'; // <-- CHANGE THIS
$sitemapUrl = 'https://fortelescopes.com/sitemap.xml';
$domain = 'https://fortelescopes.com';
$maxUrlsPerRequest = 10000; // IndexNow limit
// =================================================

// Security: Prevent direct access without explicit run confirmation
if (!isset($_GET['run']) || $_GET['run'] !== 'confirm') {
    header('Content-Type: text/html; charset=utf-8');
    echo "<!DOCTYPE html><html><head><title>IndexNow Setup</title>";
    echo "<style>body{font-family:Arial,sans-serif;max-width:800px;margin:40px auto;line-height:1.6;}";
    echo ".step{background:#f5f5f5;padding:20px;margin:20px 0;border-radius:8px;}";
    echo ".warning{background:#fff3cd;padding:15px;border-left:4px solid #ffc107;margin:20px 0;}";
    echo ".success{background:#d4edda;padding:15px;border-left:4px solid #28a745;margin:20px 0;}";
    echo "code{background:#e9ecef;padding:2px 6px;border-radius:3px;}";
    echo ".btn{display:inline-block;background:#007bff;color:white;padding:12px 24px;text-decoration:none;border-radius:5px;margin-top:20px;}";
    echo ".btn:hover{background:#0056b3;}</style></head><body>";
    echo "<h1>🚀 IndexNow One-Time Submission</h1>";
    
    echo "<div class='step'><h2>Step 1: Generate Your API Key</h2>";
    echo "<p>Generate a hexadecimal key (8-128 characters) at ";
    echo "<a href='https://www.bing.com/webmaster/indexnow' target='_blank'>Bing IndexNow</a></p>";
    echo "<p>Example key format: <code>" . bin2hex(random_bytes(16)) . "</code></p></div>";
    
    echo "<div class='step'><h2>Step 2: Create Verification File</h2>";
    echo "<p>Create a file named <code>[YOUR_KEY].txt</code> in your website root directory.</p>";
    echo "<p>The file must contain ONLY your key, nothing else.</p>";
    echo "<p>Example: If your key is <code>abc123...</code>, create <code>abc123....txt</code> at <code>https://fortelescopes.com/abc123....txt</code></p></div>";
    
    echo "<div class='step'><h2>Step 3: Update This Script</h2>";
    echo "<p>Edit this file and replace <code>YOUR_32_CHAR_HEX_KEY_HERE</code> with your actual key on line " . (__LINE__ - 18) . ".</p></div>";
    
    echo "<div class='warning'><strong>⚠️ Important:</strong> This script is designed for ONE-TIME use only. ";
    echo "After running successfully, DELETE this file immediately to prevent unauthorized submissions.</div>";
    
    echo "<div class='step'><h2>Step 4: Run Submission</h2>";
    echo "<p>Once you've completed steps 1-3, click the button below to submit all URLs from your sitemap:</p>";
    echo "<a href='?run=confirm' class='btn'>✅ Submit All URLs to IndexNow</a></div>";
    
    echo "</body></html>";
    exit;
}

// Verify API key format
if (!preg_match('/^[a-fA-F0-9]{8,128}$/', $apiKey)) {
    die("❌ Error: Invalid API key format. Must be 8-128 hexadecimal characters.");
}

// Verify key file exists
$keyFileUrl = $domain . '/' . $apiKey . '.txt';
$keyFileContent = @file_get_contents($keyFileUrl);
if ($keyFileContent === false || trim($keyFileContent) !== $apiKey) {
    die("❌ Error: Verification file not found or invalid.<br>");
    die("Expected URL: <code>$keyFileUrl</code><br>");
    die("The file must exist and contain ONLY your API key.");
}

echo "<!DOCTYPE html><html><head><title>IndexNow Submission</title>";
echo "<style>body{font-family:Arial,sans-serif;max-width:900px;margin:40px auto;line-height:1.6;}";
echo ".log{background:#f8f9fa;padding:20px;border-radius:8px;font-family:monospace;font-size:13px;white-space:pre-wrap;}";
echo ".success{color:#28a745;}.error{color:#dc3545;}.info{color:#007bff;}";
echo "h2{border-bottom:2px solid #007bff;padding-bottom:10px;}</style></head><body>";

echo "<h1>🚀 IndexNow Submission in Progress</h1>";
echo "<p>Submitting URLs from: <code>$sitemapUrl</code></p>";
echo "<p>Verification file: <code>$keyFileUrl</code> ✅</p>";
echo "<hr>";

// Fetch and parse sitemap
echo "<h2>📥 Fetching Sitemap...</h2>";
$sitemapContent = @file_get_contents($sitemapUrl);
if ($sitemapContent === false) {
    die("<p class='error'>❌ Failed to fetch sitemap. Please check the URL.</p>");
}

// Extract URLs using regex (handles standard sitemap format)
preg_match_all('/<loc>(.*?)<\/loc>/', $sitemapContent, $matches);
$urls = array_unique($matches[1]);

if (empty($urls)) {
    die("<p class='error'>❌ No URLs found in sitemap.</p>");
}

echo "<p class='success'>✅ Found " . count($urls) . " URLs in sitemap</p>";

// Split into batches if needed
$batches = array_chunk($urls, $maxUrlsPerRequest);
$totalBatches = count($batches);

echo "<h2>📤 Submitting to IndexNow...</h2>";
echo "<div class='log'>";

$results = [
    'success' => 0,
    'failed' => 0,
    'details' => []
];

foreach ($batches as $index => $batch) {
    $batchNum = $index + 1;
    echo "<span class='info'>Batch $batchNum of $totalBatches (" . count($batch) . " URLs)...</span>\n";
    flush();
    
    $postData = json_encode([
        'host' => parse_url($domain, PHP_URL_HOST),
        'key' => $apiKey,
        'keyLocation' => $keyFileUrl,
        'urlList' => $batch
    ]);
    
    $ch = curl_init('https://api.indexnow.org/IndexNow');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json; charset=utf-8',
        'Content-Length: ' . strlen($postData)
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        echo "<span class='error'>❌ cURL Error: $curlError</span>\n";
        $results['failed'] += count($batch);
        $results['details'][] = "Batch $batchNum: cURL Error - $curlError";
    } elseif ($httpCode == 200) {
        echo "<span class='success'>✅ Success (HTTP $httpCode)</span>\n";
        $results['success'] += count($batch);
        $results['details'][] = "Batch $batchNum: Submitted successfully";
    } elseif ($httpCode == 202) {
        echo "<span class='success'>✅ Accepted (HTTP $httpCode) - Validation pending</span>\n";
        $results['success'] += count($batch);
        $results['details'][] = "Batch $batchNum: Accepted for validation";
    } else {
        echo "<span class='error'>❌ Failed (HTTP $httpCode): $response</span>\n";
        $results['failed'] += count($batch);
        $results['details'][] = "Batch $batchNum: HTTP $httpCode - $response";
    }
    
    // Small delay between batches to avoid rate limiting
    if ($batchNum < $totalBatches) {
        sleep(1);
    }
}

echo "</div>";

// Summary
echo "<h2>📊 Submission Summary</h2>";
echo "<div class='log'>";
echo "<span class='success'>Total URLs Submitted Successfully: {$results['success']}</span>\n";
echo "<span class='error'>Total URLs Failed: {$results['failed']}</span>\n";
echo "\n<strong>Details:</strong>\n";
foreach ($results['details'] as $detail) {
    echo "- $detail\n";
}
echo "</div>";

if ($results['failed'] == 0) {
    echo "<div class='success' style='margin-top:20px;padding:20px;background:#d4edda;border-left:4px solid #28a745;'>";
    echo "<strong>✅ All URLs submitted successfully!</strong><br><br>";
    echo "<strong>⚠️ IMPORTANT:</strong> Delete this script file now to prevent unauthorized use.<br>";
    echo "You can verify submission status at ";
    echo "<a href='https://www.bing.com/webmasters' target='_blank'>Bing Webmaster Tools</a>";
    echo "</div>";
} else {
    echo "<div class='error' style='margin-top:20px;padding:20px;background:#f8d7da;border-left:4px solid #dc3545;'>";
    echo "<strong>⚠️ Some URLs failed to submit.</strong> Check the details above and try again if needed.<br>";
    echo "Common issues: Invalid key, verification file missing, or rate limiting.";
    echo "</div>";
}

echo "<hr><p><small>Script executed at: " . date('Y-m-d H:i:s T') . "</small></p>";
echo "</body></html>";
?>
