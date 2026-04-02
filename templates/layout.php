<?php

declare(strict_types=1);

$uri = $_SERVER['REQUEST_URI'] ?? '/';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <meta name="description" content="<?= e($meta['description'] ?? 'Affiliate product recommendations for telescope accessories.') ?>">
    <meta name="robots" content="<?= e($meta['robots'] ?? 'index,follow') ?>">
    <link rel="canonical" href="<?= e($canonicalUrl ?? absolute_url('/')) ?>">
    <meta property="og:type" content="<?= e($meta['type'] ?? 'website') ?>">
    <meta property="og:title" content="<?= e($pageTitle) ?>">
    <meta property="og:description" content="<?= e($meta['description'] ?? '') ?>">
    <meta property="og:url" content="<?= e($canonicalUrl ?? absolute_url('/')) ?>">
    <meta property="og:image" content="<?= e($meta['image'] ?? '') ?>">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= e($pageTitle) ?>">
    <meta name="twitter:description" content="<?= e($meta['description'] ?? '') ?>">
    <meta name="twitter:image" content="<?= e($meta['image'] ?? '') ?>">
    <link rel="icon" type="image/png" href="<?= e(url('/assets/logo/logo.png')) ?>">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= e(url('/assets/logo/32.png')) ?>">
    <link rel="icon" type="image/png" sizes="192x192" href="<?= e(url('/assets/logo/192.png')) ?>">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= e(url('/assets/logo/180.png')) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=Spectral:ital,wght@0,500;0,700;1,500&display=swap" rel="stylesheet">
    <?php foreach (($jsonLd ?? []) as $schema): ?>
        <script type="application/ld+json"><?= json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
    <?php endforeach; ?>
    <style>
        :root {
            color-scheme: light;
            --bg-ink: #081018;
            --bg-deep: #0f1f2e;
            --bg-soft: #f1efe7;
            --brand: #ff7a1a;
            --brand-dark: #cc5300;
            --mint: #b8ffe5;
            --text: #101826;
            --text-soft: #4d5666;
            --line: rgba(10, 24, 40, 0.12);
            --card-shadow: 0 16px 30px rgba(7, 14, 20, 0.16);
            --radius: 18px;
        }

        /* Typography: Only 2 fonts - Sora for UI/body, Spectral for headings */
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; }
        body {
            font-family: "Sora", "Segoe UI Variable", "Trebuchet MS", sans-serif;
            color: var(--text);
            background:
                radial-gradient(1400px 420px at 10% -5%, #284763 0%, transparent 60%),
                radial-gradient(1200px 360px at 95% 0%, #1f5a4e 0%, transparent 58%),
                linear-gradient(180deg, var(--bg-ink) 0 220px, var(--bg-soft) 220px 100%);
            min-height: 100vh;
        }

        a { color: inherit; }

        .topbar {
            max-width: 1180px;
            margin: 0 auto;
            padding: 10px 18px 14px;
            color: #f9f7f2;
        }

        .brand-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 800;
            letter-spacing: 0.045em;
            text-transform: uppercase;
            font-size: 28px;
            line-height: 1;
            color: #fff;
            text-decoration: none;
        }

        .brand-text {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .brand-title {
            font-size: clamp(30px, 4vw, 44px);
            letter-spacing: 0.06em;
            font-weight: 800;
            color: #fff;
        }

        .brand-sub {
            font-size: 10px;
            letter-spacing: 0.18em;
            font-weight: 700;
            color: rgba(235, 244, 255, 0.82);
        }

        .brand:hover .brand-title,
        .brand:focus-visible .brand-title {
            color: #ffffff;
            text-decoration: underline;
            text-underline-offset: 6px;
        }

        .brand-logo-wrap {
            width: 118px;
            height: 118px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: transparent;
            border: 0;
        }

        .brand-logo {
            width: 114px;
            height: 114px;
            object-fit: cover;
            display: block;
        }

        .tagline {
            margin: 4px 0 0;
            max-width: 760px;
            font-size: 14px;
            color: rgba(255, 255, 255, 0.95);
            text-shadow: 0 1px 2px rgba(0,0,0,0.3);
        }

        nav {
            margin-top: 9px;
            display: flex;
            flex-wrap: wrap;
            gap: 9px;
        }

        nav a {
            text-decoration: none;
            border-radius: 999px;
            padding: 9px 13px;
            font-size: 12px;
            font-weight: 700;
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.25);
            color: #fff;
            transition: transform 180ms ease, background 180ms ease;
            backdrop-filter: blur(4px);
        }

        nav a:hover {
            transform: translateY(-2px);
            background: rgba(255, 255, 255, 0.25);
        }

        main {
            max-width: 1180px;
            margin: 0 auto;
            padding: 14px 18px 50px;
        }

        .hero {
            background: linear-gradient(135deg, #fffdf8 0%, #fff4df 100%);
            border: none;
            border-radius: 26px;
            padding: 26px 24px;
            box-shadow: var(--card-shadow);
            margin-bottom: 20px;
            animation: rise 500ms ease both;
        }

        .hero-kicker {
            display: inline-block;
            background: #1e293b;
            color: #f7f4ee;
            font-size: 10px;
            letter-spacing: 0.08em;
            font-weight: 700;
            border-radius: 999px;
            padding: 5px 9px;
            text-transform: uppercase;
        }

        .hero h1, .hero h2 {
            margin: 10px 0 10px;
            font-family: "Spectral", Georgia, serif;
            font-size: clamp(30px, 6vw, 48px);
            line-height: 1.05;
            letter-spacing: -0.02em;
        }

        .hero p {
            margin: 0;
            max-width: 860px;
            color: #2e3850;
            font-size: 15px;
            line-height: 1.55;
        }

        .trust-row {
            margin-top: 18px;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .chip {
            border-radius: 999px;
            padding: 7px 10px;
            font-size: 11px;
            font-weight: 600;
            background: #fff;
            border: 1px solid #e5e7eb;
            color: #374151;
        }

        /* Trust strip - simplified without heavy borders */
        .trust-strip {
            margin: 0 0 16px;
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 10px;
        }

        .trust-box {
            background: #f8f9fc;
            border: none;
            border-radius: 12px;
            padding: 10px 11px;
            font-size: 11px;
            color: #374151;
            font-weight: 600;
        }

        .section-title {
            margin: 4px 0 12px;
            font-family: "Spectral", Georgia, serif;
            font-size: clamp(26px, 4vw, 36px);
            line-height: 1.1;
        }

        .muted {
            margin: 0 0 20px;
            font-size: 15px;
            color: var(--text-soft);
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 16px;
        }

        .card {
            border-radius: var(--radius);
            overflow: hidden;
            background: #fff;
            border: 1px solid #e5e7eb;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            transform: translateY(12px);
            opacity: 0;
            animation: rise 520ms ease forwards;
        }

        .card:nth-child(2) { animation-delay: 80ms; }
        .card:nth-child(3) { animation-delay: 150ms; }
        .card:nth-child(4) { animation-delay: 220ms; }

        .card img {
            width: 100%;
            height: 200px;
            object-fit: cover;
            display: block;
            background: linear-gradient(145deg, #f3f4f6 0%, #e5e7eb 100%);
        }

        .body {
            padding: 15px 15px 16px;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 9px;
            border-radius: 999px;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.04em;
            color: #0c2c41;
            background: linear-gradient(120deg, #e0f2fe 0%, #d1fae5 100%);
            text-transform: uppercase;
        }

        .card h3 {
            margin: 10px 0 8px;
            font-size: 18px;
            line-height: 1.22;
            letter-spacing: -0.01em;
            font-family: "Sora", sans-serif;
        }

        .card-copy {
            margin: 0;
            color: #4d5666;
            font-size: 14px;
            line-height: 1.46;
            min-height: 42px;
        }

        .price-line {
            margin: 13px 0 12px;
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            gap: 8px;
        }

        .price {
            font-size: 22px;
            font-weight: 700;
            letter-spacing: -0.02em;
            color: #0f1c30;
        }

        .hint {
            font-size: 11px;
            color: #6b7280;
            font-weight: 600;
        }

        /* Update freshness pills - subdued to not compete with CTA buttons */
        .update-pill {
            display: inline-block;
            border-radius: 999px;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.03em;
            padding: 3px 7px;
            margin-bottom: 6px;
            text-transform: uppercase;
            opacity: 0.75;
        }

        .fresh { background: #e8f5e9; color: #2e7d32; }
        .aging { background: #fff8e1; color: #f57f17; }
        .stale { background: #ffebee; color: #c62828; }

        .btn, .card-cta {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-decoration: none;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 800;
            letter-spacing: 0.02em;
            transition: transform 180ms ease, box-shadow 180ms ease;
        }

        /* Primary CTA button - highest visual priority */
        .card-cta {
            width: 100%;
            background: linear-gradient(140deg, var(--brand) 0%, #ff5c00 100%);
            color: #fff;
            padding: 12px 14px;
            box-shadow: 0 9px 18px rgba(255, 122, 26, 0.35);
        }

        .card-cta:hover { transform: translateY(-2px); }

        .btn {
            background: linear-gradient(140deg, var(--brand) 0%, #ff5c00 100%);
            color: #fff;
            padding: 12px 16px;
            box-shadow: 0 10px 18px rgba(255, 122, 26, 0.3);
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 24px rgba(255, 122, 26, 0.4);
        }

        .panel {
            background: #fff;
            border: none;
            border-radius: 24px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            animation: rise 520ms ease both;
        }

        .panel img {
            width: 100%;
            border-radius: 16px;
            max-height: 520px;
            object-fit: cover;
        }

        .tier-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
            margin: 0 0 18px;
        }

        /* Tier cards - simplified with subtle background instead of heavy borders */
        .tier-card {
            background: #fafbfc;
            border: 1px solid #eef2f6;
            border-radius: 16px;
            padding: 14px;
            box-shadow: none;
        }

        .tier-tag {
            display: inline-block;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            border-radius: 999px;
            padding: 4px 8px;
            margin-bottom: 8px;
        }

        .tier-top { background: #e3f2fd; color: #1565c0; }
        .tier-budget { background: #e8f5e9; color: #2e7d32; }
        .tier-premium { background: #fff3e0; color: #ef6c00; }

        .tier-card h4 {
            margin: 0 0 7px;
            font-size: 16px;
            line-height: 1.25;
        }

        .tier-card p {
            margin: 0 0 8px;
            color: #4d5666;
            font-size: 13px;
        }

        /* Compare tables - cleaner with dividers only */
        .compare-table {
            margin-top: 16px;
            border: none;
            border-radius: 0;
            overflow: visible;
            background: transparent;
        }

        .compare-row {
            display: grid;
            grid-template-columns: 170px 1fr;
            border-bottom: 1px solid #eef2f6;
            padding: 8px 0;
        }

        .compare-row:last-child { border-bottom: 0; }

        .compare-label {
            background: transparent;
            padding: 11px 12px 11px 0;
            font-size: 12px;
            font-weight: 700;
            color: #6b7280;
        }

        .compare-value {
            padding: 11px 12px 11px 0;
            font-size: 14px;
            color: #1f2a3d;
        }

        .pill {
            display: inline-block;
            border-radius: 999px;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            padding: 3px 7px;
            margin-right: 6px;
        }

        .pill.ok { background: #dcfce7; color: #166534; }
        .pill.warn { background: #fef3c7; color: #92400e; }

        .mobile-sticky-cta {
            display: none;
        }

        .notice {
            margin-top: 22px;
            background: #f0fdf4;
            border: none;
            border-radius: 12px;
            font-size: 11px;
            color: #166534;
            padding: 10px 12px;
            font-weight: 600;
        }

        footer {
            max-width: 1180px;
            margin: 0 auto;
            padding: 0 18px 34px;
            color: #2f3d58;
            font-size: 13px;
            text-align: center;
        }

        footer a { color: #213b63; text-underline-offset: 2px; }

        @keyframes rise {
            from { transform: translateY(16px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        /* Responsive fixes - proper menu wrapping without cutting */
        @media (max-width: 760px) {
            .hero { padding: 18px 16px; border-radius: 20px; }
            .hero h1, .hero h2 { font-size: clamp(26px, 9vw, 38px); }
            .topbar { padding: 10px 14px 13px; }
            .brand { gap: 10px; }
            .brand-logo-wrap { width: 88px; height: 88px; }
            .brand-logo { width: 84px; height: 84px; }
            .brand-title { font-size: clamp(20px, 7vw, 26px); letter-spacing: 0.045em; }
            .brand-sub { font-size: 9px; letter-spacing: 0.14em; }
            .tagline { font-size: 13px; }
            nav { gap: 6px; }
            nav a { padding: 7px 11px; font-size: 11px; }
            main { padding: 8px 14px 88px; }
            .card img { height: 188px; }
            .trust-strip { grid-template-columns: 1fr; }
            .tier-grid { grid-template-columns: 1fr; }
            .compare-row { grid-template-columns: 1fr; }
            .compare-label { border-bottom: 1px solid #eef2f6; padding-left: 0; }
            .compare-value { padding-left: 0; }
            .mobile-sticky-cta {
                position: fixed;
                left: 10px;
                right: 10px;
                bottom: 10px;
                z-index: 50;
                display: block;
                padding: 12px 14px;
                border-radius: 14px;
                text-align: center;
                background: linear-gradient(140deg, var(--brand) 0%, #ff5c00 100%);
                color: #fff;
                text-decoration: none;
                font-size: 14px;
                font-weight: 800;
                box-shadow: 0 16px 24px rgba(255, 92, 0, 0.35);
            }
        }
    </style>
</head>
<body>
<header class="topbar">
    <div class="brand-row">
        <a class="brand" href="<?= e(url('/')) ?>" aria-label="<?= e(APP_NAME) ?> home">
            <span class="brand-logo-wrap">
                <img class="brand-logo" src="<?= e(url('/assets/logo/logo_original.png')) ?>" alt="<?= e(APP_NAME) ?> logo">
            </span>
            <span class="brand-text">
                <span class="brand-title"><?= e(APP_NAME) ?></span>
                <span class="brand-sub">Telescope Accessories & Buyer's Picks</span>
            </span>
        </a>
    </div>
    <p class="tagline">Buy smarter telescope accessories with practical shortlists, clear comparisons, and direct links to proven gear.</p>
    <nav>
        <a href="<?= e(url('/')) ?>">Home</a>
        <a href="<?= e(url('/telescopes')) ?>">Telescopes</a>
        <a href="<?= e(url('/accessories')) ?>">Accessories</a>
        <a href="<?= e(url('/guides')) ?>">Guides</a>
        <a href="<?= e(url('/about')) ?>">About</a>
        <a href="<?= e(url('/contact')) ?>">Contact</a>
    </nav>
</header>
<main>
    <?php if (!empty($breadcrumbs) && is_array($breadcrumbs) && count($breadcrumbs) > 1): ?>
        <nav aria-label="Breadcrumb" style="margin: 0 0 12px; font-size: 13px; color: #334155;">
            <?php foreach ($breadcrumbs as $idx => $crumb): ?>
                <?php if ($idx > 0): ?><span style="opacity: .6;"> / </span><?php endif; ?>
                <?php if ($idx === count($breadcrumbs) - 1): ?>
                    <span><?= e((string) ($crumb['name'] ?? '')) ?></span>
                <?php else: ?>
                    <a href="<?= e((string) ($crumb['url'] ?? '/')) ?>"><?= e((string) ($crumb['name'] ?? '')) ?></a>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>
    <?php endif; ?>
    <?php require $template; ?>
    <div class="notice">Affiliate disclosure: as an Amazon Associate, this site may earn from qualifying purchases.</div>
</main>
<footer>
    <div><?= e(APP_NAME) ?> - <?= date('Y') ?> - Domain: <?= e(SITE_DOMAIN) ?></div>
    <div style="margin-top: 8px;">
        <a href="<?= e(url('/about')) ?>">About</a> |
        <a href="<?= e(url('/contact')) ?>">Contact</a> |
        <a href="<?= e(url('/affiliate-disclosure')) ?>">Affiliate Disclosure</a> |
        <a href="<?= e(url('/privacy-policy')) ?>">Privacy Policy</a> |
        <a href="<?= e(url('/terms-of-use')) ?>">Terms of Use</a>
    </div>
</footer>
<script>
(function () {
  var fallback = <?= json_encode(product_image_fallback_url()) ?>;
  var imgs = document.querySelectorAll('.card img, .panel img');
  imgs.forEach(function (img) {
    function applyFallback() {
      if (!img.src || img.src.indexOf(fallback) !== -1) return;
      img.src = fallback;
    }
    img.addEventListener('error', applyFallback);
    if (img.complete && (!img.naturalWidth || img.naturalWidth < 2)) {
      applyFallback();
    }
  });
})();
</script>
</body>
</html>
