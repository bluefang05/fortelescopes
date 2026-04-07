<?php

require_once __DIR__ . '/../includes/bootstrap.php';

if (PHP_SAPI !== 'cli' && !defined('ENMA_ALLOW_WEB_RUN')) {
    http_response_code(403);
    echo "CLI only\n";
    exit(1);
}

$items = [
    ['asin' => 'B0TSCP004', 'title' => 'Red Dot Finder Scope', 'category' => 'Finders', 'img' => 'https://images.unsplash.com/photo-1462331940025-496dfbfc7564?auto=format&fit=crop&w=1200&q=80'],
    ['asin' => 'B0TSCP005', 'title' => 'Plossl Eyepiece 10mm', 'category' => 'Eyepieces', 'img' => 'https://images.unsplash.com/photo-1451187580459-43490279c0fa?auto=format&fit=crop&w=1200&q=80'],
    ['asin' => 'B0TSCP006', 'title' => 'Plossl Eyepiece 25mm', 'category' => 'Eyepieces', 'img' => 'https://images.unsplash.com/photo-1465101162946-4377e57745c3?auto=format&fit=crop&w=1200&q=80'],
    ['asin' => 'B0TSCP007', 'title' => '2x Barlow Lens', 'category' => 'Lenses', 'img' => 'https://images.unsplash.com/photo-1502136969935-8d8eef54d77e?auto=format&fit=crop&w=1200&q=80'],
    ['asin' => 'B0TSCP008', 'title' => 'Collimation Laser Tool', 'category' => 'Maintenance', 'img' => 'https://images.unsplash.com/photo-1489515217757-5fd1be406fef?auto=format&fit=crop&w=1200&q=80'],
    ['asin' => 'B0TSCP009', 'title' => 'Dew Heater Strap Set', 'category' => 'Weather Control', 'img' => 'https://images.unsplash.com/photo-1531512073830-ba890ca4eba2?auto=format&fit=crop&w=1200&q=80'],
    ['asin' => 'B0TSCP010', 'title' => 'Heavy Duty Carry Case', 'category' => 'Storage', 'img' => 'https://images.unsplash.com/photo-1521295121783-8a321d551ad2?auto=format&fit=crop&w=1200&q=80'],
    ['asin' => 'B0TSCP011', 'title' => 'Star Chart Planisphere', 'category' => 'Planning', 'img' => 'https://images.unsplash.com/photo-1465101178521-c1a9136a3b99?auto=format&fit=crop&w=1200&q=80'],
    ['asin' => 'B0TSCP012', 'title' => 'Nebula Filter UHC', 'category' => 'Filters', 'img' => 'https://images.unsplash.com/photo-1473929735477-7df8f0a8e2da?auto=format&fit=crop&w=1200&q=80'],
    ['asin' => 'B0TSCP013', 'title' => 'Moon & Planet Filter Set', 'category' => 'Filters', 'img' => 'https://images.unsplash.com/photo-1543722530-d2c3201371e7?auto=format&fit=crop&w=1200&q=80'],
    ['asin' => 'B0TSCP014', 'title' => 'Adjustable Observing Chair', 'category' => 'Comfort', 'img' => 'https://images.unsplash.com/photo-1517971071642-34a2d3ecc9cd?auto=format&fit=crop&w=1200&q=80'],
    ['asin' => 'B0TSCP015', 'title' => 'Portable Power Station 300W', 'category' => 'Power', 'img' => 'https://images.unsplash.com/photo-1581090464777-f3220bbe1b8b?auto=format&fit=crop&w=1200&q=80'],
    ['asin' => 'B0TSCP016', 'title' => 'Solar Filter Film Kit', 'category' => 'Solar', 'img' => 'https://images.unsplash.com/photo-1500534314209-a25ddb2bd429?auto=format&fit=crop&w=1200&q=80'],
    ['asin' => 'B0TSCP017', 'title' => 'Telescope Cleaning Kit', 'category' => 'Maintenance', 'img' => 'https://images.unsplash.com/photo-1520175480921-4edfa2983e0f?auto=format&fit=crop&w=1200&q=80'],
    ['asin' => 'B0TSCP018', 'title' => 'Phone Mount Pro Adapter', 'category' => 'Adapters', 'img' => 'https://images.unsplash.com/photo-1498049860654-af1a5c566876?auto=format&fit=crop&w=1200&q=80'],
    ['asin' => 'B0TSCP019', 'title' => 'Alt-Azimuth Mount Head', 'category' => 'Mounts', 'img' => 'https://images.unsplash.com/photo-1499914485622-a88fac536970?auto=format&fit=crop&w=1200&q=80'],
    ['asin' => 'B0TSCP020', 'title' => 'Wide Field 32mm Eyepiece', 'category' => 'Eyepieces', 'img' => 'https://images.unsplash.com/photo-1469474968028-56623f02e42e?auto=format&fit=crop&w=1200&q=80'],
];

$stmt = $pdo->prepare(
    'INSERT OR IGNORE INTO products (
        asin, slug, title, description, category_slug, category_name,
        image_url, affiliate_url, status,
        last_synced_at, created_at, updated_at
    ) VALUES (
        :asin, :slug, :title, :description, :category_slug, :category_name,
        :image_url, :affiliate_url, :status,
        :last_synced_at, :created_at, :updated_at
    )'
);

$inserted = 0;
$now = gmdate('c');

foreach ($items as $idx => $item) {
    $syncTime = gmdate('c', time() - (($idx % 9) * 3600));

    $stmt->execute([
        ':asin' => $item['asin'],
        ':slug' => slugify($item['title']),
        ':title' => $item['title'],
        ':description' => 'Practical upgrade for clearer views and smoother observing sessions.',
        ':category_slug' => slugify($item['category']),
        ':category_name' => $item['category'],
        ':image_url' => $item['img'],
        ':affiliate_url' => 'https://www.amazon.com/dp/' . $item['asin'] . '?tag=fortelescopes-20',
        ':status' => 'published',
        ':last_synced_at' => $syncTime,
        ':created_at' => $now,
        ':updated_at' => $now,
    ]);

    $inserted += $stmt->rowCount();
}

echo 'Inserted products: ' . $inserted . PHP_EOL;
