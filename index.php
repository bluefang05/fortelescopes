<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$scriptDir = rtrim($scriptDir, '/');

if ($scriptDir !== '' && $scriptDir !== '/' && strpos($path, $scriptDir) === 0) {
    $path = substr($path, strlen($scriptDir));
    $path = $path === '' ? '/' : $path;
}

$path = trim($path, '/');
$segments = $path === '' ? [] : explode('/', $path);
$requestPath = '/' . ltrim($path, '/');
if ($requestPath === '') {
    $requestPath = '/';
}

$pageTitle = APP_NAME;
$template = __DIR__ . '/templates/home.php';
$data = [];
$meta = site_meta_defaults();
$meta['robots'] = 'index,follow';
$canonicalPath = '/';
$jsonLd = [];
$breadcrumbs = [
    ['name' => 'Home', 'url' => absolute_url('/')],
];
$viewPageType = 'home';
$viewPageSlug = '';
$viewProductId = 0;
$jsonLd[] = json_ld_for_organization();
$jsonLd[] = json_ld_for_website();

if (count($segments) === 1 && $segments[0] === 'go') {
    $target = trim((string) ($_GET['u'] ?? ''));
    $productId = (int) ($_GET['pid'] ?? 0);
    $fromPath = trim((string) ($_GET['from'] ?? '/'));
    $target = amazon_affiliate_url($target);

    if ($target === '' || filter_var($target, FILTER_VALIDATE_URL) === false) {
        header('Location: ' . url('/'), true, 302);
        exit;
    }
    $host = (string) (parse_url($target, PHP_URL_HOST) ?? '');
    if (!is_amazon_host($host)) {
        header('Location: ' . url('/'), true, 302);
        exit;
    }

    try {
        track_outbound_click($pdo, $target, $productId, $fromPath);
    } catch (Throwable $e) {
        // Do not block redirect if click tracking fails.
    }

    header('Location: ' . $target, true, 302);
    exit;
}

if (count($segments) === 1 && $segments[0] === 'robots.txt') {
    header('Content-Type: text/plain; charset=UTF-8');
    echo "User-agent: *\n";
    echo "Allow: /\n";
    echo "Disallow: /admin/\n";
    echo "Disallow: /enma/\n";
    echo "Disallow: /dev/\n\n";
    echo 'Sitemap: ' . absolute_url('/sitemap.xml') . "\n";
    exit;
}

if (count($segments) === 1 && $segments[0] === 'sitemap.xml') {
    header('Content-Type: application/xml; charset=UTF-8');
    $nowIso = gmdate('c');
    $urls = [
        ['loc' => absolute_url('/'), 'lastmod' => $nowIso],
        ['loc' => absolute_url('/telescopes'), 'lastmod' => $nowIso],
        ['loc' => absolute_url('/accessories'), 'lastmod' => $nowIso],
        ['loc' => absolute_url('/best-beginner-telescopes'), 'lastmod' => $nowIso],
        ['loc' => absolute_url('/best-telescope-accessories'), 'lastmod' => $nowIso],
        ['loc' => absolute_url('/best-telescopes-under-500'), 'lastmod' => $nowIso],
        ['loc' => absolute_url('/guides'), 'lastmod' => $nowIso],
        ['loc' => absolute_url('/about'), 'lastmod' => $nowIso],
        ['loc' => absolute_url('/affiliate-disclosure'), 'lastmod' => $nowIso],
        ['loc' => absolute_url('/contact'), 'lastmod' => $nowIso],
        ['loc' => absolute_url('/privacy-policy'), 'lastmod' => $nowIso],
        ['loc' => absolute_url('/terms-of-use'), 'lastmod' => $nowIso],
    ];
    $cats = get_categories($pdo);
    foreach ($cats as $cat) {
        $urls[] = [
            'loc' => absolute_url('/category/' . $cat['category_slug']),
            'lastmod' => $nowIso,
        ];
    }
    foreach (get_recent_products($pdo, 5000) as $product) {
        $urls[] = [
            'loc' => absolute_url('/product/' . $product['slug']),
            'lastmod' => (string) ($product['updated_at'] ?? $product['last_synced_at'] ?? $nowIso),
        ];
    }

    echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
    echo "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
    $seen = [];
    foreach ($urls as $entry) {
        $loc = (string) ($entry['loc'] ?? '');
        if ($loc === '' || isset($seen[$loc])) {
            continue;
        }
        $seen[$loc] = true;
        $lastmod = (string) ($entry['lastmod'] ?? $nowIso);
        $lastmodTs = strtotime($lastmod);
        if ($lastmodTs !== false) {
            $lastmod = gmdate('c', $lastmodTs);
        } else {
            $lastmod = $nowIso;
        }
        echo "  <url>\n";
        echo "    <loc>" . e($loc) . "</loc>\n";
        echo "    <lastmod>" . e($lastmod) . "</lastmod>\n";
        echo "  </url>\n";
    }
    echo "</urlset>";
    exit;
}

if ($segments === []) {
    $data['products'] = get_recent_products($pdo, 18);
    $data['telescopes'] = get_products_by_category($pdo, 'telescopes', 6);
    $data['accessories'] = get_products_by_category($pdo, 'accessories', 6);
    $pageTitle = APP_NAME . ' | Telescopes and Astronomy Gear';
    $meta['description'] = 'Explore beginner telescopes, popular astronomy accessories, and practical stargazing guides.';
    $meta['image'] = absolute_url('/assets/logo/1024.png');
    $jsonLd[] = json_ld_for_itemlist($data['products'], 'Featured Telescope and Astronomy Products');
} elseif (count($segments) === 2 && $segments[0] === 'category') {
    $categorySlug = slugify($segments[1]);
    $products = get_products_by_category($pdo, $categorySlug, 12);
    $canonicalPath = '/category/' . $categorySlug;

    if ($products === []) {
        http_response_code(404);
        $template = __DIR__ . '/templates/not-found.php';
        $pageTitle = 'Category Not Found | ' . APP_NAME;
        $meta['description'] = 'Requested category does not exist.';
        $meta['robots'] = 'noindex,follow';
    } else {
        $data['products'] = $products;
        $data['categoryName'] = $products[0]['category_name'];
        $viewPageType = 'category';
        $viewPageSlug = $categorySlug;
        $pageTitle = $products[0]['category_name'] . ' | ' . APP_NAME;
        $template = __DIR__ . '/templates/category.php';
        $meta['description'] = 'Browse ' . $products[0]['category_name'] . ' recommendations and compare options before buying.';
        $meta['image'] = absolute_url('/assets/logo/1024.png');
        $jsonLd[] = json_ld_for_itemlist($products, $products[0]['category_name'] . ' recommendations');
        $breadcrumbs[] = ['name' => 'Categories', 'url' => absolute_url('/telescopes')];
        $breadcrumbs[] = ['name' => $products[0]['category_name'], 'url' => absolute_url($canonicalPath)];
    }
} elseif (count($segments) === 1 && in_array($segments[0], ['telescopes', 'accessories'], true)) {
    $categorySlug = $segments[0];
    $products = get_products_by_category($pdo, $categorySlug, 16);
    $canonicalPath = '/' . $categorySlug;

    if ($products === []) {
        http_response_code(404);
        $template = __DIR__ . '/templates/not-found.php';
        $pageTitle = 'Category Not Found | ' . APP_NAME;
        $meta['description'] = 'Requested category does not exist.';
        $meta['robots'] = 'noindex,follow';
    } else {
        $data['products'] = $products;
        $data['categoryName'] = $products[0]['category_name'];
        $viewPageType = 'category';
        $viewPageSlug = $categorySlug;
        $pageTitle = $products[0]['category_name'] . ' | ' . APP_NAME;
        $template = __DIR__ . '/templates/category.php';
        $meta['description'] = 'Compare ' . strtolower($products[0]['category_name']) . ' for astronomy and stargazing sessions.';
        $meta['image'] = absolute_url('/assets/logo/1024.png');
        $jsonLd[] = json_ld_for_itemlist($products, $products[0]['category_name']);
        $breadcrumbs[] = ['name' => $products[0]['category_name'], 'url' => absolute_url($canonicalPath)];
    }
} elseif (count($segments) === 2 && $segments[0] === 'product') {
    $productSlug = slugify($segments[1]);
    $product = find_product_by_slug($pdo, $productSlug);
    $canonicalPath = '/product/' . $productSlug;

    if ($product === null) {
        http_response_code(404);
        $template = __DIR__ . '/templates/not-found.php';
        $pageTitle = 'Product Not Found | ' . APP_NAME;
        $meta['description'] = 'Requested product does not exist.';
        $meta['robots'] = 'noindex,follow';
    } else {
        $data['product'] = $product;
        $viewPageType = 'product';
        $viewPageSlug = $productSlug;
        $viewProductId = (int) ($product['id'] ?? 0);
        $template = __DIR__ . '/templates/product.php';
        $pageTitle = $product['title'] . ' | ' . APP_NAME;
        $meta['description'] = substr($product['description'], 0, 150);
        if ($meta['description'] === '') {
            $meta['description'] = 'Detailed recommendation for ' . $product['title'] . '.';
        }
        if ($product['image_url'] !== '') {
            $meta['image'] = $product['image_url'];
        } else {
            $meta['image'] = absolute_url('/assets/logo/1024.png');
        }
        $meta['type'] = 'product';
        $jsonLd[] = json_ld_for_product($product);
        $breadcrumbs[] = ['name' => 'Products', 'url' => absolute_url('/telescopes')];
        $breadcrumbs[] = ['name' => $product['title'], 'url' => absolute_url($canonicalPath)];
    }
} elseif (
    (count($segments) === 2 && $segments[0] === 'guides') ||
    (count($segments) === 1 && in_array($segments[0], ['best-beginner-telescopes', 'best-telescope-accessories', 'best-telescopes-under-500'], true))
) {
    $guideSlug = count($segments) === 2 ? slugify($segments[1]) : slugify($segments[0]);
    $guides = [
        'best-beginner-telescopes' => [
            'slug' => 'best-beginner-telescopes',
            'title' => 'Best Beginner Telescopes (2026) - Real Picks for First-Time Stargazers',
            'description' => 'A practical guide to choosing your first telescope: what matters, what to avoid, and which real models are easiest to start with.',
            'focus' => 'telescopes',
            'intro' => 'If you are completely new to astronomy, the best beginner telescope is the one you can set up quickly, point confidently, and keep using week after week.',
            'framework' => [
                'Prioritize aperture and mount stability before extra accessories.',
                'Pick a telescope you can carry and set up without frustration.',
                'Start simple, then upgrade based on real observing experience.',
            ],
            'article_intro' => [
                'Most first-time buyers focus on magnification numbers, but that is rarely what makes a telescope enjoyable. For beginners, clear optics, a stable mount, and simple setup matter much more.',
                'This guide is built for complete newcomers looking for a practical telescope for beginners, with real models and realistic expectations.',
            ],
            'key_factors' => [
                [
                    'title' => 'Aperture first, not marketing magnification',
                    'points' => [
                        'A larger aperture gathers more light and improves detail on the Moon, planets, and brighter deep-sky objects.',
                        'For a first telescope, prioritize optical quality and usable views over inflated magnification claims.',
                    ],
                ],
                [
                    'title' => 'Mount type defines the learning curve',
                    'points' => [
                        'Alt-azimuth mounts are easier to learn and faster for casual sessions.',
                        'Equatorial mounts can track better, but take more setup and practice.',
                    ],
                ],
                [
                    'title' => 'Portability decides real usage',
                    'points' => [
                        'A telescope that is too heavy or awkward usually ends up unused.',
                        'If you plan to move between backyard, balcony, or travel, compact models are safer first picks.',
                    ],
                ],
                [
                    'title' => 'Ease of use beats feature overload',
                    'points' => [
                        'Your first telescope should be simple enough to use on night one.',
                        'You can always upgrade eyepieces and accessories after a few observing sessions.',
                    ],
                ],
            ],
            'mistakes' => [
                'Buying based on maximum magnification claims.',
                'Choosing unstable tripods that make focusing frustrating.',
                'Starting with too much complexity before learning the sky.',
                'Expecting astrophotography-style images from visual observing.',
            ],
            'best_for_map' => [
                'B0007UQNNQ' => 'Beginners who want a value entry point with room to learn.',
                'B07C8ZQF9Q' => 'Kids, gifts, and casual first-time backyard sessions.',
                'B000MLL6R8' => 'Beginners ready for stronger performance and a learning curve.',
                'B001TI9Y2M' => 'Travel-friendly sessions and quick setup nights.',
                'B000GUFOBO' => 'Users who want computerized object finding earlier.',
                'B002828HJE' => 'Portable Dobsonian fans who want strong value.',
                'B001UQ6E4K' => 'Compact tabletop use in limited spaces.',
                'B000GUFOC8' => 'Buyers planning a long-term premium setup.',
            ],
            'faq' => [
                ['q' => 'What is the best beginner telescope if I have no experience?', 'a' => 'Choose a model with straightforward setup, stable mount behavior, and reliable optics from a known brand. Ease of use is more important than advanced features at the start.'],
                ['q' => 'Should my first telescope be computerized?', 'a' => 'Only if you are comfortable with extra setup steps. Many beginners progress faster with simpler manual models first.'],
                ['q' => 'Can I start with a budget telescope and still enjoy astronomy?', 'a' => 'Yes. A realistic entry model can deliver excellent early sessions if expectations are practical and setup is consistent.'],
            ],
        ],
        'best-telescope-accessories' => [
            'slug' => 'best-telescope-accessories',
            'title' => 'Best Telescope Accessories That Actually Improve Your Viewing Experience',
            'description' => 'Actionable telescope upgrades for beginners to intermediate users: what to buy first, what to skip, and which accessories deliver real value.',
            'focus' => 'accessories',
            'intro' => 'Most observing frustrations come from workflow bottlenecks, not from the telescope tube itself. The right accessories can improve comfort, targeting speed, and consistency in every session.',
            'framework' => [
                'Prioritize high-impact essentials before collecting niche tools.',
                'Buy compatibility-first accessories that fit your focuser and observing style.',
                'Upgrade only after repeated field usage confirms a real limitation.',
            ],
            'article_intro' => [
                'The best telescope accessories do not need to be expensive. They need to solve real problems: better target acquisition, easier focusing, and more comfortable night sessions.',
                'This guide focuses on practical upgrades with realistic outcomes, not marketing claims.',
            ],
            'key_factors' => [
                [
                    'title' => 'Essential accessories first',
                    'points' => [
                        'A quality eyepiece, a practical finder, and a basic filter usually improve observing more than large accessory bundles.',
                        'If your sessions are short or frustrating, start by fixing comfort and usability before buying advanced add-ons.',
                    ],
                ],
                [
                    'title' => 'Upgrade only when a problem repeats',
                    'points' => [
                        'If the same issue appears across multiple nights, that is a valid upgrade trigger.',
                        'Avoid buying based on hype; buy based on actual observing pain points.',
                    ],
                ],
            ],
            'best_for_map' => [
                'B0007UQNV8' => 'Flexible focal lengths for users who want fewer eyepiece swaps.',
                'B01LZ6DDC2' => 'Starter lens variety on a tighter budget.',
                'B01K7M0JEM' => 'Quick smartphone mounting for basic Moon/planet captures.',
                'B0000635WI' => 'Portable power for longer sessions and mount reliability.',
                'B07JWDFMZL' => 'Higher magnification sessions for users refining planetary detail.',
                'B0048EZCF2' => 'Comfortable mid-range eyepiece upgrade for regular observers.',
                'B00D12P6Z2' => 'Faster object acquisition with simpler alignment behavior.',
                'B00006RH5I' => 'Moon glare control for cleaner, more comfortable lunar viewing.',
            ],
            'mistakes' => [
                'Buying large accessory kits with parts you will not use.',
                'Chasing extreme magnification before improving stability and tracking workflow.',
                'Ignoring compatibility with your focuser size and telescope type.',
                'Upgrading too many variables at once, making results hard to evaluate.',
            ],
            'upgrade_timing' => [
                'Upgrade eyepieces when your current view feels narrow, dim, or uncomfortable.',
                'Add filters when bright lunar sessions or light pollution limit useful contrast.',
                'Add adapters and power solutions when setup friction delays or shortens sessions.',
            ],
            'avoid_list' => [
                'Low-cost mega accessory bundles with inconsistent optical quality.',
                'Aggressive high-power eyepieces used without stable seeing conditions.',
                'Complex upgrades that cost more than the practical value they deliver at your current level.',
            ],
            'budget_notes' => [
                'Spend first on one quality eyepiece and one finder improvement.',
                'Use neutral language in recommendations: no fake stock, no fake discount urgency.',
                'Prefer gradual upgrades over one large, unfocused purchase.',
            ],
            'final_recommendation' => 'Start with one quality eyepiece plus one alignment helper. Run 3-5 real observing sessions, then add the next upgrade based on what still slows you down.',
            'cta_text' => 'Check current price on Amazon',
            'cta_note' => 'Check current price and availability before upgrading.',
            'comparisons' => [
                ['label' => 'Eyepiece upgrade', 'value' => 'High impact for image comfort and usability'],
                ['label' => 'Finder upgrade', 'value' => 'High impact for faster target acquisition'],
                ['label' => 'Filter upgrade', 'value' => 'Medium to high impact depending on your sky conditions'],
                ['label' => 'Phone adapter', 'value' => 'Optional but useful for simple sharing and documentation'],
            ],
            'faq' => [
                ['q' => 'Which accessory should I buy first?', 'a' => 'Most beginners benefit first from a phone adapter, a finder upgrade, or a practical eyepiece improvement.'],
                ['q' => 'Are accessory kits worth it?', 'a' => 'They can be useful if they match your telescope and viewing goals. Avoid kits with parts you will never use.'],
                ['q' => 'How often should accessories be upgraded?', 'a' => 'Upgrade only after repeated observing sessions reveal specific limitations.'],
            ],
        ],
        'best-telescopes-under-500' => [
            'slug' => 'best-telescopes-under-500',
            'title' => 'Best Telescopes Under $500 (2026) - What Is Actually Worth Buying',
            'description' => 'A practical under-$500 telescope guide focused on real value, mount stability, and beginner-friendly performance.',
            'focus' => 'telescopes',
            'intro' => 'If your budget is under $500, you can buy a real telescope that delivers meaningful planetary and deep-sky sessions without stepping into premium pricing.',
            'framework' => [
                'Prioritize optical quality and mount stability over marketing magnification.',
                'Choose a model that matches your learning style: manual simplicity or guided setup.',
                'Use budget headroom for one or two high-impact accessories, not random bundles.',
            ],
            'article_intro' => [
                'The best telescope under $500 is not always the most complex option. For many beginners, a stable manual design delivers better real-world observing than a feature-heavy but fragile setup.',
                'This guide compares realistic picks for first and second-year observers who want useful results, not inflated promises.',
            ],
            'key_factors' => [
                [
                    'title' => 'Aperture and stability win at this price',
                    'points' => [
                        'Around 130mm aperture is a strong value point for beginners in this budget tier.',
                        'A stable mount often improves your night more than a small bump in claimed power.',
                    ],
                ],
                [
                    'title' => 'Manual vs computerized depends on learning style',
                    'points' => [
                        'Computerized options can reduce object-finding friction but require alignment steps.',
                        'Manual tabletop or Dobsonian styles are often more direct and robust for pure visual use.',
                    ],
                ],
                [
                    'title' => 'Portability matters more than people expect',
                    'points' => [
                        'If transport and setup feel heavy, observing frequency drops.',
                        'Compact designs improve consistency, which improves results.',
                    ],
                ],
            ],
            'mistakes' => [
                'Buying by magnification claims instead of aperture and mount quality.',
                'Underestimating mount wobble and overestimating included accessories.',
                'Choosing a bulky setup that is rarely taken outside.',
                'Expecting premium astrophotography output from an entry-level visual rig.',
            ],
            'best_for_map' => [
                'B000GUFOBO' => 'Beginners who want computerized guidance and easier target finding.',
                'B002828HJE' => 'Value-focused users who prioritize image quality and portability.',
                'B000MLL6R8' => 'Learners who want stronger aperture performance with a classic setup.',
                'B001UQ6E4K' => 'Compact tabletop sessions with fast deployment.',
                'B0007UQNNQ' => 'Entry-level buyers starting with a lower budget ceiling.',
                'B000GUFOC8' => 'Premium-leaning path if budget can stretch in later phases.',
            ],
            'comparisons' => [
                ['label' => 'Celestron NexStar 130SLT', 'value' => 'Best for guided setup and easier object locating'],
                ['label' => 'Sky-Watcher Heritage 130P', 'value' => 'Best for optical value and manual simplicity'],
                ['label' => 'Celestron AstroMaster 130EQ', 'value' => 'Best for users willing to learn more setup mechanics'],
                ['label' => 'Orion StarBlast 4.5 Astro', 'value' => 'Best for compact tabletop observing sessions'],
            ],
            'budget_notes' => [
                'Use remaining budget for one quality eyepiece and a practical finder/filter upgrade.',
                'Avoid spending your entire budget on mount complexity if you prefer fast, visual sessions.',
                'Check current price on Amazon before purchase because pricing changes frequently.',
            ],
            'final_recommendation' => 'For most buyers under $500, prioritize a stable 130mm-class option with a workflow you can repeat weekly. Consistency beats complexity at this stage.',
            'cta_text' => 'Check current price on Amazon',
            'cta_note' => 'Prices change often; verify current price and availability before checkout.',
            'upgrade_timing' => [
                'Upgrade only after 3-5 sessions reveal a repeated bottleneck.',
                'Improve finder/eyepiece workflow before adding niche accessories.',
            ],
            'avoid_list' => [
                'Inflated magnification packages paired with small apertures.',
                'Low-stability tripods that sabotage focus and tracking.',
            ],
            'comparisons_title' => 'Quick comparison',
            'recommendations_title' => 'Top telescopes under $500',
            'mistakes_title' => 'Common under-$500 buying mistakes',
            'cta_hint' => 'Check availability on Amazon',
            'shortlist_note' => 'These real models are widely considered in the under-$500 bracket. Verify current pricing before purchase.',
            'faq_title' => 'FAQ',
            'final_title' => 'Final recommendation',
            'comparison_mode' => 'text',
            'final_mode' => 'generic',
            'keywords' => ['best telescope under 500', 'budget telescope', 'mid-range telescope'],
            'cta_secondary' => 'View on Amazon',
            'cta_primary' => 'Check current price on Amazon',
            'guide_note' => 'No fake stock claims. No inflated discount language. Practical buyer guidance only.',
            'section_labels' => ['intro', 'criteria', 'mistakes', 'picks', 'comparison', 'final'],
            'read_time' => '10 min read',
            'updated_label' => 'Updated 2026',
            'guide_style' => 'practical',
            'intent' => 'transactional+informational',
            'disclosure_reminder' => 'As an Amazon Associate, this site may earn from qualifying purchases.',
            'table_headers' => ['Model', 'Aperture', 'Mount style', 'Best for'],
            'faq' => [
                ['q' => 'Can an under-$500 telescope be genuinely good?', 'a' => 'Yes. With the right aperture and mount stability, this budget can deliver excellent beginner and intermediate visual sessions.'],
                ['q' => 'Should I choose GoTo or manual under $500?', 'a' => 'Choose GoTo if alignment steps do not bother you. Choose manual if you want faster setup and maximum optical value for the dollar.'],
                ['q' => 'What should I upgrade first after buying?', 'a' => 'Usually one quality eyepiece or finder improvement gives the fastest practical gain.'],
            ],
        ],
    ];
    $guides = apply_guides_overrides($guides);

    if (!isset($guides[$guideSlug])) {
        http_response_code(404);
        $template = __DIR__ . '/templates/not-found.php';
        $pageTitle = 'Guide Not Found | ' . APP_NAME;
        $meta['robots'] = 'noindex,follow';
    } else {
        $guide = $guides[$guideSlug];
        $viewPageType = 'guide';
        $viewPageSlug = $guideSlug;
        $guideProducts = get_products_by_category($pdo, $guide['focus'], 6);
        if ($guideProducts === []) {
            $guideProducts = get_recent_products($pdo, 6);
        }
        $data['guide'] = $guide;
        $data['guideProducts'] = $guideProducts;
        $template = __DIR__ . '/templates/guide.php';
        $pageTitle = $guide['title'] . ' | ' . APP_NAME;
        $meta['description'] = $guide['description'];
        $meta['image'] = absolute_url('/assets/logo/1024.png');
        $canonicalPath = '/' . $guideSlug;
        $jsonLd[] = json_ld_for_itemlist($guideProducts, $guide['title']);
        $jsonLd[] = json_ld_for_article($guide['title'], $guide['description'], absolute_url($canonicalPath), gmdate('c'));
        $breadcrumbs[] = ['name' => 'Guides', 'url' => absolute_url('/best-beginner-telescopes')];
        $breadcrumbs[] = ['name' => $guide['title'], 'url' => absolute_url($canonicalPath)];
        if (!empty($guide['faq'])) {
            $jsonLd[] = json_ld_for_faq($guide['faq']);
        }
    }
} elseif (count($segments) === 1 && $segments[0] === 'guides') {
    $viewPageType = 'guides';
    $viewPageSlug = 'guides-hub';
    $template = __DIR__ . '/templates/guides.php';
    $data['guides'] = [
        [
            'title' => 'Best Beginner Telescopes (2026)',
            'slug' => 'best-beginner-telescopes',
            'summary' => 'Practical first-telescope picks for beginners with no prior experience.',
            'image' => '/assets/img/optimized_1.webp',
        ],
        [
            'title' => 'Best Telescope Accessories',
            'slug' => 'best-telescope-accessories',
            'summary' => 'High-impact upgrades that improve real observing sessions.',
            'image' => '/assets/img/optimized_2.webp',
        ],
        [
            'title' => 'Best Telescopes Under $500',
            'slug' => 'best-telescopes-under-500',
            'summary' => 'Value-focused telescopes with real performance potential under $500.',
            'image' => '/assets/img/optimized_3.webp',
        ],
    ];
    $pageTitle = 'Astronomy Buying Guides | ' . APP_NAME;
    $meta['description'] = 'Browse telescope and astronomy buying guides covering beginner picks, accessories, and budget-friendly models.';
    $meta['image'] = absolute_url('/assets/logo/1024.png');
    $canonicalPath = '/guides';
    $breadcrumbs[] = ['name' => 'Guides', 'url' => absolute_url('/guides')];
} elseif (count($segments) === 1 && $segments[0] === 'about') {
    $viewPageType = 'page';
    $viewPageSlug = 'about';
    $template = __DIR__ . '/templates/about.php';
    $pageTitle = 'About | ' . APP_NAME;
    $meta['description'] = 'About Fortelescopes and our mission to help beginners choose astronomy gear.';
    $meta['image'] = absolute_url('/assets/logo/1024.png');
    $canonicalPath = '/about';
    $breadcrumbs[] = ['name' => 'About', 'url' => absolute_url($canonicalPath)];
} elseif (count($segments) === 1 && $segments[0] === 'affiliate-disclosure') {
    $viewPageType = 'page';
    $viewPageSlug = 'affiliate-disclosure';
    $template = __DIR__ . '/templates/legal-affiliate.php';
    $pageTitle = 'Affiliate Disclosure | ' . APP_NAME;
    $meta['description'] = 'Affiliate disclosure and monetization transparency for Fortelescopes.';
    $meta['image'] = absolute_url('/assets/logo/1024.png');
    $canonicalPath = '/affiliate-disclosure';
    $breadcrumbs[] = ['name' => 'Affiliate Disclosure', 'url' => absolute_url($canonicalPath)];
} elseif (count($segments) === 1 && $segments[0] === 'privacy-policy') {
    $viewPageType = 'page';
    $viewPageSlug = 'privacy-policy';
    $template = __DIR__ . '/templates/legal-privacy.php';
    $pageTitle = 'Privacy Policy | ' . APP_NAME;
    $meta['description'] = 'Privacy policy for visitors of Fortelescopes.';
    $meta['image'] = absolute_url('/assets/logo/1024.png');
    $canonicalPath = '/privacy-policy';
    $breadcrumbs[] = ['name' => 'Privacy Policy', 'url' => absolute_url($canonicalPath)];
} elseif (count($segments) === 1 && $segments[0] === 'terms-of-use') {
    $viewPageType = 'page';
    $viewPageSlug = 'terms-of-use';
    $template = __DIR__ . '/templates/legal-terms.php';
    $pageTitle = 'Terms of Use | ' . APP_NAME;
    $meta['description'] = 'Terms and conditions for using Fortelescopes.';
    $meta['image'] = absolute_url('/assets/logo/1024.png');
    $canonicalPath = '/terms-of-use';
    $breadcrumbs[] = ['name' => 'Terms of Use', 'url' => absolute_url($canonicalPath)];
} elseif (count($segments) === 1 && $segments[0] === 'contact') {
    $viewPageType = 'page';
    $viewPageSlug = 'contact';
    $template = __DIR__ . '/templates/contact.php';
    $pageTitle = 'Contact | ' . APP_NAME;
    $meta['description'] = 'Contact Fortelescopes for partnerships, corrections, or feedback.';
    $meta['image'] = absolute_url('/assets/logo/1024.png');
    $canonicalPath = '/contact';
    $breadcrumbs[] = ['name' => 'Contact', 'url' => absolute_url($canonicalPath)];
} else {
    http_response_code(404);
    $viewPageType = 'not_found';
    $viewPageSlug = trim((string) $path);
    $template = __DIR__ . '/templates/not-found.php';
    $pageTitle = 'Not Found | ' . APP_NAME;
    $meta['description'] = 'Requested page does not exist.';
    $meta['robots'] = 'noindex,follow';
}

if (count($breadcrumbs) > 1) {
    $jsonLd[] = json_ld_for_breadcrumb($breadcrumbs);
}

$categories = get_categories($pdo);
$canonicalUrl = absolute_url($canonicalPath);
try {
    $trackingPath = $viewPageType === 'not_found' ? $requestPath : $canonicalPath;
    track_page_view($pdo, $trackingPath, $viewPageType, $viewPageSlug, $viewProductId);
} catch (Throwable $e) {
    // Do not break frontend if analytics write fails.
}

require __DIR__ . '/templates/layout.php';
