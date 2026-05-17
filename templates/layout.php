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
$siteLogoImage = trim(site_setting_get($pdo, 'site_logo_image', '/assets/logo/128.png'));
$siteFaviconIco = trim(site_setting_get($pdo, 'site_favicon_ico', '/favicon.ico'));
$siteFaviconImage = trim(site_setting_get($pdo, 'site_favicon_image', '/assets/logo/32.png'));
$siteAppleTouchIcon = trim(site_setting_get($pdo, 'site_apple_touch_icon', '/apple-touch-icon.png'));
$siteLogoUrl = url($siteLogoImage !== '' ? $siteLogoImage : '/assets/logo/128.png');
$siteFaviconIcoUrl = url($siteFaviconIco !== '' ? $siteFaviconIco : '/favicon.ico');
$siteFaviconImageUrl = url($siteFaviconImage !== '' ? $siteFaviconImage : '/assets/logo/32.png');
$siteAppleTouchIconUrl = url($siteAppleTouchIcon !== '' ? $siteAppleTouchIcon : '/apple-touch-icon.png');
$showBreadcrumbs = false;
if (!empty($breadcrumbs) && is_array($breadcrumbs) && count($breadcrumbs) > 1) {
    $showBreadcrumbs = str_starts_with($navPath, '/blog')
        || str_starts_with($navPath, '/guides')
        || str_starts_with($navPath, '/reviews')
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
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, shrink-to-fit=no">
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
    <link rel="icon" href="<?= e($siteFaviconIcoUrl) ?>">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= e($siteFaviconImageUrl) ?>">
    <link rel="icon" type="image/png" sizes="192x192" href="<?= e($siteFaviconImageUrl) ?>">
    <link rel="apple-touch-icon" href="<?= e($siteAppleTouchIconUrl) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=Spectral:ital,wght@0,500;0,700;1,500&display=swap" rel="stylesheet">
    <?php foreach (($jsonLd ?? []) as $schema): ?>
        <script type="application/ld+json"><?= json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
    <?php endforeach; ?>
    <link rel="stylesheet" href="<?= e(url('/assets/css/site.css?v=' . (string) @filemtime(__DIR__ . '/../assets/css/site.css'))) ?>">
</head>
<body class="<?= $isHomePage ? 'page-home' : 'page-inner' ?>">
<a class="skip-link" href="#content">Skip to content</a>
<header class="topbar">
    <div class="brand-row">
        <a class="brand" href="<?= e(url('/')) ?>" aria-label="<?= e(APP_NAME) ?> home">
            <span class="brand-logo-wrap">
                <img class="brand-logo" src="<?= e($siteLogoUrl) ?>" alt="<?= e(APP_NAME) ?> logo" width="114" height="114" loading="eager" fetchpriority="high" decoding="async">
            </span>
            <span class="brand-text">
                <span class="brand-title"><?= e(APP_NAME) ?></span>
                <span class="brand-sub">Telescope Accessories & Buyer's Picks</span>
            </span>
        </a>
    </div>
    <button class="mobile-nav-toggle" id="mobile-nav-toggle" type="button" aria-expanded="false" aria-controls="primary-nav">Menu</button>
    <nav aria-label="Primary" id="primary-nav">
        <?php if ($isNavCurrent('/')): ?>
            <span class="nav-current" aria-current="page">Home</span>
        <?php else: ?>
            <a href="<?= e(url('/')) ?>">Home</a>
        <?php endif; ?>
        <a href="<?= e(url('/guides')) ?>" <?= $isNavCurrent('/guides') ? 'aria-current="page"' : '' ?>>Guides</a>
        <a href="<?= e(url('/telescopes')) ?>" <?= $isNavCurrent('/telescopes') ? 'aria-current="page"' : '' ?>>Telescopes</a>
        <a href="<?= e(url('/accessories')) ?>" <?= $isNavCurrent('/accessories') ? 'aria-current="page"' : '' ?>>Accessories</a>
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
        <nav aria-label="Breadcrumb" class="breadcrumb-nav">
            <?php foreach ($breadcrumbs as $idx => $crumb): ?>
                <?php if ($idx > 0): ?><span class="breadcrumb-sep"> / </span><?php endif; ?>
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
    <div class="footer-mobile-blurb">
        Beginner telescope guides and product picks.
    </div>
    <div class="footer-links">
        <a href="<?= e(url('/guides')) ?>">Guides</a> |
        <a href="<?= e(url('/reviews')) ?>">Reviews</a> |
        <a href="<?= e(url('/blog')) ?>">Posts</a> |
        <a href="<?= e(url('/about')) ?>">About</a> |
        <a href="<?= e(url('/contact')) ?>">Contact</a> |
        <a href="<?= e(url('/affiliate-disclosure')) ?>">Affiliate Disclosure</a> |
        <a href="<?= e(url('/privacy-policy')) ?>">Privacy Policy</a> |
        <a href="<?= e(url('/terms-of-use')) ?>">Terms of Use</a>
    </div>
</footer>
<script>
(function () {
  var toggle = document.getElementById('mobile-nav-toggle');
  var nav = document.getElementById('primary-nav');
  if (!toggle || !nav) return;

  toggle.addEventListener('click', function () {
    var open = nav.classList.toggle('is-open');
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
  });
})();

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
