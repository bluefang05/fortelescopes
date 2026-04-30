<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

function fetch_url_content(string $url): string
{
    $url = trim($url);
    if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
        return '';
    }

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        if ($ch === false) {
            return '';
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language: en-US,en;q=0.9',
            ],
        ]);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($status >= 200 && $status < 400 && is_string($body)) {
            return $body;
        }
        return '';
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 12,
            'header' => "User-Agent: Mozilla/5.0\r\nAccept-Language: en-US,en;q=0.9\r\n",
        ],
    ]);

    $body = @file_get_contents($url, false, $context);
    return is_string($body) ? $body : '';
}

function is_placeholder_like_image_url(string $url): bool
{
    $value = strtolower(trim($url));
    if ($value === '') {
        return true;
    }

    return strpos($value, '/assets/img/product-placeholder.svg') !== false
        || strpos($value, '\\assets\\img\\product-placeholder.svg') !== false
        || strpos($value, 'image-uploading') !== false
        || strpos($value, '/assets/logo/') !== false
        || strpos($value, '\\assets\\logo\\') !== false;
}

function extract_amazon_image_url(string $html): string
{
    if ($html === '') {
        return '';
    }

    $patterns = [
        '/<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\']/i',
        '/data-a-dynamic-image=["\']([^"\']+)["\']/i',
        '/"landingImageUrl"\s*:\s*"([^"]+)"/i',
        '/"mainUrl"\s*:\s*"([^"]+)"/i',
        '/"hiRes"\s*:\s*"([^"]+)"/i',
        '/"large"\s*:\s*"([^"]+)"/i',
        '/(https?:\\\\\/\\\\\/m\.media-amazon\.com\\\\\/images\\\\\/I\\\\\/[^"\\\\]+?\.(?:jpg|jpeg|png|webp))/i',
        '/(https?:\/\/m\.media-amazon\.com\/images\/I\/[^"\']+?\.(?:jpg|jpeg|png|webp))/i',
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $html, $m)) {
            $candidate = html_entity_decode((string) $m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $candidate = str_replace('\\/', '/', $candidate);

            if ($pattern === '/data-a-dynamic-image=["\']([^"\']+)["\']/i') {
                $decoded = json_decode($candidate, true);
                if (is_array($decoded) && $decoded !== []) {
                    $keys = array_keys($decoded);
                    usort($keys, static function (string $a, string $b): int {
                        return strlen($b) <=> strlen($a);
                    });
                    foreach ($keys as $key) {
                        $imgUrl = trim((string) $key);
                        if (filter_var($imgUrl, FILTER_VALIDATE_URL) && stripos($imgUrl, 'amazon') !== false) {
                            return $imgUrl;
                        }
                    }
                }
            }

            if (filter_var($candidate, FILTER_VALIDATE_URL) && stripos($candidate, 'amazon') !== false) {
                return $candidate;
            }
        }
    }

    return '';
}

function amazon_product_url_by_asin(string $asin): string
{
    $asin = normalize_asin($asin);
    if ($asin === '') {
        return '';
    }
    return 'https://www.amazon.com/dp/' . $asin;
}

$stmt = $pdo->query('SELECT id, asin, affiliate_url, image_url FROM products');
$rows = $stmt->fetchAll();

$checked = 0;
$updated = 0;
$skippedNoAsin = 0;
$scraped = 0;
$fallbackToPlaceholder = 0;
$failedFetch = 0;
$fetchedFromAsinUrl = 0;
$fetchedFromAffiliateUrl = 0;

$updateStmt = $pdo->prepare(
    'UPDATE products
     SET image_url = :image_url, updated_at = :updated_at
     WHERE id = :id'
);

foreach ($rows as $row) {
    $checked++;
    $id = (int) ($row['id'] ?? 0);
    $asin = normalize_asin((string) ($row['asin'] ?? ''));
    $affiliateUrl = trim((string) ($row['affiliate_url'] ?? ''));
    $current = trim((string) ($row['image_url'] ?? ''));

    if ($id <= 0) {
        continue;
    }
    if ($asin === '') {
        $skippedNoAsin++;
        continue;
    }

    $needsFix = $current === ''
        || is_placeholder_like_image_url($current)
        || stripos($current, 'URL_') !== false
        || !is_usable_product_image_url($current);

    if (!$needsFix) {
        continue;
    }

    $sourceUrl = amazon_product_url_by_asin($asin);
    $html = fetch_url_content($sourceUrl);
    if ($html !== '') {
        $fetchedFromAsinUrl++;
    }

    if ($html === '' && $affiliateUrl !== '' && stripos($affiliateUrl, '/dp/') !== false) {
        $sourceUrl = $affiliateUrl;
        $html = fetch_url_content($sourceUrl);
        if ($html !== '') {
            $fetchedFromAffiliateUrl++;
        }
    }

    $newUrl = extract_amazon_image_url($html);

    if ($newUrl !== '') {
        $scraped++;
    } else {
        if ($html === '') {
            $failedFetch++;
        }
        $newUrl = product_image_fallback_url();
        $fallbackToPlaceholder++;
    }

    if ($newUrl === '') {
        continue;
    }

    if ($newUrl === $current) {
        continue;
    }

    $updateStmt->execute([
        ':image_url' => $newUrl,
        ':updated_at' => now_iso(),
        ':id' => $id,
    ]);
    $updated++;
}

$lines = [
    'Checked: ' . $checked,
    'Updated: ' . $updated,
    'Skipped (invalid/missing ASIN): ' . $skippedNoAsin,
    'Scraped real images: ' . $scraped,
    'Fallback placeholder: ' . $fallbackToPlaceholder,
    'Failed fetches: ' . $failedFetch,
    'Fetched from ASIN URLs: ' . $fetchedFromAsinUrl,
    'Fetched from affiliate URLs: ' . $fetchedFromAffiliateUrl,
];
maintenance_prune_files('logs', 'fix-product-images_*.log', 30);
$logPath = maintenance_append_log('fix-product-images', $lines);
$lines[] = 'Log: ' . $logPath;
echo implode(PHP_EOL, $lines) . PHP_EOL;
