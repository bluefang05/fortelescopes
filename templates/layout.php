<?php

declare(strict_types=1);

$uri = $_SERVER['REQUEST_URI'] ?? '/';
$currentPath = parse_url($uri, PHP_URL_PATH) ?: '/';
$navPath = rtrim((string) $currentPath, '/');
$navPath = $navPath === '' ? '/' : $navPath;
$isNavCurrent = static function (string $target) use ($navPath): bool {
    return $navPath === $target;
};
$showAdminNav = frontend_admin_preview_enabled();
$isHomePage = $navPath === '/';
$showBreadcrumbs = false;
if (!empty($breadcrumbs) && is_array($breadcrumbs) && count($breadcrumbs) > 1) {
    $showBreadcrumbs = str_starts_with($navPath, '/blog')
        || str_starts_with($navPath, '/guides')
        || str_starts_with($navPath, '/best-')
        || $navPath === '/about'
        || $navPath === '/contact'
        || $navPath === '/privacy-policy'
        || $navPath === '/terms-of-use'
        || $navPath === '/affiliate-disclosure';
}
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
    <?php if (!empty($meta['prev_url'])): ?>
        <link rel="prev" href="<?= e((string) $meta['prev_url']) ?>">
    <?php endif; ?>
    <?php if (!empty($meta['next_url'])): ?>
        <link rel="next" href="<?= e((string) $meta['next_url']) ?>">
    <?php endif; ?>
    <meta name="theme-color" content="#081018">
    <link rel="icon" type="image/png" href="<?= e(url('/assets/logo/32.png')) ?>">
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

        .skip-link {
            position: absolute;
            left: 18px;
            top: -48px;
            z-index: 30;
            padding: 10px 14px;
            border-radius: 12px;
            background: #fff;
            color: #081018;
            font-weight: 800;
            text-decoration: none;
            box-shadow: 0 10px 22px rgba(10, 20, 34, 0.16);
        }

        .skip-link:focus {
            top: 14px;
        }

        .topbar {
            width: 100%;
            margin: 0;
            padding: 10px 28px 12px;
            color: #f9f7f2;
            background: linear-gradient(180deg, rgba(3, 10, 18, 0.98) 0%, rgba(3, 10, 18, 0.9) 100%);
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
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

        nav {
            margin-top: 8px;
            display: flex;
            flex-wrap: wrap;
            gap: 14px;
            align-items: center;
        }

        nav a {
            text-decoration: none;
            border-radius: 0;
            padding: 4px 0;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            background: transparent;
            border: 0;
            color: rgba(236, 243, 255, 0.9);
            transition: color 180ms ease, border-color 180ms ease;
            border-bottom: 2px solid transparent;
        }

        nav a:hover {
            color: #ffffff;
            border-color: rgba(255, 138, 66, 0.9);
        }

        nav a[aria-current="page"] {
            color: #fff;
            border-color: #ff8a42;
        }

        .nav-current {
            padding: 4px 0;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #fff;
            cursor: default;
            border-bottom: 2px solid #ff8a42;
        }

        a:focus-visible,
        button:focus-visible,
        summary:focus-visible {
            outline: 3px solid rgba(184, 255, 229, 0.9);
            outline-offset: 3px;
        }

        main {
            width: 100%;
            margin: 0;
            padding: 0 0 50px;
        }

        .hero {
            background: linear-gradient(135deg, #fffdf8 0%, #fff4df 100%);
            border: 1px solid #f0d3aa;
            border-radius: 26px;
            padding: 26px 24px;
            box-shadow: var(--card-shadow);
            margin-bottom: 20px;
            animation: rise 500ms ease both;
            margin-top: 14px;
        }

        .hero--with-image {
            position: relative;
            background: linear-gradient(140deg, #07101a 0%, #0f2234 52%, #14273d 100%);
            border: 1px solid rgba(139, 184, 230, 0.18);
            min-height: clamp(360px, 54vw, 620px);
            overflow: hidden;
            padding: 0;
            border-radius: 0;
        }

        .hero-content {
            min-width: 0;
        }

        .hero-visual {
            border-radius: 18px;
            overflow: hidden;
            border: 0;
            background: linear-gradient(145deg, #0f1c2d 0%, #14283d 100%);
            box-shadow: none;
            min-height: 100%;
            position: absolute;
            inset: 0;
        }

        .hero-visual picture,
        .hero-visual img {
            display: block;
            width: 100%;
            height: 100%;
        }

        .hero-visual img {
            object-fit: cover;
            object-position: center;
            filter: brightness(0.76) saturate(1.08);
        }

        .hero-visual::after {
            content: "";
            position: absolute;
            inset: 0;
            background:
                radial-gradient(120% 120% at 20% 85%, rgba(13, 32, 54, 0.62) 0%, rgba(13, 32, 54, 0) 65%),
                linear-gradient(180deg, rgba(5, 12, 22, 0.06) 0%, rgba(5, 12, 22, var(--hero-overlay-opacity, 0.55)) 100%);
            pointer-events: none;
        }

        .hero-visual-overlay {
            position: absolute;
            left: 18px;
            bottom: 18px;
            z-index: 2;
        }

        .hero-visual-label {
            display: inline-flex;
            align-items: center;
            padding: 7px 10px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.07em;
            text-transform: uppercase;
            color: #f7f9fc;
            background: rgba(5, 11, 20, 0.62);
            border: 1px solid rgba(255, 255, 255, 0.22);
        }

        .hero-kicker {
            display: inline-block;
            background: #121f2f;
            color: #f7f4ee;
            font-size: 11px;
            letter-spacing: 0.08em;
            font-weight: 700;
            border-radius: 999px;
            padding: 6px 10px;
            text-transform: uppercase;
        }
        .draft-preview-bar {
            max-width: 1180px;
            margin: 8px auto 0;
            padding: 10px 14px;
            border-radius: 12px;
            border: 1px solid #f6c89a;
            background: #fff3df;
            color: #7d2d00;
            font-size: 13px;
            font-weight: 700;
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
            font-size: 16px;
            line-height: 1.52;
        }

        .hero--with-image .hero-kicker {
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.28);
            color: #eef6ff;
        }

        .hero--with-image .hero-content {
            position: relative;
            z-index: 3;
            max-width: 760px;
            margin: 0 auto;
            text-align: center;
            padding: clamp(44px, 9vw, 92px) 18px;
        }

        .hero-visual-block {
            width: 100%;
            min-height: inherit;
            border-radius: 0;
            border: 0;
            box-shadow: none;
            margin: 0;
        }

        .content-constrained {
            max-width: 1360px;
            margin: 0 auto;
            padding: 0 28px;
        }

        .hero-visual-block .visual-overlay-content {
            justify-content: center;
            align-items: center;
            text-align: center;
            padding: clamp(44px, 9vw, 92px) 18px;
            z-index: 2;
        }

        .hero-visual-block .promo-cta {
            border-radius: 999px;
            padding: 13px 20px;
            font-size: 15px;
        }

        .hero--with-image h1,
        .hero--with-image h2 {
            color: #f8fbff;
            text-shadow: 0 8px 24px rgba(0, 0, 0, 0.35);
            font-size: clamp(36px, 6.5vw, 64px);
            line-height: 1.14;
            margin-bottom: 16px;
        }

        .hero--with-image p {
            color: rgba(236, 244, 255, 0.9);
            margin-left: auto;
            margin-right: auto;
            font-size: clamp(16px, 2.2vw, 22px);
            line-height: 1.56;
        }

        .hero--with-image .hero-visual::after {
            background:
                radial-gradient(130% 120% at 18% 88%, rgba(7, 20, 36, 0.72) 0%, rgba(7, 20, 36, 0) 64%),
                linear-gradient(180deg, rgba(5, 12, 22, 0.1) 0%, rgba(5, 12, 22, calc(var(--hero-overlay-opacity, 0.55) + 0.18)) 100%);
        }

        .trust-row {
            margin-top: 18px;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .chip {
            border-radius: 999px;
            padding: 8px 11px;
            font-size: 12px;
            font-weight: 700;
            background: #fff;
            border: 1px solid #e8d7bc;
            color: #21314b;
        }

        .hero--with-image .chip {
            background: rgba(7, 18, 31, 0.65);
            border-color: rgba(198, 222, 250, 0.42);
            color: #edf5ff;
        }

        .hero--with-image .trust-row {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 8px;
            align-items: stretch;
        }

        .hero--with-image .trust-row .chip {
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 42px;
            line-height: 1.3;
        }

        .hero-main-cta {
            align-self: center;
            display: inline-flex;
            margin-left: auto;
            margin-right: auto;
            min-width: 210px;
            border-radius: 999px;
            border: 1px solid #ff9e5a;
            box-shadow: 0 10px 24px rgba(255, 95, 0, 0.42);
            font-size: 15px;
            padding: 13px 20px;
        }

        .hero-visual-block .visual-overlay-content .hero-main-cta {
            align-self: center;
            margin-left: auto;
            margin-right: auto;
        }

        .panel-compact {
            padding: 14px 16px;
        }

        .goal-links {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 10px;
        }

        .goal-links a {
            display: block;
            text-decoration: none;
            text-align: center;
            padding: 14px 14px;
            min-height: 56px;
            border-radius: 12px;
            border: 1px solid #d8dee9;
            background: #fff;
            font-size: 14px;
            font-weight: 800;
            color: #182a45;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .goal-links a:hover {
            transform: translateY(-1px);
            border-color: #b8c4d6;
        }

        .goal-links a.is-primary {
            background: linear-gradient(140deg, #ff7a1a 0%, #ff5c00 100%);
            color: #fff;
            border-color: #ff8f43;
            box-shadow: 0 12px 20px rgba(255, 92, 0, 0.28);
        }

        .promo-strip {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
            margin: 0 0 20px;
        }

        .promo-goal-links {
            grid-column: 1 / -1;
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 10px;
            margin-top: 2px;
        }

        .promo-goal-links a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 42px;
            text-decoration: none;
            text-align: center;
            border-radius: 999px;
            padding: 8px 12px;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.02em;
            border: 1px solid rgba(214, 229, 248, 0.6);
            background: rgba(7, 20, 34, 0.46);
            color: #f0f6ff;
            backdrop-filter: blur(2px);
            transition: transform 180ms ease, border-color 180ms ease, background 180ms ease;
        }

        .promo-goal-links a:hover {
            transform: translateY(-1px);
            border-color: rgba(255, 159, 96, 0.85);
            background: rgba(7, 20, 34, 0.7);
        }

        .promo-goal-links a.is-primary {
            border-color: #ff9e5a;
            background: linear-gradient(140deg, #ff7a1a 0%, #ff5c00 100%);
            color: #fff;
            box-shadow: 0 10px 20px rgba(255, 92, 0, 0.32);
        }

        .visual-block {
            position: relative;
            border-radius: 18px;
            overflow: hidden;
            min-height: 250px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 14px 26px rgba(10, 20, 34, 0.2);
            background: #0e2033;
        }

        .visual-block img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            filter: brightness(0.82) saturate(1.06);
        }

        .visual-overlay-content {
            position: absolute;
            inset: 0;
            display: flex;
            flex-direction: column;
            gap: 8px;
            padding: 16px;
            color: #f7fbff;
        }

        .visual-text-block {
            display: inline-flex;
            flex-direction: column;
            gap: 8px;
            width: fit-content;
            max-width: min(92%, 760px);
            padding: 10px 14px;
            border-radius: 12px;
            background: linear-gradient(180deg, rgba(4, 12, 22, 0.58) 0%, rgba(4, 12, 22, 0.78) 100%);
            border: 1px solid rgba(182, 206, 238, 0.2);
            box-shadow: 0 10px 24px rgba(0, 0, 0, 0.24);
        }

        .hero-visual-block .visual-text-block {
            padding: 14px 18px;
            border-radius: 16px;
            max-width: min(92%, 920px);
        }

        .visual-overlay-content h3 {
            margin: 0;
            font-size: clamp(24px, 3vw, 34px);
            line-height: 1.1;
            font-family: "Spectral", Georgia, serif;
        }

        .visual-overlay-content p {
            margin: 0;
            max-width: 62ch;
            color: rgba(244, 249, 255, 0.94);
        }

        .visual-eyebrow {
            display: inline-block;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.07em;
            text-transform: uppercase;
            color: #e6f0ff;
        }

        .visual-pos-left .visual-overlay-content { justify-content: center; align-items: flex-start; text-align: left; }
        .visual-pos-center .visual-overlay-content { justify-content: center; align-items: center; text-align: center; }
        .visual-pos-right .visual-overlay-content { justify-content: center; align-items: flex-end; text-align: right; }
        .visual-pos-bottom-left .visual-overlay-content { justify-content: flex-end; align-items: flex-start; text-align: left; }
        .visual-pos-bottom-center .visual-overlay-content { justify-content: flex-end; align-items: center; text-align: center; }
        .visual-pos-bottom-right .visual-overlay-content { justify-content: flex-end; align-items: flex-end; text-align: right; }

        .visual-overlay-none::after,
        .visual-overlay-light::after,
        .visual-overlay-medium::after,
        .visual-overlay-dark::after {
            content: "";
            position: absolute;
            inset: 0;
            pointer-events: none;
        }
        .visual-overlay-none::after { background: linear-gradient(180deg, rgba(0,0,0,.03), rgba(0,0,0,.08)); }
        .visual-overlay-light::after { background: linear-gradient(180deg, rgba(0,0,0,.08), rgba(0,0,0,.22)); }
        .visual-overlay-medium::after { background: linear-gradient(180deg, rgba(0,0,0,.14), rgba(0,0,0,.42)); }
        .visual-overlay-dark::after { background: linear-gradient(180deg, rgba(0,0,0,.18), rgba(0,0,0,.62)); }

        .visual-size-full { grid-column: span 2; min-height: 300px; }
        .visual-size-half { grid-column: span 1; }
        .visual-size-third { grid-column: span 1; min-height: 220px; }

        .banner-strip { grid-template-columns: repeat(2, minmax(0, 1fr)); }

        .promo-tile {
            position: relative;
            border-radius: 18px;
            overflow: hidden;
            min-height: 250px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 14px 26px rgba(10, 20, 34, 0.2);
            background: #0e2033;
        }

        .promo-tile img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            filter: brightness(0.78) saturate(1.08);
        }

        .promo-overlay {
            position: absolute;
            inset: 0;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            gap: 8px;
            padding: 16px;
            background: linear-gradient(180deg, rgba(3, 8, 16, 0) 40%, rgba(3, 8, 16, 0.72) 100%);
        }

        .promo-overlay h3 {
            margin: 0;
            color: #f7fbff;
            font-size: clamp(22px, 3vw, 31px);
            line-height: 1.08;
            font-family: "Spectral", Georgia, serif;
        }

        .promo-cta {
            align-self: flex-start;
            border-radius: 999px;
            padding: 10px 14px;
            font-size: 12px;
        }

        .mini-trust {
            display: inline-block;
            margin: 0 6px 8px 0;
            border-radius: 999px;
            padding: 4px 8px;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            background: #eef4ff;
            color: #1d3b6d;
            border: 1px solid #d5e2f8;
        }

        .home-most-loved .card img,
        .grid .card img {
            aspect-ratio: 1 / 1;
            height: auto;
            max-height: 320px;
            object-fit: contain;
            object-position: center;
            background: #f5f7fb;
            padding: 8px;
        }

        .content-lazy { content-visibility: visible; }

        .mobile-context-cta {
            display: none;
        }

        .trust-strip {
            margin: 0 0 16px;
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 10px;
        }

        .trust-box {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 10px 11px;
            font-size: 12px;
            color: #253650;
            font-weight: 700;
        }

        .trust-box[style*="background-color: transparent"] {
            background: #fff !important;
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

        .blog-grid {
            grid-template-columns: repeat(3, minmax(0, 1fr));
            align-items: start;
        }

        .card {
            border-radius: var(--radius);
            overflow: hidden;
            background: #fff;
            border: 1px solid var(--line);
            box-shadow: 0 10px 20px rgba(10, 20, 34, 0.08);
            transform: translateY(12px);
            opacity: 0;
            animation: rise 520ms ease forwards;
        }

        .card:nth-child(2) { animation-delay: 80ms; }
        .card:nth-child(3) { animation-delay: 150ms; }
        .card:nth-child(4) { animation-delay: 220ms; }

        .card img {
            width: 100%;
            height: auto;
            aspect-ratio: 1 / 1;
            object-fit: contain;
            display: block;
            background: linear-gradient(145deg, #eef2f8 0%, #f7f9fc 100%);
            padding: 8px;
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
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.04em;
            color: #0c2c41;
            background: linear-gradient(120deg, #d9f8ff 0%, #c9ffe8 100%);
            text-transform: uppercase;
        }

        .card h3 {
            margin: 10px 0 8px;
            font-size: 19px;
            line-height: 1.22;
            letter-spacing: -0.01em;
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
            font-size: 24px;
            font-weight: 800;
            letter-spacing: -0.02em;
            color: #0f1c30;
        }

        .hint {
            font-size: 12px;
            color: #5f6b7d;
            font-weight: 600;
        }

        .update-pill {
            display: inline-block;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.03em;
            padding: 4px 8px;
            margin-bottom: 8px;
            text-transform: uppercase;
        }

        .fresh { background: #e4ffea; color: #176330; }
        .aging { background: #fff5df; color: #85520e; }
        .stale { background: #ffe7e7; color: #8a2323; }

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

        .card-cta {
            width: 100%;
            background: linear-gradient(140deg, var(--brand) 0%, #ff5c00 100%);
            color: #fff;
            padding: 11px 12px;
            box-shadow: 0 9px 18px rgba(255, 122, 26, 0.28);
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

        .pagination {
            margin-top: 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            flex-wrap: wrap;
        }

        .pagination-info {
            font-size: 13px;
            color: var(--text-soft);
            font-weight: 700;
        }

        .pagination-nav {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .pagination-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 38px;
            padding: 9px 12px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 800;
            text-decoration: none;
            border: 1px solid #d2d8e2;
            background: #fff;
            color: #12213a;
            transition: transform 180ms ease, border-color 180ms ease;
        }

        .pagination-link:hover {
            transform: translateY(-1px);
            border-color: #b4bfce;
        }

        .pagination-link.active {
            background: linear-gradient(140deg, var(--brand) 0%, #ff5c00 100%);
            border-color: transparent;
            color: #fff;
            box-shadow: 0 8px 18px rgba(255, 122, 26, 0.28);
        }

        .amazon-btn {
            background: linear-gradient(140deg, #ffd814 0%, #f7ca00 100%) !important;
            color: #111 !important;
            font-weight: 900;
            border: 1px solid #f2c200;
            box-shadow: 0 10px 18px rgba(242, 194, 0, 0.35);
        }

        .amazon-btn:hover {
            box-shadow: 0 14px 24px rgba(242, 194, 0, 0.45);
        }

        .panel {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 24px;
            padding: 18px;
            box-shadow: 0 10px 22px rgba(10, 20, 34, 0.09);
            animation: rise 520ms ease both;
        }

        .panel img {
            width: 100%;
            border-radius: 16px;
            max-height: 520px;
            object-fit: cover;
        }

        .product-hero-media {
            width: 100%;
            max-width: 640px;
            aspect-ratio: 1 / 1;
            height: auto;
            margin: 0 auto;
            border-radius: 16px;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.08);
            background: linear-gradient(145deg, #eef2f8 0%, #f7f9fc 100%);
            padding: 10px;
        }

        .product-hero-image {
            width: 100% !important;
            height: 100% !important;
            max-height: none !important;
            object-fit: contain !important;
            object-position: center center;
            background: transparent;
            display: block;
        }

        .article-prose {
            color: #1c2940;
            font-size: 16px;
            line-height: 1.75;
        }

        .article-prose > *:first-child { margin-top: 0; }
        .article-prose > *:last-child { margin-bottom: 0; }

        .article-prose h2,
        .article-prose h3,
        .article-prose h4 {
            color: #13233a;
            line-height: 1.25;
            letter-spacing: -0.01em;
        }

        .article-prose h2 {
            margin: 26px 0 12px;
            font-size: clamp(24px, 4vw, 34px);
            font-family: "Spectral", Georgia, serif;
        }

        .article-prose h3 {
            margin: 22px 0 10px;
            font-size: clamp(20px, 3vw, 28px);
            font-family: "Spectral", Georgia, serif;
        }

        .article-prose h4 {
            margin: 16px 0 8px;
            font-size: 19px;
            font-weight: 700;
        }

        .article-prose p,
        .article-prose ul,
        .article-prose ol,
        .article-prose blockquote {
            margin: 0 0 14px;
        }

        .article-prose ul,
        .article-prose ol {
            padding-left: 20px;
        }

        .article-prose li {
            margin-bottom: 6px;
        }

        .article-prose blockquote {
            border-left: 4px solid #ceddf6;
            background: #f6f9ff;
            border-radius: 10px;
            padding: 12px 14px;
            color: #2b3c57;
        }

        .article-prose .blog-post > header {
            margin-bottom: 20px;
            padding-bottom: 14px;
            border-bottom: 1px solid #e7edf5;
        }

        .article-prose .blog-post > header h1 {
            margin: 0 0 10px;
            font-family: "Spectral", Georgia, serif;
            font-size: clamp(28px, 4vw, 42px);
            line-height: 1.08;
            color: #13233a;
        }

        .article-prose .post-meta {
            margin: 0;
            color: #52627a;
            font-size: 16px;
        }

        .article-prose .post-content {
            color: inherit;
        }

        .article-prose .telescope-cta-section {
            margin: 34px 0;
            padding: 36px 28px;
            border-radius: 18px;
            text-align: center;
            background: linear-gradient(135deg, #1d3f7a 0%, #254f94 100%);
            box-shadow: 0 16px 32px rgba(21, 43, 87, 0.28);
            color: #e8eefb;
        }

        .article-prose .telescope-cta-section h3 {
            margin: 0 0 12px;
            color: #fff;
            font-family: "Spectral", Georgia, serif;
            font-size: clamp(24px, 4vw, 36px);
        }

        .article-prose .telescope-cta-section .product-name {
            margin: 0 0 18px;
            color: #ffd65a;
            font-size: clamp(20px, 3vw, 28px);
            font-weight: 800;
        }

        .article-prose .telescope-cta-section .features-list {
            list-style: none;
            max-width: 560px;
            margin: 20px auto;
            padding: 0;
            color: #eff4ff;
        }

        .article-prose .telescope-cta-section .features-list li {
            margin: 0;
            padding: 8px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.12);
        }

        .article-prose .telescope-cta-section .features-list li::before {
            content: "✓";
            display: inline-block;
            margin-right: 10px;
            color: #67f0a6;
            font-weight: 800;
        }

        .article-prose .telescope-cta-section .price-note {
            margin: 14px 0 20px;
            color: #c9d6ef;
            font-size: 14px;
        }

        .article-prose .telescope-cta-section a[href*="amazon.com"] {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin: 10px 0 0;
            padding: 14px 30px;
            min-width: 240px;
            border-radius: 999px;
            border: 1px solid #f0c14b;
            background: linear-gradient(180deg, #ffd814 0%, #f7ca00 100%);
            color: #111827;
            text-decoration: none;
            font-size: 16px;
            font-weight: 800;
            letter-spacing: 0.01em;
            box-shadow: 0 10px 20px rgba(247, 202, 0, 0.3);
            transition: transform 160ms ease, box-shadow 160ms ease, filter 160ms ease;
        }

        .article-prose .telescope-cta-section a[href*="amazon.com"]:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 24px rgba(247, 202, 0, 0.38);
            filter: brightness(1.02);
        }

        .article-prose .telescope-cta-section .amazon-disclaimer {
            margin-top: 18px;
            color: #c8d3e6;
            font-size: 12px;
            line-height: 1.5;
        }

        .article-prose .telescope-cta-section .amazon-disclaimer a {
            color: #fff2b2;
        }

        .article-prose .discussion-reference {
            margin: 34px 0;
            padding: 24px;
            border-radius: 14px;
            border-left: 4px solid #2a5298;
            background: #f5f8fd;
        }

        .article-prose .discussion-reference h3 {
            margin-top: 0;
            color: #1d3968;
        }

        .article-prose .discussion-reference a {
            font-weight: 700;
        }

        .article-prose .post-tags {
            margin: 30px 0 0;
            padding-top: 18px;
            border-top: 1px solid #e0e7f0;
        }

        .article-prose .post-tags span {
            display: inline-block;
            margin: 4px 6px 4px 0;
            padding: 5px 12px;
            border-radius: 999px;
            background: #eaf4ff;
            color: #2a5298;
            font-size: 13px;
            font-weight: 700;
        }

        .tier-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
            margin: 0 0 18px;
        }

        .tier-card {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 16px;
            padding: 12px;
            box-shadow: 0 8px 16px rgba(10, 20, 34, 0.07);
        }

        .tier-tag {
            display: inline-block;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            border-radius: 999px;
            padding: 5px 8px;
            margin-bottom: 8px;
        }

        .tier-top { background: #dff7ff; color: #0a4960; }
        .tier-budget { background: #e5ffe9; color: #1e5a2e; }
        .tier-premium { background: #fff0dd; color: #6c3d08; }

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

        .compare-table {
            margin-top: 16px;
            border: 1px solid var(--line);
            border-radius: 16px;
            overflow: hidden;
            background: #fff;
        }

        .compare-row {
            display: grid;
            grid-template-columns: 170px 1fr;
            border-bottom: 1px solid #e9edf2;
        }

        .compare-row:last-child { border-bottom: 0; }

        .compare-label {
            background: #f8f9fc;
            padding: 11px 12px;
            font-size: 12px;
            font-weight: 700;
            color: #2c415f;
        }

        .compare-value {
            padding: 11px 12px;
            font-size: 14px;
            color: #1f2a3d;
        }

        .pill {
            display: inline-block;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            padding: 4px 8px;
        }

        .pill.ok { background: #e6ffe9; color: #1b6d2e; }
        .pill.warn { background: #fff3df; color: #8a4d09; }

        .mobile-sticky-cta {
            display: none;
        }

        .notice {
            margin-top: 22px;
            background: #0f1b2b;
            border: 1px solid #28415f;
            border-radius: 14px;
            font-size: 12px;
            color: #c8d6ea;
            padding: 10px 12px;
            font-weight: 600;
            text-align: center;
        }

        footer {
            width: 100%;
            margin: 0;
            padding: 24px 28px 42px;
            color: #2f3d58;
            font-size: 14px;
            text-align: center;
            border-top: 1px solid rgba(33, 59, 99, 0.16);
            margin-top: 24px;
        }

        footer a { color: #213b63; text-underline-offset: 2px; }

        body.page-inner main {
            max-width: 1180px;
            margin: 0 auto;
            padding: 14px 18px 50px;
        }

        body.page-inner main > nav[aria-label="Breadcrumb"] {
            margin-top: 8px !important;
        }

        body.page-inner .notice {
            margin-top: 18px;
        }

        body.page-inner footer {
            margin-top: 10px;
            padding-top: 18px;
        }

        .faq-panel {
            border-radius: 22px;
            padding: 20px;
        }

        .faq-panel .section-title {
            margin-bottom: 18px !important;
        }

        .faq-panel details {
            margin-bottom: 12px;
            border: 1px solid #dde7f4;
            border-radius: 12px;
            padding: 12px 14px;
            background: #fff;
        }

        .faq-panel details:last-child {
            margin-bottom: 0;
        }

        .faq-panel summary {
            font-weight: 800;
            cursor: pointer;
            font-size: 16px;
            color: #122744;
        }

        .faq-panel .muted {
            margin: 10px 0 0;
            font-size: 15px;
            line-height: 1.6;
        }

        @keyframes rise {
            from { transform: translateY(16px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        @media (max-width: 760px) {
            .hero { padding: 18px 16px; border-radius: 20px; }
            .hero--with-image {
                min-height: clamp(320px, 86vw, 520px);
            }
            .hero h1, .hero h2 { font-size: clamp(26px, 9vw, 38px); }
            .hero-visual {
                min-height: 180px;
            }
            .topbar { padding: 10px 14px 12px; }
            nav { gap: 10px; }
            nav a, .nav-current { font-size: 11px; letter-spacing: 0.06em; }
            .brand { gap: 10px; }
            .brand-logo-wrap { width: 88px; height: 88px; }
            .brand-logo { width: 84px; height: 84px; }
            .brand-title { font-size: clamp(20px, 7vw, 26px); letter-spacing: 0.045em; }
            .brand-sub { font-size: 9px; letter-spacing: 0.14em; }
            main { padding: 0 0 88px; }
            .content-constrained { padding: 0 14px; }
            .card img { height: 188px; }
            .trust-strip { grid-template-columns: 1fr; }
            .hero--with-image .trust-row { grid-template-columns: 1fr; }
            .goal-links { grid-template-columns: 1fr 1fr; }
            .promo-strip { grid-template-columns: 1fr; }
            .promo-goal-links { grid-template-columns: 1fr 1fr; }
            .promo-tile { min-height: 220px; }
            .visual-size-full,
            .visual-size-half,
            .visual-size-third { grid-column: span 1; min-height: 220px; }
            .tier-grid { grid-template-columns: 1fr; }
            .compare-row { grid-template-columns: 1fr; }
            .compare-label { border-bottom: 1px solid #eef2f6; }
            .product-hero-media { height: clamp(240px, 72vw, 420px); }
            .product-hero-media {
                max-width: 100%;
                aspect-ratio: 1 / 1;
                height: auto;
            }
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
            
            /* YouTube Lazy Load - Mobile */
            .youtube-lazy-wrapper {
                position: relative;
                width: 100%;
                max-width: 100%;
                aspect-ratio: 16 / 9;
                margin: 18px 0;
                border-radius: 12px;
                overflow: hidden;
                background: #000;
                cursor: pointer;
            }
            .youtube-thumbnail {
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background-size: contain;
                background-position: center;
                background-repeat: no-repeat;
                background-color: #000;
            }
            .mobile-context-cta {
                position: fixed;
                left: 10px;
                right: 10px;
                bottom: 62px;
                z-index: 49;
                display: block;
                padding: 11px 14px;
                border-radius: 12px;
                text-align: center;
                background: #0f2238;
                color: #f4f8ff;
                border: 1px solid rgba(201, 224, 255, 0.28);
                text-decoration: none;
                font-size: 13px;
                font-weight: 800;
                box-shadow: 0 12px 18px rgba(6, 12, 22, 0.3);
            }
            .youtube-play-button {
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                width: 68px;
                height: 48px;
                transition: transform 200ms ease;
            }
            .youtube-lazy-wrapper:hover .youtube-play-button {
                transform: translate(-50%, -50%) scale(1.1);
            }

            .blog-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 1060px) and (min-width: 761px) {
            .blog-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }
        
        /* YouTube Lazy Load Styles - Desktop & Mobile */
        .youtube-lazy-wrapper {
            position: relative;
            width: 100%;
            max-width: 100%;
            aspect-ratio: 16 / 9;
            margin: 18px 0;
            border-radius: 12px;
            overflow: hidden;
            background: #000;
            cursor: pointer;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
        }
        .youtube-thumbnail {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-size: contain;
            background-position: center;
            background-repeat: no-repeat;
            background-color: #000;
        }
        .youtube-play-button {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 68px;
            height: 48px;
            transition: transform 200ms ease;
            opacity: 0.9;
        }
        .youtube-lazy-wrapper:hover .youtube-play-button {
            transform: translate(-50%, -50%) scale(1.1);
            opacity: 1;
        }
        .youtube-lazy-wrapper iframe {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border: 0;
        }

        /* Global dark theme */
        :root {
            --bg-ink: #070d16;
            --bg-deep: #0d1a2a;
            --bg-soft: #0a1422;
            --text: #e6edf8;
            --text-soft: #9db0c9;
            --line: rgba(155, 181, 214, 0.18);
            --card-shadow: 0 16px 34px rgba(0, 0, 0, 0.42);
        }

        body {
            background:
                radial-gradient(1400px 420px at 10% -5%, #17304a 0%, transparent 60%),
                radial-gradient(1200px 360px at 95% 0%, #143a33 0%, transparent 58%),
                linear-gradient(180deg, #040a12 0 220px, #0a1422 220px 100%);
            color: var(--text);
        }

        .panel,
        .card,
        .compare-table,
        .faq-panel details,
        .tier-card,
        .trust-box {
            background: #0f1b2b;
            border-color: var(--line);
            box-shadow: var(--card-shadow);
        }

        .trust-box {
            background: #122238;
            border-color: rgba(164, 192, 228, 0.3);
            color: #e6f0ff;
        }

        .trust-box[style*="background-color: transparent"] {
            background: #122238 !important;
        }

        .hero {
            background: linear-gradient(135deg, #0f1b2b 0%, #0d1726 100%);
            border-color: var(--line);
        }

        .hero p {
            color: var(--text-soft);
        }

        .muted,
        .card-copy,
        .compare-value,
        .faq-panel .muted,
        .hint,
        .pagination-info {
            color: var(--text-soft);
        }

        .section-title,
        .card h3,
        .faq-panel summary,
        .compare-label,
        .brand-sub {
            color: #eaf1fc;
        }

        .compare-label {
            background: #122238;
            border-right: 1px solid var(--line);
        }

        .card img,
        .home-most-loved .card img,
        .grid .card img,
        .product-hero-media {
            background: #0b1625;
        }

        .chip {
            background: rgba(12, 26, 42, 0.9);
            border-color: rgba(164, 192, 228, 0.32);
            color: #dce9fa;
        }

        .goal-links a {
            background: #122238;
            border-color: #28415f;
            color: #dce9fa;
        }

        .pagination-link {
            background: #122238;
            border-color: #2a4362;
            color: #dce9fa;
        }

        .pagination-link:hover {
            border-color: #4a6b94;
        }

        footer {
            color: #9bb0cb;
            border-top-color: rgba(142, 172, 209, 0.2);
            background: #07101d;
        }

        footer a {
            color: #d2e3fa;
        }

        /* Dark theme polish */
        .topbar {
            background:
                linear-gradient(180deg, rgba(4, 11, 20, 0.98) 0%, rgba(4, 11, 20, 0.92) 100%),
                radial-gradient(1200px 300px at 10% 0%, rgba(36, 72, 120, 0.18) 0%, transparent 70%);
            border-bottom: 1px solid rgba(156, 185, 224, 0.2);
        }

        nav a,
        .nav-current {
            font-size: 13px;
            letter-spacing: 0.07em;
        }

        nav a {
            color: rgba(224, 236, 252, 0.92);
        }

        .brand-sub {
            color: rgba(202, 220, 246, 0.9);
        }

        .panel {
            background: linear-gradient(180deg, #0f1b2b 0%, #0d1726 100%);
        }

        .card {
            background: linear-gradient(180deg, #122033 0%, #0f1a2b 100%);
            border-color: rgba(155, 181, 214, 0.22);
        }

        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 16px 30px rgba(0, 0, 0, 0.45);
        }

        .badge {
            color: #dce9fa;
            background: linear-gradient(120deg, rgba(48, 93, 148, 0.42) 0%, rgba(34, 112, 94, 0.38) 100%);
            border: 1px solid rgba(144, 178, 218, 0.28);
        }

        .compare-row {
            border-bottom: 1px solid rgba(150, 178, 212, 0.16);
        }

        .compare-value a,
        .muted a {
            color: #c8dcfb;
            text-underline-offset: 2px;
        }

        .faq-panel details {
            background: #111f32;
        }

        .faq-panel summary {
            color: #e7f0fe;
        }

        .notice {
            background: linear-gradient(180deg, #122034 0%, #0f1a2b 100%);
            border-color: rgba(145, 173, 208, 0.28);
            color: #d8e6fb;
        }

        .draft-preview-bar {
            border-color: rgba(210, 157, 79, 0.45);
            background: linear-gradient(180deg, #2a1f13 0%, #21180f 100%);
            color: #ffd9a6;
        }
    </style>
</head>
<body class="<?= $isHomePage ? 'page-home' : 'page-inner' ?>">
<a class="skip-link" href="#content">Skip to content</a>
<header class="topbar">
    <div class="brand-row">
        <a class="brand" href="<?= e(url('/')) ?>" aria-label="<?= e(APP_NAME) ?> home">
            <span class="brand-logo-wrap">
                <img class="brand-logo" src="<?= e(url('/assets/logo/128.png')) ?>" alt="<?= e(APP_NAME) ?> logo" width="114" height="114" loading="eager" fetchpriority="high" decoding="async">
            </span>
            <span class="brand-text">
                <span class="brand-title"><?= e(APP_NAME) ?></span>
                <span class="brand-sub">Telescope Accessories & Buyer's Picks</span>
            </span>
        </a>
    </div>
    <nav aria-label="Primary">
        <?php if ($isNavCurrent('/')): ?>
            <span class="nav-current" aria-current="page">Home</span>
        <?php else: ?>
            <a href="<?= e(url('/')) ?>">Home</a>
        <?php endif; ?>
        <a href="<?= e(url('/guides')) ?>" <?= $isNavCurrent('/guides') ? 'aria-current="page"' : '' ?>>Guides</a>
        <a href="<?= e(url('/blog')) ?>" <?= $isNavCurrent('/blog') ? 'aria-current="page"' : '' ?>>Blog</a>
        <a href="<?= e(url('/telescopes')) ?>" <?= $isNavCurrent('/telescopes') ? 'aria-current="page"' : '' ?>>Telescopes</a>
        <a href="<?= e(url('/accessories')) ?>" <?= $isNavCurrent('/accessories') ? 'aria-current="page"' : '' ?>>Accessories</a>
        <a href="<?= e(url('/about')) ?>" <?= $isNavCurrent('/about') ? 'aria-current="page"' : '' ?>>About</a>
        <a href="<?= e(url('/contact')) ?>" <?= $isNavCurrent('/contact') ? 'aria-current="page"' : '' ?>>Contact</a>
        <?php if ($showAdminNav): ?>
            <a href="<?= e(url('/enma/?tab=maintenance')) ?>">ENMA</a>
        <?php endif; ?>
    </nav>
</header>
<?php if (!empty($draftPreviewNotice)): ?>
    <div class="draft-preview-bar"><?= e((string) $draftPreviewNotice) ?></div>
<?php endif; ?>
<main id="content">
    <?php if ($showBreadcrumbs): ?>
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

/**
 * YouTube Lazy Load - Intersection Observer + Click to Load
 * Automatically loads iframe when user clicks or when element enters viewport
 */
(function () {
  var wrappers = document.querySelectorAll('.youtube-lazy-wrapper');
  
  if (!wrappers.length) return;
  
  // Function to load the actual iframe
  function loadIframe(wrapper) {
    var placeholder = wrapper.querySelector('.youtube-iframe-placeholder');
    if (!placeholder) return;
    
    var src = placeholder.getAttribute('data-src');
    if (!src) return;
    
    var iframe = document.createElement('iframe');
    iframe.src = src;
    iframe.setAttribute('loading', 'lazy');
    iframe.setAttribute('referrerpolicy', 'strict-origin-when-cross-origin');
    iframe.setAttribute('allow', 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share');
    iframe.setAttribute('allowfullscreen', 'true');
    
    wrapper.innerHTML = '';
    wrapper.appendChild(iframe);
    wrapper.classList.add('loaded');
  }
  
  // Set up Intersection Observer for auto-loading when in viewport
  var observerOptions = {
    root: null,
    rootMargin: '200px',
    threshold: 0.1
  };
  
  var observer = new IntersectionObserver(function (entries) {
    entries.forEach(function (entry) {
      if (entry.isIntersecting) {
        loadIframe(entry.target);
        observer.unobserve(entry.target);
      }
    });
  }, observerOptions);
  
  wrappers.forEach(function (wrapper) {
    // Add click handler
    wrapper.addEventListener('click', function () {
      loadIframe(wrapper);
    });
    
    // Also observe for viewport entry
    observer.observe(wrapper);
  });
})();

(function () {
  var cta = document.getElementById('mobile-context-cta');
  if (!cta) return;

  var sections = [
    { id: 'section-telescopes', href: <?= json_encode(url('/best-beginner-telescopes')) ?>, label: 'Start with Beginner Telescopes' },
    { id: 'section-accessories', href: <?= json_encode(url('/best-telescope-accessories')) ?>, label: 'Upgrade with Accessories' },
    { id: 'section-guides', href: <?= json_encode(url('/guides')) ?>, label: 'Read Buying Guides' }
  ];

  var active = 0;
  var targets = sections
    .map(function (item, index) {
      var el = document.getElementById(item.id);
      return el ? { el: el, index: index } : null;
    })
    .filter(Boolean);

  function apply(index) {
    var next = sections[index] || sections[0];
    cta.href = next.href;
    cta.textContent = next.label;
  }

  apply(active);

  if (!('IntersectionObserver' in window) || !targets.length) return;

  var observer = new IntersectionObserver(function (entries) {
    entries.forEach(function (entry) {
      if (entry.isIntersecting) {
        var hit = targets.find(function (t) { return t.el === entry.target; });
        if (hit) {
          active = hit.index;
          apply(active);
        }
      }
    });
  }, { rootMargin: '-35% 0px -45% 0px', threshold: 0.2 });

  targets.forEach(function (target) { observer.observe(target.el); });
})();
</script>
</body>
</html>
