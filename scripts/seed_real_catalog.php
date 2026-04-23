<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

if (PHP_SAPI !== 'cli' && !defined('ENMA_ALLOW_WEB_RUN')) {
    http_response_code(403);
    echo "CLI only\n";
    exit(1);
}

function parse_cli_args(array $argv): array
{
    $args = [];
    foreach ($argv as $arg) {
        if (!is_string($arg) || !str_starts_with($arg, '--')) {
            continue;
        }
        $raw = substr($arg, 2);
        if ($raw === '') {
            continue;
        }
        $pair = explode('=', $raw, 2);
        $key = trim($pair[0]);
        if ($key === '') {
            continue;
        }
        $args[$key] = isset($pair[1]) ? (string) $pair[1] : '1';
    }
    return $args;
}

function extract_products_array_expression(string $input): string
{
    $raw = trim($input);
    if ($raw === '') {
        return '';
    }

    $raw = preg_replace('/^\s*```(?:php)?\s*/i', '', $raw) ?? $raw;
    $raw = preg_replace('/\s*```\s*$/', '', $raw) ?? $raw;
    $raw = trim(str_replace('<?php', '', $raw));

    if (preg_match('/\$products\s*=\s*(\[.*\]);/is', $raw, $m) === 1) {
        return trim((string) $m[1]);
    }

    if (preg_match('/(\[.*\])\s*$/is', $raw, $m) === 1) {
        return trim((string) $m[1]);
    }

    return '';
}

function parse_products_payload(string $input): array
{
    $expr = extract_products_array_expression($input);
    if ($expr === '') {
        throw new RuntimeException('Could not find a valid $products array in payload.');
    }

    $parsed = @eval('return ' . $expr . ';');
    if (!is_array($parsed)) {
        throw new RuntimeException('Payload did not evaluate to a PHP array.');
    }

    return $parsed;
}

/**
 * Paste Claude output here.
 *
 * Supported keys per item (Spanish/English aliases):
 * - asin
 * - nombre | name | title
 * - categoria | category | type
 * - descripcion | description | focus
 * - imagen | image | image_url | img
 * - url | affiliate_url
 */
$products = [
    ['asin' => 'B0007UQNNQ', 'title' => 'Celestron PowerSeeker 127EQ', 'type' => 'telescope', 'focus' => 'Beginner reflector telescope with equatorial mount.', 'url' => 'https://www.amazon.com/dp/B0007UQNNQ', 'img' => 'https://images.unsplash.com/photo-1451187580459-43490279c0fa?auto=format&fit=crop&w=1200&q=80'],
    ['asin' => 'B07C8ZQF9Q', 'title' => 'Gskyer 70mm Telescope', 'type' => 'telescope', 'focus' => 'Entry-level refractor often chosen as a first gift telescope.', 'url' => 'https://www.amazon.com/dp/B07C8ZQF9Q', 'img' => 'https://images.unsplash.com/photo-1462331940025-496dfbfc7564?auto=format&fit=crop&w=1200&q=80'],
    ['asin' => 'B000GUFOBO', 'title' => 'Celestron NexStar 130SLT', 'type' => 'telescope', 'focus' => 'Computerized goto telescope for beginners moving into intermediate observing.', 'url' => 'https://www.amazon.com/dp/B000GUFOBO', 'img' => 'https://images.unsplash.com/photo-1419242902214-272b3f66ee7a?auto=format&fit=crop&w=1200&q=80'],
    ['asin' => 'B001DDW9V6', 'title' => 'Orion SkyQuest XT6', 'type' => 'telescope', 'focus' => 'Classic Dobsonian value pick with strong aperture per dollar.', 'url' => 'https://www.amazon.com/dp/B001DDW9V6', 'img' => 'https://images.unsplash.com/photo-1502136969935-8d8eef54d77e?auto=format&fit=crop&w=1200&q=80'],
    ['asin' => 'B0007UQNV8', 'title' => 'Celestron 8-24mm Zoom Eyepiece', 'type' => 'accessory', 'focus' => 'Flexible zoom eyepiece for faster magnification changes during sessions.', 'url' => 'https://www.amazon.com/dp/B0007UQNV8', 'img' => 'https://images.unsplash.com/photo-1498049860654-af1a5c566876?auto=format&fit=crop&w=1200&q=80'],
    ['asin' => 'B01LZ6DDC2', 'title' => 'SVBONY Telescope Lens Kit', 'type' => 'accessory', 'focus' => 'Accessory lens kit for expanding viewing options on compatible scopes.', 'url' => 'https://www.amazon.com/dp/B01LZ6DDC2', 'img' => 'https://images.unsplash.com/photo-1531512073830-ba890ca4eba2?auto=format&fit=crop&w=1200&q=80'],
    ['asin' => 'B01K7M0JEM', 'title' => 'Telescope Phone Adapter', 'type' => 'accessory', 'focus' => 'Smartphone adapter to capture moon and planetary shots through the eyepiece.', 'url' => 'https://www.amazon.com/dp/B01K7M0JEM', 'img' => 'https://images.unsplash.com/photo-1545239351-1141bd82e8a6?auto=format&fit=crop&w=1200&q=80'],
    ['asin' => 'B0000635WI', 'title' => 'Celestron PowerTank', 'type' => 'accessory', 'focus' => 'Portable power source for longer observing nights in the field.', 'url' => 'https://www.amazon.com/dp/B0000635WI', 'img' => 'https://images.unsplash.com/photo-1581090464777-f3220bbe1b8b?auto=format&fit=crop&w=1200&q=80'],
    ['asin' => 'B000GUFOC8', 'title' => 'Celestron NexStar 8SE', 'type' => 'telescope', 'focus' => 'High-ticket Schmidt-Cassegrain option for serious progression.', 'url' => 'https://www.amazon.com/dp/B000GUFOC8', 'img' => 'https://images.unsplash.com/photo-1473929735477-7df8f0a8e2da?auto=format&fit=crop&w=1200&q=80'],
    ['asin' => 'B000MLL6R8', 'title' => 'Celestron AstroMaster 130EQ', 'type' => 'telescope', 'focus' => 'Popular bridge telescope between beginner and intermediate usage.', 'url' => 'https://www.amazon.com/dp/B000MLL6R8', 'img' => 'https://images.unsplash.com/photo-1500534314209-a25ddb2bd429?auto=format&fit=crop&w=1200&q=80'],
    ['asin' => 'B083JW8NWK', 'title' => 'Celestron StarSense Explorer DX 130AZ', 'type' => 'telescope', 'focus' => 'Smartphone-guided telescope designed to simplify object finding.', 'url' => 'https://www.amazon.com/dp/B083JW8NWK', 'img' => 'https://images.unsplash.com/photo-1469474968028-56623f02e42e?auto=format&fit=crop&w=1200&q=80'],
    ['asin' => 'B002828HJE', 'title' => 'Sky-Watcher Heritage 130P', 'type' => 'telescope', 'focus' => 'Portable tabletop Dobsonian with strong value for travel-friendly setups.', 'url' => 'https://www.amazon.com/dp/B002828HJE', 'img' => 'https://images.unsplash.com/photo-1517971071642-34a2d3ecc9cd?auto=format&fit=crop&w=1200&q=80'],
    ['asin' => 'B001UQ6E4K', 'title' => 'Orion StarBlast 4.5 Astro', 'type' => 'telescope', 'focus' => 'Compact reflector telescope well-suited to quick backyard sessions.', 'url' => 'https://www.amazon.com/dp/B001UQ6E4K', 'img' => 'https://images.unsplash.com/photo-1521295121783-8a321d551ad2?auto=format&fit=crop&w=1200&q=80'],
    ['asin' => 'B001TI9Y2M', 'title' => 'Celestron Travel Scope 70', 'type' => 'telescope', 'focus' => 'Portable travel-friendly telescope for first-time stargazing trips.', 'url' => 'https://www.amazon.com/dp/B001TI9Y2M', 'img' => 'https://images.unsplash.com/photo-1465101178521-c1a9136a3b99?auto=format&fit=crop&w=1200&q=80'],
    ['asin' => 'B07JWDFMZL', 'title' => 'SVBONY 6mm Eyepiece', 'type' => 'accessory', 'focus' => 'Short focal length eyepiece aimed at higher magnification viewing.', 'url' => 'https://www.amazon.com/dp/B07JWDFMZL', 'img' => 'https://images.unsplash.com/photo-1465101046530-73398c7f28ca?auto=format&fit=crop&w=1200&q=80'],
    ['asin' => 'B0048EZCF2', 'title' => 'Celestron X-Cel LX Eyepiece', 'type' => 'accessory', 'focus' => 'Mid-range eyepiece line known for comfort-oriented viewing.', 'url' => 'https://www.amazon.com/dp/B0048EZCF2', 'img' => 'https://images.unsplash.com/photo-1489515217757-5fd1be406fef?auto=format&fit=crop&w=1200&q=80'],
    ['asin' => 'B00D12P6Z2', 'title' => 'Red Dot Finder Scope', 'type' => 'accessory', 'focus' => 'Finder upgrade to improve target acquisition speed.', 'url' => 'https://www.amazon.com/dp/B00D12P6Z2', 'img' => 'https://images.unsplash.com/photo-1543722530-d2c3201371e7?auto=format&fit=crop&w=1200&q=80'],
    ['asin' => 'B00006RH5I', 'title' => 'Moon Filter', 'type' => 'accessory', 'focus' => 'Simple lunar observing filter to reduce glare and improve surface detail.', 'url' => 'https://www.amazon.com/dp/B00006RH5I', 'img' => 'https://images.unsplash.com/photo-1532960407998-9027f4534d10?auto=format&fit=crop&w=1200&q=80'],
    ['asin' => 'B07VZ7W5Z9', 'title' => 'Telescope Carrying Bag', 'type' => 'accessory', 'focus' => 'Transport and storage bag for protecting gear between sessions.', 'url' => 'https://www.amazon.com/dp/B07VZ7W5Z9', 'img' => 'https://images.unsplash.com/photo-1520175480921-4edfa2983e0f?auto=format&fit=crop&w=1200&q=80'],
];

$cliArgs = parse_cli_args($argv ?? []);
$payloadFile = trim((string) ($cliArgs['payload_file'] ?? ''));
if ($payloadFile !== '') {
    if (!is_file($payloadFile) || !is_readable($payloadFile)) {
        throw new RuntimeException('Payload file is missing or unreadable: ' . $payloadFile);
    }
    $payloadRaw = file_get_contents($payloadFile);
    if (!is_string($payloadRaw) || trim($payloadRaw) === '') {
        throw new RuntimeException('Payload file is empty: ' . $payloadFile);
    }
    $products = parse_products_payload($payloadRaw);
}

function normalize_catalog_category(string $raw): string
{
    $value = strtolower(trim($raw));
    if ($value === '') {
        return 'accessory';
    }

    if (
        str_contains($value, 'telescope')
        || str_contains($value, 'telescopio')
        || str_contains($value, 'scope')
    ) {
        return 'telescope';
    }

    if (str_contains($value, 'accessor')) {
        return 'accessory';
    }

    return in_array($value, ['telescopes', 'telescope'], true) ? 'telescope' : 'accessory';
}

function normalize_catalog_item(array $raw): ?array
{
    $asin = strtoupper(trim((string) ($raw['asin'] ?? '')));
    if (!preg_match('/^[A-Z0-9]{10}$/', $asin)) {
        return null;
    }

    $title = trim((string) ($raw['title'] ?? $raw['name'] ?? $raw['nombre'] ?? ''));
    if ($title === '') {
        return null;
    }

    $categoryRaw = (string) ($raw['type'] ?? $raw['category'] ?? $raw['categoria'] ?? '');
    $type = normalize_catalog_category($categoryRaw);

    $description = trim((string) ($raw['focus'] ?? $raw['description'] ?? $raw['descripcion'] ?? ''));
    if ($description === '') {
        $description = 'Curated product synced from master catalog update.';
    }

    $image = trim((string) ($raw['img'] ?? $raw['image'] ?? $raw['image_url'] ?? $raw['imagen'] ?? ''));

    $url = trim((string) ($raw['url'] ?? $raw['affiliate_url'] ?? ''));
    if ($url === '') {
        $url = 'https://www.amazon.com/dp/' . $asin;
    }
    $url = amazon_affiliate_url($url);

    return [
        'asin' => $asin,
        'title' => $title,
        'type' => $type,
        'focus' => mb_substr($description, 0, 500),
        'img' => $image,
        'url' => $url,
    ];
}

$normalizedProducts = [];
$seenAsins = [];
$skippedInvalid = 0;
$skippedDuplicate = 0;

foreach ($products as $row) {
    if (!is_array($row)) {
        $skippedInvalid++;
        continue;
    }

    $normalized = normalize_catalog_item($row);
    if ($normalized === null) {
        $skippedInvalid++;
        continue;
    }

    $asin = $normalized['asin'];
    if (isset($seenAsins[$asin])) {
        $skippedDuplicate++;
        continue;
    }

    $seenAsins[$asin] = true;
    $normalizedProducts[] = $normalized;
}

if ($normalizedProducts === []) {
    throw new RuntimeException('No valid products found in $products. Check ASIN/title fields.');
}

$knownAsins = array_map(static fn(array $p): string => $p['asin'], $normalizedProducts);
$placeholders = implode(',', array_fill(0, count($knownAsins), '?'));

$archive = $pdo->prepare("UPDATE products SET status='archived', updated_at=? WHERE asin NOT IN ($placeholders)");
$archive->execute(array_merge([gmdate('c')], $knownAsins));

if (DB_DRIVER === 'mysql') {
    $stmt = $pdo->prepare(
        'INSERT INTO products (
            asin, slug, title, description, category_slug, category_name,
            image_url, affiliate_url, status,
            last_synced_at, created_at, updated_at
        ) VALUES (
            :asin, :slug, :title, :description, :category_slug, :category_name,
            :image_url, :affiliate_url, :status,
            :last_synced_at, :created_at, :updated_at
        )
        ON DUPLICATE KEY UPDATE
            slug = VALUES(slug),
            title = VALUES(title),
            description = VALUES(description),
            category_slug = VALUES(category_slug),
            category_name = VALUES(category_name),
            image_url = VALUES(image_url),
            affiliate_url = VALUES(affiliate_url),
            status = VALUES(status),
            last_synced_at = VALUES(last_synced_at),
            updated_at = VALUES(updated_at)'
    );
} else {
    $stmt = $pdo->prepare(
        'INSERT INTO products (
            asin, slug, title, description, category_slug, category_name,
            image_url, affiliate_url, status,
            last_synced_at, created_at, updated_at
        ) VALUES (
            :asin, :slug, :title, :description, :category_slug, :category_name,
            :image_url, :affiliate_url, :status,
            :last_synced_at, :created_at, :updated_at
        )
        ON CONFLICT(asin) DO UPDATE SET
            slug = excluded.slug,
            title = excluded.title,
            description = excluded.description,
            category_slug = excluded.category_slug,
            category_name = excluded.category_name,
            image_url = excluded.image_url,
            affiliate_url = excluded.affiliate_url,
            status = excluded.status,
            last_synced_at = excluded.last_synced_at,
            updated_at = excluded.updated_at'
    );
}

$now = gmdate('c');
$count = 0;

foreach ($normalizedProducts as $p) {
    $isTelescope = $p['type'] === 'telescope';
    $categorySlug = $isTelescope ? 'telescopes' : 'accessories';
    $categoryName = $isTelescope ? 'Telescopes' : 'Accessories';

    $stmt->execute([
        ':asin' => $p['asin'],
        ':slug' => slugify($p['title'] . '-' . strtolower(substr($p['asin'], -4))),
        ':title' => $p['title'],
        ':description' => $p['focus'],
        ':category_slug' => $categorySlug,
        ':category_name' => $categoryName,
        ':image_url' => $p['img'],
        ':affiliate_url' => $p['url'],
        ':status' => 'published',
        ':last_synced_at' => $now,
        ':created_at' => $now,
        ':updated_at' => $now,
    ]);

    $count++;
}

echo "Catalog upsert completed. Products processed: $count" . PHP_EOL;
echo "Skipped invalid rows: $skippedInvalid" . PHP_EOL;
echo "Skipped duplicate ASIN rows: $skippedDuplicate" . PHP_EOL;
