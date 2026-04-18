<?php

declare(strict_types=1);

/**
 * One-shot affiliate draft generator using Gemini.
 *
 * Env lookup order for API key:
 * 1) GEMINI_API_KEY
 * 2) GOOGLE_API_KEY
 *
 * Manual mode:
 * php scripts/generate_affiliate_post.php --topic="..." --keyword="..." --product="..." --category="..."
 *
 * One-click mode (auto gap/topic selection):
 * php scripts/generate_affiliate_post.php --auto=1
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Forbidden\n");
}

require_once __DIR__ . '/../includes/bootstrap.php';

try {
    main($argv);
} catch (Throwable $e) {
    fwrite(STDERR, '[ERROR] ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

function main(array $argv): void
{
    $opts = getopt('', ['topic::', 'keyword::', 'product::', 'category::', 'model::', 'auto::']);
    if (!is_array($opts)) {
        fail('Could not parse CLI options.');
    }

    $model = trim((string) ($opts['model'] ?? ''));
    if ($model === '') {
        $model = 'gemini-2.0-flash';
    }

    $autoRaw = trim((string) ($opts['auto'] ?? ''));
    $autoMode = $autoRaw !== '' && !in_array(strtolower($autoRaw), ['0', 'false', 'no'], true);

    $topic = trim((string) ($opts['topic'] ?? ''));
    $keyword = trim((string) ($opts['keyword'] ?? ''));
    $product = trim((string) ($opts['product'] ?? ''));
    $category = trim((string) ($opts['category'] ?? ''));

    global $pdo;
    if (!$pdo instanceof PDO) {
        fail('Database connection is not available.');
    }

    if ($autoMode || ($topic === '' && $keyword === '' && $product === '' && $category === '')) {
        info('Auto mode enabled. Selecting commercial content gap.');
        $auto = choose_auto_generation_brief($pdo);
        $topic = $auto['topic'];
        $keyword = $auto['keyword'];
        $product = $auto['product'];
        $category = $auto['category'];
    }

    $missing = [];
    if ($topic === '') {
        $missing[] = '--topic';
    }
    if ($keyword === '') {
        $missing[] = '--keyword';
    }
    if ($product === '') {
        $missing[] = '--product';
    }
    if ($category === '') {
        $missing[] = '--category';
    }
    if ($missing !== []) {
        fail('Missing required arguments: ' . implode(', ', $missing));
    }

    $apiKey = resolve_gemini_api_key();
    $siteContext = build_site_context_snapshot($pdo);

    info('Generating draft with Gemini model: ' . $model);
    info('Topic: ' . $topic);
    info('Keyword: ' . $keyword);
    info('Product: ' . $product);
    info('Category: ' . $category);

    $generationSource = 'gemini-cli';
    try {
        $prompt = build_generation_prompt($topic, $keyword, $product, $category, $siteContext);
        $rawText = call_gemini_generate_json($model, $apiKey, $prompt);
        $payload = decode_model_json_payload($rawText);
        $postData = normalize_generated_payload($payload);
        validate_generated_post($postData);
    } catch (Throwable $e) {
        info('Gemini generation unavailable: ' . $e->getMessage());
        info('Falling back to deterministic affiliate draft template.');
        $postData = build_fallback_post_payload($topic, $keyword, $product, $category);
        validate_generated_post($postData);
        $generationSource = 'fallback-template';
    }

    $insertResult = insert_generated_draft($pdo, $postData, $topic, $keyword, $product, $category, $model, $siteContext, $generationSource);
    info('Draft created successfully.');
    info('Draft ID: ' . (string) $insertResult['id']);
    info('Slug: ' . (string) $insertResult['slug']);
    info('Status: draft');
}

function choose_auto_generation_brief(PDO $pdo): array
{
    $candidates = [
        [
            'topic' => 'Best telescope for balcony viewing in light-polluted cities',
            'keyword' => 'best telescope for balcony viewing',
            'product' => 'Celestron NexStar 4SE',
            'category' => 'telescopes',
            'match_terms' => ['balcony', 'light-polluted', 'city viewing'],
        ],
        [
            'topic' => 'Best telescope eyepiece upgrade for sharper planetary views',
            'keyword' => 'best telescope eyepiece upgrade',
            'product' => 'SVBONY 8-24mm Zoom Eyepiece',
            'category' => 'accessories',
            'match_terms' => ['eyepiece upgrade', 'planetary views', 'zoom eyepiece'],
        ],
        [
            'topic' => 'Best tracking mount upgrade for beginner astrophotography',
            'keyword' => 'best tracking mount for beginner astrophotography',
            'product' => 'Sky-Watcher Star Adventurer 2i',
            'category' => 'accessories',
            'match_terms' => ['tracking mount', 'astrophotography', 'star adventurer'],
        ],
        [
            'topic' => 'Best telescope accessories for lunar and planetary observing nights',
            'keyword' => 'best telescope accessories for planetary observing',
            'product' => 'Celestron X-Cel LX Eyepiece',
            'category' => 'accessories',
            'match_terms' => ['planetary observing', 'lunar observing', 'accessories night'],
        ],
    ];

    $rows = $pdo->query('SELECT title, slug, excerpt FROM posts WHERE post_type = "post"')->fetchAll();
    $haystack = '';
    foreach ($rows as $row) {
        $haystack .= ' ' . mb_strtolower(trim((string) ($row['title'] ?? '')));
        $haystack .= ' ' . mb_strtolower(trim((string) ($row['slug'] ?? '')));
        $haystack .= ' ' . mb_strtolower(trim((string) ($row['excerpt'] ?? '')));
    }

    foreach ($candidates as $candidate) {
        $covered = false;
        foreach ($candidate['match_terms'] as $term) {
            if (str_contains($haystack, mb_strtolower((string) $term))) {
                $covered = true;
                break;
            }
        }
        if (!$covered) {
            return [
                'topic' => (string) $candidate['topic'],
                'keyword' => (string) $candidate['keyword'],
                'product' => (string) $candidate['product'],
                'category' => (string) $candidate['category'],
            ];
        }
    }

    $fallback = $candidates[0];
    return [
        'topic' => (string) $fallback['topic'],
        'keyword' => (string) $fallback['keyword'],
        'product' => (string) $fallback['product'],
        'category' => (string) $fallback['category'],
    ];
}

function build_site_context_snapshot(PDO $pdo): array
{
    $context = [
        'date' => gmdate('Y-m-d'),
        'sitemap_status' => 'unknown',
        'sitemap_sample_urls' => [],
        'top_categories' => [],
        'existing_post_slugs' => [],
    ];

    $sitemap = fetch_url_text('https://fortelescopes.com/sitemap.xml');
    if ($sitemap['ok']) {
        $context['sitemap_status'] = 'ok';
        $urls = parse_sitemap_urls((string) $sitemap['body']);
        $context['sitemap_sample_urls'] = array_slice($urls, 0, 25);
    } else {
        $context['sitemap_status'] = 'unavailable';
    }

    $catRows = $pdo->query(
        'SELECT category_slug, category_name, COUNT(*) AS total
         FROM products
         WHERE status = "published"
         GROUP BY category_slug, category_name
         ORDER BY total DESC, category_name ASC
         LIMIT 10'
    )->fetchAll();
    foreach ($catRows as $row) {
        $context['top_categories'][] = [
            'slug' => (string) ($row['category_slug'] ?? ''),
            'name' => (string) ($row['category_name'] ?? ''),
            'total_products' => (int) ($row['total'] ?? 0),
        ];
    }

    $postRows = $pdo->query(
        'SELECT slug
         FROM posts
         WHERE post_type = "post"
         ORDER BY id DESC
         LIMIT 60'
    )->fetchAll();
    foreach ($postRows as $row) {
        $slug = trim((string) ($row['slug'] ?? ''));
        if ($slug !== '') {
            $context['existing_post_slugs'][] = $slug;
        }
    }

    return $context;
}

function fetch_url_text(string $url): array
{
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'body' => '', 'error' => 'curl missing'];
    }

    $ch = curl_init($url);
    if ($ch === false) {
        return ['ok' => false, 'body' => '', 'error' => 'curl init failed'];
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_HTTPHEADER => ['User-Agent: FortelescopesDraftGenerator/1.0'],
    ]);
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if (!is_string($body) || $status < 200 || $status >= 300) {
        return ['ok' => false, 'body' => '', 'error' => $error !== '' ? $error : ('http ' . $status)];
    }

    return ['ok' => true, 'body' => $body, 'error' => ''];
}

function parse_sitemap_urls(string $xml): array
{
    $xml = trim($xml);
    if ($xml === '') {
        return [];
    }

    $internal = libxml_use_internal_errors(true);
    $doc = new DOMDocument('1.0', 'UTF-8');
    $ok = $doc->loadXML($xml, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);
    libxml_clear_errors();
    libxml_use_internal_errors($internal);
    if (!$ok) {
        return [];
    }

    $xpath = new DOMXPath($doc);
    $nodes = $xpath->query('//*[local-name()="url"]/*[local-name()="loc"]');
    $urls = [];
    foreach ($nodes as $node) {
        $url = trim((string) $node->textContent);
        if ($url !== '') {
            $urls[] = $url;
        }
    }

    return array_values(array_unique($urls));
}

function resolve_gemini_api_key(): string
{
    $primary = read_env_value('GEMINI_API_KEY');
    if ($primary !== '') {
        return $primary;
    }

    $fallback = read_env_value('GOOGLE_API_KEY');
    if ($fallback !== '') {
        return $fallback;
    }

    $localPrimary = read_env_file_value('GEMINI_API_KEY');
    if ($localPrimary !== '') {
        return $localPrimary;
    }

    $localFallback = read_env_file_value('GOOGLE_API_KEY');
    if ($localFallback !== '') {
        return $localFallback;
    }

    fail('Gemini API key not found. Set GEMINI_API_KEY (preferred) or GOOGLE_API_KEY in the environment.');
}

function read_env_value(string $key): string
{
    $value = getenv($key);
    if (is_string($value) && trim($value) !== '') {
        return trim($value);
    }

    if (isset($_ENV[$key]) && is_string($_ENV[$key]) && trim($_ENV[$key]) !== '') {
        return trim($_ENV[$key]);
    }

    if (isset($_SERVER[$key]) && is_string($_SERVER[$key]) && trim($_SERVER[$key]) !== '') {
        return trim($_SERVER[$key]);
    }

    return '';
}

function read_env_file_value(string $key): string
{
    $paths = [
        __DIR__ . '/../local_gemini_credentials.env',
        __DIR__ . '/../.env',
    ];

    foreach ($paths as $path) {
        if (!is_file($path) || !is_readable($path)) {
            continue;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines)) {
            continue;
        }

        foreach ($lines as $line) {
            $trimmed = trim((string) $line);
            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }
            $pos = strpos($trimmed, '=');
            if ($pos === false) {
                continue;
            }
            $name = trim(substr($trimmed, 0, $pos));
            if ($name !== $key) {
                continue;
            }
            $value = trim(substr($trimmed, $pos + 1));
            $value = trim($value, " \t\n\r\0\x0B\"'");
            if ($value !== '') {
                return $value;
            }
        }
    }

    return '';
}

function build_generation_prompt(string $topic, string $keyword, string $product, string $category, array $siteContext): string
{
    $siteJson = json_encode($siteContext, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if (!is_string($siteJson) || trim($siteJson) === '') {
        $siteJson = '{}';
    }

    return implode("\n", [
        'Act as a Senior SEO Content Strategist and CRO Expert for the astronomy niche.',
        'Current date context: April 11, 2026.',
        '',
        'You are writing for Fortelescopes.',
        'Generate a conversion-oriented affiliate article body and metadata that can be inserted as a CMS draft.',
        '',
        'Return ONLY valid JSON (no markdown, no code fences) with exact fields:',
        '{',
        '  "title": "...",',
        '  "slug": "...",',
        '  "excerpt": "...",',
        '  "meta_title": "...",',
        '  "meta_description": "...",',
        '  "content_html": "..."',
        '}',
        '',
        'Target brief:',
        '- Topic: ' . $topic,
        '- Target keyword: ' . $keyword,
        '- Main product anchor: ' . $product,
        '- Category: ' . $category,
        '- Affiliate tag required in Amazon links: fortelescopes-20',
        '',
        'Site context snapshot (trust this context, do not invent site coverage outside it):',
        $siteJson,
        '',
        'Critical output constraints:',
        '- content_html only (inner body content, no html/head/body wrappers).',
        '- Write in natural, human, trustworthy English.',
        '- At least 1500 words of useful content.',
        '- Start with a styled disclosure div that includes exactly this sentence:',
        '  "As an Amazon Associate, I earn from qualifying purchases."',
        '- Include conversion-focused sections: buying guidance, why we love it, pros and cons, who should buy, who should skip, FAQ, conclusion.',
        '- Include a comparison table for 3-5 picks with columns: Model, Aperture, Best For, Check Price.',
        '- Include at least one and at most two relevant YouTube embeds.',
        '- Include Amazon CTA buttons with visible yellow style using #FFD814 and links with ?tag=fortelescopes-20.',
        '- If unsure about ASIN, use INSERT_ASIN_HERE.',
        '- No markdown.',
    ]);
}

function build_fallback_post_payload(string $topic, string $keyword, string $product, string $category): array
{
    $title = $topic;
    $slug = slugify($topic);
    $excerpt = 'A structured Fortelescopes draft covering ' . $topic . ', with buying advice, comparison notes, FAQ, and affiliate CTAs for Amazon.';
    $metaTitle = mb_substr($topic . ' | Fortelescopes Buying Guide', 0, 67);
    $metaDescription = mb_substr('Learn how to choose ' . strtolower($topic) . ' with practical buyer guidance, comparison notes, pros and cons, and Amazon-ready next steps.', 0, 158);

    return [
        'title' => $title,
        'slug' => $slug,
        'excerpt' => $excerpt,
        'meta_title' => $metaTitle,
        'meta_description' => $metaDescription,
        'content_html' => build_fallback_article_html($topic, $keyword, $product, $category),
    ];
}

function build_fallback_article_html(string $topic, string $keyword, string $product, string $category): string
{
    $amazonUrl = 'https://www.amazon.com/dp/INSERT_ASIN_HERE?tag=fortelescopes-20';
    $secondaryAmazonUrl = 'https://www.amazon.com/dp/INSERT_ASIN_HERE?tag=fortelescopes-20';
    $youtubePrimary = 'https://www.youtube-nocookie.com/embed/INSERT_YOUTUBE_VIDEO_ID_HERE';
    $youtubeSecondary = 'https://www.youtube-nocookie.com/embed/INSERT_YOUTUBE_VIDEO_ID_HERE';
    $comparisonRows = [
        ['model' => $product, 'aperture' => 'Check listing', 'best_for' => 'Most readers starting with this shortlist'],
        ['model' => 'Alternative Pick A', 'aperture' => 'Check listing', 'best_for' => 'Budget-conscious buyers'],
        ['model' => 'Alternative Pick B', 'aperture' => 'Check listing', 'best_for' => 'Users who want easier transport'],
    ];

    $introParagraphs = [
        'Balcony observing in bright suburban and urban skies is a real use case, not a compromise. Most buyers do not need the biggest telescope possible; they need a setup that cools down quickly, fits limited space, handles light pollution reasonably well, and feels easy enough to use on a weeknight. That is why this draft focuses on practical buying decisions instead of fantasy scenarios.',
        'The target keyword for this post is "' . e($keyword) . '", but the real search intent is simple: people want to know what they can confidently buy without regretting size, setup complexity, weak mount stability, or wasted budget. The goal is to reduce friction, build trust, and move readers toward the most sensible Amazon click when they are ready.',
        'For Fortelescopes, this is the right commercial angle because readers in this segment are usually comparing compact telescope kits, tracking options, planetary performance, and accessory tradeoffs at the same time. They are not just browsing for inspiration. They are close to buying, but they need clarity.',
        'This fallback draft is designed to be editable inside the CMS, so any placeholders such as ASINs, aperture values, and YouTube video IDs can be replaced before publishing. The structure, conversion sections, CTA placement, and FAQ are already in place so the draft is still operational when the external model is unavailable.',
    ];

    $faqItems = [
        ['q' => 'Is a balcony telescope good enough for serious viewing?', 'a' => 'Yes, especially for the Moon, planets, double stars, and selected bright deep-sky objects. The real limit is usually light pollution, local heat turbulence, and mount stability, not simply the fact that you are observing from a balcony.'],
        ['q' => 'Should beginners prioritize aperture or ease of use?', 'a' => 'For this use case, ease of use matters more than raw aperture on paper. A telescope that is too bulky or annoying to set up will lose observing time and click-through confidence, even if its specifications look stronger in a spreadsheet.'],
        ['q' => 'Do I need extra accessories on day one?', 'a' => 'Not always. Many buyers are better off getting the main telescope right first, then adding one meaningful upgrade later such as a better eyepiece, a more stable tripod solution, or a simple power setup if the mount requires it.'],
    ];

    $html = [];
    $html[] = '<div style="background:#fff7d6;border:1px solid #f0d879;padding:14px 16px;border-radius:8px;font-size:15px;font-weight:700;margin-bottom:18px;">As an Amazon Associate, I earn from qualifying purchases.</div>';
    $html[] = '<p><strong>' . e($topic) . '</strong> is a high-intent buying topic because readers usually have a real observing constraint, a budget in mind, and a short list of products open in other tabs. They are not looking for theory. They want a recommendation they can trust, a fast explanation of what matters, and a clear next step.</p>';
    foreach ($introParagraphs as $paragraph) {
        $html[] = '<p>' . e($paragraph) . '</p>';
    }

    $html[] = '<h2>Quick Verdict</h2>';
    $html[] = '<p>If you want the short answer, start by comparing <strong>' . e($product) . '</strong> against one lighter, one cheaper, and one simpler alternative. The best option for this query is rarely the biggest scope. It is usually the one that matches your balcony space, setup patience, and typical observing targets.</p>';
    $html[] = '<p>That is why the draft below is structured around practical buying guidance, not just specification dumping. The intent is to help readers feel informed enough to click, not pressured into the wrong purchase.</p>';

    $html[] = '<h2>Comparison Table</h2>';
    $html[] = '<table style="width:100%;border-collapse:collapse;margin:16px 0;">';
    $html[] = '<thead><tr><th style="border:1px solid #d9d9d9;padding:10px;text-align:left;">Model</th><th style="border:1px solid #d9d9d9;padding:10px;text-align:left;">Aperture</th><th style="border:1px solid #d9d9d9;padding:10px;text-align:left;">Best For</th><th style="border:1px solid #d9d9d9;padding:10px;text-align:left;">Check Price</th></tr></thead>';
    $html[] = '<tbody>';
    foreach ($comparisonRows as $row) {
        $html[] = '<tr>';
        $html[] = '<td style="border:1px solid #d9d9d9;padding:10px;">' . e($row['model']) . '</td>';
        $html[] = '<td style="border:1px solid #d9d9d9;padding:10px;">' . e($row['aperture']) . '</td>';
        $html[] = '<td style="border:1px solid #d9d9d9;padding:10px;">' . e($row['best_for']) . '</td>';
        $html[] = '<td style="border:1px solid #d9d9d9;padding:10px;"><a href="' . e($amazonUrl) . '" style="background-color:#FFD814;border:1px solid #FCD200;color:#0F1111;padding:8px 14px;border-radius:4px;font-weight:bold;text-decoration:none;display:inline-block;box-shadow:0 2px 5px rgba(0,0,0,0.1);">Check Price</a></td>';
        $html[] = '</tr>';
    }
    $html[] = '</tbody></table>';

    $html[] = '<h2>How to Choose the Right Setup</h2>';
    $html[] = '<p>Buyers in this segment usually make one of three mistakes. First, they overvalue aperture without thinking about where the telescope will actually live. Second, they underestimate how much a shaky mount damages the experience. Third, they focus on product hype instead of usage frequency. A smaller setup that gets used twice a week is a better affiliate recommendation than a more impressive setup that stays indoors.</p>';
    $html[] = '<ul><li><strong>Space:</strong> Measure how much room you really have for the mount, chair, and movement around the balcony.</li><li><strong>Targets:</strong> Decide whether planets, the Moon, or brighter deep-sky objects are your main priority.</li><li><strong>Speed:</strong> Favor products that get from storage to first view fast enough for casual weeknight sessions.</li></ul>';
    $html[] = '<p>When a reader reaches this point in the article, they are often dealing with objections like “Will this be too much work?” or “Will city skies make this pointless?” The right answer is not to overpromise. It is to explain that realistic expectations and a sensible product match create a much better ownership experience.</p>';

    $html[] = '<h2>' . e($product) . ' Review</h2>';
    $html[] = '<p><strong>' . e($product) . '</strong> is the anchor recommendation in this draft because it fits the exact search intent behind <em>' . e($keyword) . '</em>. It gives readers a clear reference point: a telescope that feels serious enough to justify the investment, but still approachable enough for recurring use if the balcony is the main observing location.</p>';
    $html[] = '<h3>Why We Love It</h3>';
    $html[] = '<p>We love this kind of pick because it balances usability and performance. It gives readers a believable path from beginner frustration to repeated successful sessions. That balance is what often drives the click, because buyers are not just buying optics; they are buying confidence.</p>';
    $html[] = '<h3>Who Should Buy This?</h3>';
    $html[] = '<p>This recommendation is best for readers who want a meaningful upgrade over entry-level impulse buys and who care more about repeatable results than about chasing the biggest specifications for the money.</p>';
    $html[] = '<h3>Who Should Skip This?</h3>';
    $html[] = '<p>Skip it if portability is everything, if you only want a very low-cost starter setup, or if you know you will not tolerate any setup routine at all. In those cases, a lighter or simpler option may convert better and create fewer returns or regrets.</p>';
    $html[] = '<h3>Pros and Cons</h3>';
    $html[] = '<ul><li><strong>Pros:</strong> Better fit for serious beginners than many generic starter kits.</li><li><strong>Pros:</strong> Easier to recommend when readers want a setup they can grow into.</li><li><strong>Pros:</strong> Stronger buyer confidence because it feels like a real long-term purchase.</li><li><strong>Cons:</strong> May be too expensive for casual curiosity buyers.</li><li><strong>Cons:</strong> Still requires realistic expectations about local light pollution and balcony conditions.</li></ul>';
    $html[] = '<div style="position:relative;padding-bottom:56.25%;height:0;overflow:hidden;margin:16px 0;border-radius:10px;"><iframe src="' . e($youtubePrimary) . '" title="' . e($product) . ' video review" style="position:absolute;top:0;left:0;width:100%;height:100%;border:0;" loading="lazy" allowfullscreen></iframe></div>';
    $html[] = '<a href="' . e($amazonUrl) . '" style="background-color:#FFD814;border:1px solid #FCD200;color:#0F1111;padding:12px 24px;border-radius:4px;font-weight:bold;text-decoration:none;display:inline-block;margin-top:10px;box-shadow:0 2px 5px rgba(0,0,0,0.1);">Check Price on Amazon &rarr;</a>';

    $html[] = '<h2>Alternative Picks Worth Considering</h2>';
    $html[] = '<p>A strong affiliate article should not feel locked to a single recommendation. Readers trust the content more when they can see why a product wins, where it does not, and what alternatives make sense for different budgets or usage patterns. That is especially true in astronomy, where setup friction can be just as important as the hardware itself.</p>';
    $html[] = '<h3>Alternative Pick A</h3>';
    $html[] = '<p>This slot is ideal for a lower-cost recommendation that still aligns with the same commercial intent. Use it to capture readers who are interested but not fully committed to the price point of the main recommendation.</p>';
    $html[] = '<h3>Why We Love It</h3>';
    $html[] = '<p>Budget-oriented alternatives win when they remove purchase hesitation. They give readers permission to act now instead of postponing the decision for weeks.</p>';
    $html[] = '<ul><li><strong>Pros:</strong> Lower barrier to entry.</li><li><strong>Pros:</strong> Easier to justify for first-time buyers.</li><li><strong>Cons:</strong> May have more obvious compromises in stability or long-term satisfaction.</li></ul>';
    $html[] = '<a href="' . e($secondaryAmazonUrl) . '" style="background-color:#FFD814;border:1px solid #FCD200;color:#0F1111;padding:12px 24px;border-radius:4px;font-weight:bold;text-decoration:none;display:inline-block;margin-top:10px;box-shadow:0 2px 5px rgba(0,0,0,0.1);">Check Price on Amazon &rarr;</a>';

    $html[] = '<h2>Beginner Buying Guidance</h2>';
    $html[] = '<p>Beginners do not need perfect information. They need enough clarity to avoid the most expensive mistakes. The right decision framework is usually: choose the form factor you will actually use, confirm the mount and practicality, then buy the strongest product your real budget allows. This is why commercial content that is honest about tradeoffs tends to convert better than copy that tries to make every option sound amazing.</p>';
    $html[] = '<ol><li>Decide whether your main goal is lunar and planetary viewing or a broader all-around experience.</li><li>Check whether your balcony setup supports stable observing and safe movement around the tripod or mount.</li><li>Choose the product that feels sustainable for repeated use, not just exciting on paper.</li></ol>';
    $html[] = '<p>Soft urgency is justified when a reader has already narrowed the field and only needs the final nudge. That is why CTA placement works best after trust-building sections, not before them. When the article helps the buyer think clearly, the Amazon click feels like the next logical step.</p>';

    $html[] = '<h2>FAQ</h2>';
    foreach ($faqItems as $item) {
        $html[] = '<h3>' . e($item['q']) . '</h3>';
        $html[] = '<p>' . e($item['a']) . '</p>';
    }

    $html[] = '<h2>Conclusion</h2>';
    $html[] = '<p>This draft is built to do the hard part well: connect buyer intent to a recommendation framework that feels realistic, confident, and commercially useful. Readers searching for <strong>' . e($keyword) . '</strong> are usually close to action. Give them one best-fit recommendation, one budget fallback, and one clear reason to click today, and the post will do its job.</p>';
    $html[] = '<p>Before publishing, replace placeholder ASINs, aperture values, and YouTube IDs with verified data. The structure is already aligned with the Fortelescopes affiliate flow, so editorial cleanup should be fast.</p>';
    $html[] = '<div style="margin-top:18px;"><a href="' . e($amazonUrl) . '" style="background-color:#FFD814;border:1px solid #FCD200;color:#0F1111;padding:14px 28px;border-radius:4px;font-weight:bold;text-decoration:none;display:inline-block;box-shadow:0 2px 5px rgba(0,0,0,0.1);">See Today&apos;s Price on Amazon &rarr;</a></div>';

    return implode("\n", $html);
}

function call_gemini_generate_json(string $model, string $apiKey, string $prompt): string
{
    $endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent?key=' . rawurlencode($apiKey);
    $requestBody = [
        'contents' => [
            [
                'role' => 'user',
                'parts' => [['text' => $prompt]],
            ],
        ],
        'generationConfig' => [
            'temperature' => 0.45,
            'responseMimeType' => 'application/json',
        ],
    ];

    $payload = json_encode($requestBody, JSON_UNESCAPED_SLASHES);
    if (!is_string($payload) || $payload === '') {
        fail('Failed to encode Gemini request payload.');
    }

    if (!function_exists('curl_init')) {
        fail('cURL extension is required for Gemini API calls.');
    }

    $ch = curl_init($endpoint);
    if ($ch === false) {
        fail('Failed to initialize cURL.');
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json; charset=utf-8'],
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_TIMEOUT => 75,
        CURLOPT_CONNECTTIMEOUT => 15,
    ]);

    $response = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if (!is_string($response)) {
        fail('Gemini request failed' . ($curlError !== '' ? ': ' . $curlError : '.'));
    }

    if ($status < 200 || $status >= 300) {
        $preview = mb_substr(trim($response), 0, 800);
        fail('Gemini API returned HTTP ' . $status . '. Response preview: ' . $preview);
    }

    $json = json_decode($response, true);
    if (!is_array($json)) {
        fail('Gemini API returned non-JSON response.');
    }

    $text = extract_gemini_text_output($json);
    if ($text === '') {
        fail('Gemini response did not contain text output.');
    }

    return $text;
}

function extract_gemini_text_output(array $response): string
{
    $candidates = $response['candidates'] ?? null;
    if (!is_array($candidates) || $candidates === []) {
        return '';
    }

    $first = $candidates[0] ?? null;
    if (!is_array($first)) {
        return '';
    }

    $parts = $first['content']['parts'] ?? null;
    if (!is_array($parts)) {
        return '';
    }

    $chunks = [];
    foreach ($parts as $part) {
        if (is_array($part) && isset($part['text']) && is_string($part['text'])) {
            $chunks[] = $part['text'];
        }
    }

    return trim(implode("\n", $chunks));
}

function decode_model_json_payload(string $raw): array
{
    $candidate = trim($raw);
    if ($candidate === '') {
        fail('Model output was empty.');
    }

    $candidate = strip_code_fences($candidate);
    $decoded = json_decode($candidate, true);
    if (!is_array($decoded)) {
        fail('Model output was not valid JSON after cleanup.');
    }

    return $decoded;
}

function strip_code_fences(string $text): string
{
    $trimmed = trim($text);
    if (preg_match('/^```(?:json)?\s*([\s\S]*?)\s*```$/i', $trimmed, $m)) {
        return trim((string) ($m[1] ?? ''));
    }
    return $trimmed;
}

function normalize_generated_payload(array $payload): array
{
    $title = trim((string) ($payload['title'] ?? ''));
    $slugInput = trim((string) ($payload['slug'] ?? ''));
    $excerpt = trim((string) ($payload['excerpt'] ?? ''));
    $metaTitle = trim((string) ($payload['meta_title'] ?? ''));
    $metaDescription = trim((string) ($payload['meta_description'] ?? ''));
    $contentHtml = trim((string) ($payload['content_html'] ?? ''));

    if ($slugInput === '') {
        $slugInput = $title;
    }
    $normalizedSlug = slugify($slugInput);

    return [
        'title' => $title,
        'slug' => $normalizedSlug,
        'excerpt' => $excerpt,
        'meta_title' => $metaTitle,
        'meta_description' => $metaDescription,
        'content_html' => $contentHtml,
    ];
}

function validate_generated_post(array $post): void
{
    $errors = [];

    $title = (string) ($post['title'] ?? '');
    $slug = (string) ($post['slug'] ?? '');
    $excerpt = (string) ($post['excerpt'] ?? '');
    $metaTitle = (string) ($post['meta_title'] ?? '');
    $metaDescription = (string) ($post['meta_description'] ?? '');
    $contentHtml = (string) ($post['content_html'] ?? '');

    if ($title === '') {
        $errors[] = 'title must not be empty.';
    }
    if ($slug === '' || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
        $errors[] = 'slug must be lowercase kebab-case.';
    }
    if ($excerpt === '') {
        $errors[] = 'excerpt must not be empty.';
    }
    if (mb_strlen($metaTitle) < 35 || mb_strlen($metaTitle) > 70) {
        $errors[] = 'meta_title should be SEO-reasonable (35-70 chars).';
    }
    if (mb_strlen($metaDescription) < 110 || mb_strlen($metaDescription) > 180) {
        $errors[] = 'meta_description should be SEO-reasonable (110-180 chars).';
    }
    if ($contentHtml === '') {
        $errors[] = 'content_html must not be empty.';
    }
    if (stripos($contentHtml, '<html') !== false || stripos($contentHtml, '<body') !== false) {
        $errors[] = 'content_html must be inner article HTML only (no full-page HTML).';
    }
    if (preg_match('/^\s{0,3}#{1,6}\s+/m', $contentHtml)) {
        $errors[] = 'content_html appears to contain markdown headings.';
    }

    $errors = array_merge($errors, validate_content_html_with_dom($contentHtml));
    if ($errors !== []) {
        fail("Generated content validation failed:\n- " . implode("\n- ", $errors));
    }
}

function validate_content_html_with_dom(string $html): array
{
    $errors = [];
    $internal = libxml_use_internal_errors(true);
    $dom = new DOMDocument('1.0', 'UTF-8');
    $wrappedHtml = '<!doctype html><html><body><div id="article-root">' . $html . '</div></body></html>';
    $loaded = $dom->loadHTML($wrappedHtml, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);
    libxml_clear_errors();
    libxml_use_internal_errors($internal);
    if (!$loaded) {
        return ['content_html could not be parsed as valid HTML.'];
    }

    $xpath = new DOMXPath($dom);
    if ((int) $xpath->query('//script')->length > 0) {
        $errors[] = 'content_html contains forbidden <script> tags.';
    }
    if ((int) $xpath->query('//object|//embed')->length > 0) {
        $errors[] = 'content_html contains forbidden object/embed tags.';
    }

    $headingNodes = $xpath->query('//h2|//h3');
    $headingCount = (int) $headingNodes->length;
    if ($headingCount < 3) {
        $errors[] = 'content_html must include at least 3 H2/H3 headings.';
    }

    $plainText = trim(preg_replace('/\s+/', ' ', strip_tags($html)) ?? '');
    $wordCount = $plainText === '' ? 0 : count(preg_split('/\s+/', $plainText) ?: []);
    if ($wordCount < 1500) {
        $errors[] = 'content_html must contain at least 1500 words.';
    }

    $headingTexts = [];
    foreach ($headingNodes as $node) {
        $headingTexts[] = mb_strtolower(trim((string) $node->textContent));
    }
    $hasFaq = false;
    $hasPros = false;
    $hasCons = false;
    foreach ($headingTexts as $text) {
        if ($text === '') {
            continue;
        }
        if (str_contains($text, 'faq') || str_contains($text, 'frequently asked')) {
            $hasFaq = true;
        }
        if (str_contains($text, 'pros and cons')) {
            $hasPros = true;
            $hasCons = true;
        } else {
            if (preg_match('/\bpros?\b/', $text)) {
                $hasPros = true;
            }
            if (preg_match('/\bcons?\b/', $text)) {
                $hasCons = true;
            }
        }
    }
    if (!$hasFaq) {
        $errors[] = 'content_html must include a FAQ section.';
    }
    if (!($hasPros && $hasCons)) {
        $errors[] = 'content_html must include a Pros and Cons section.';
    }

    $hasDisclosure = str_contains($plainText, 'As an Amazon Associate, I earn from qualifying purchases.');
    if (!$hasDisclosure) {
        $errors[] = 'content_html must include the exact affiliate disclosure sentence.';
    }

    $amazonCtas = 0;
    $affiliateLinks = 0;
    $allLinks = $xpath->query('//a[@href]');
    foreach ($allLinks as $a) {
        $href = trim((string) $a->getAttribute('href'));
        $style = mb_strtolower(trim((string) $a->getAttribute('style')));
        if ($href === '') {
            continue;
        }
        if (preg_match('/^\s*javascript:/i', $href)) {
            $errors[] = 'content_html contains javascript: link URLs.';
            continue;
        }
        if (is_amazon_affiliate_link($href, 'fortelescopes-20')) {
            $affiliateLinks++;
            if (str_contains($style, '#ffd814')) {
                $amazonCtas++;
            }
        }
    }
    if ($affiliateLinks < 1) {
        $errors[] = 'content_html must contain at least one Amazon affiliate link with tag=fortelescopes-20.';
    }
    if ($amazonCtas < 1) {
        $errors[] = 'content_html should include at least one yellow Amazon CTA button style.';
    }

    $youtubeEmbeds = 0;
    $iframes = $xpath->query('//iframe[@src]');
    foreach ($iframes as $iframe) {
        $src = trim((string) $iframe->getAttribute('src'));
        if ($src === '') {
            continue;
        }
        if (preg_match('/^\s*javascript:/i', $src)) {
            $errors[] = 'content_html contains javascript: iframe src.';
            continue;
        }
        $host = strtolower((string) (parse_url($src, PHP_URL_HOST) ?? ''));
        $path = strtolower((string) (parse_url($src, PHP_URL_PATH) ?? ''));
        $isYoutube = in_array($host, ['www.youtube.com', 'youtube.com', 'www.youtube-nocookie.com', 'youtube-nocookie.com'], true)
            && str_contains($path, '/embed/');
        if (!$isYoutube) {
            $errors[] = 'content_html contains iframe embeds outside allowed YouTube embed URLs.';
            continue;
        }
        $youtubeEmbeds++;
    }
    if ($youtubeEmbeds < 1 || $youtubeEmbeds > 2) {
        $errors[] = 'content_html must contain at least 1 and at most 2 YouTube embeds.';
    }

    $tableNodes = $xpath->query('//table');
    if ((int) $tableNodes->length < 1) {
        $errors[] = 'content_html must include a comparison table.';
    }

    $elements = $xpath->query('//*');
    foreach ($elements as $el) {
        if (!$el instanceof DOMElement) {
            continue;
        }
        foreach ($el->attributes as $attr) {
            $name = strtolower((string) $attr->nodeName);
            $value = trim((string) $attr->nodeValue);
            if (str_starts_with($name, 'on')) {
                $isAllowedAnchorHover = $el->tagName === 'a' && in_array($name, ['onmouseover', 'onmouseout'], true);
                if ($isAllowedAnchorHover) {
                    continue;
                }
                $errors[] = 'content_html contains forbidden inline event handler attributes.';
                break 2;
            }
            if (($name === 'href' || $name === 'src') && preg_match('/^\s*javascript:/i', $value)) {
                $errors[] = 'content_html contains javascript: URLs.';
                break 2;
            }
        }
    }

    return array_values(array_unique($errors));
}

function is_amazon_affiliate_link(string $url, string $requiredTag): bool
{
    if (filter_var($url, FILTER_VALIDATE_URL) === false) {
        return false;
    }
    $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?? ''));
    if (!is_amazon_host($host)) {
        return false;
    }

    $query = (string) (parse_url($url, PHP_URL_QUERY) ?? '');
    if ($query === '') {
        return false;
    }

    parse_str($query, $params);
    $tag = trim((string) ($params['tag'] ?? ''));
    return strcasecmp($tag, $requiredTag) === 0;
}

function insert_generated_draft(
    PDO $pdo,
    array $post,
    string $topic,
    string $keyword,
    string $product,
    string $category,
    string $model,
    array $siteContext,
    string $generationSource
): array {
    $slug = unique_slug_for_posts($pdo, (string) ($post['slug'] ?? ''));
    $now = now_iso();

    $stmt = $pdo->prepare(
        'INSERT INTO posts (
            slug, title, excerpt, content_html, featured_image, post_type, status, meta_title, meta_description, extra_data,
            created_at, updated_at, published_at
         ) VALUES (
            :slug, :title, :excerpt, :content_html, :featured_image, :post_type, :status, :meta_title, :meta_description, :extra_data,
            :created_at, :updated_at, :published_at
         )'
    );

    $extraData = json_encode([
        'source' => $generationSource,
        'source_model' => $model,
        'generation_input' => [
            'topic' => $topic,
            'keyword' => $keyword,
            'product' => $product,
            'category' => $category,
            'auto_site_context' => $siteContext,
        ],
    ], JSON_UNESCAPED_SLASHES);
    if (!is_string($extraData) || $extraData === '') {
        $extraData = null;
    }

    $stmt->execute([
        ':slug' => $slug,
        ':title' => (string) $post['title'],
        ':excerpt' => (string) $post['excerpt'],
        ':content_html' => (string) $post['content_html'],
        ':featured_image' => '',
        ':post_type' => 'post',
        ':status' => 'draft',
        ':meta_title' => (string) $post['meta_title'],
        ':meta_description' => (string) $post['meta_description'],
        ':extra_data' => $extraData,
        ':created_at' => $now,
        ':updated_at' => $now,
        ':published_at' => null,
    ]);

    $id = (int) $pdo->lastInsertId();
    if ($id <= 0) {
        fail('Draft insert did not return a valid post id.');
    }

    return ['id' => $id, 'slug' => $slug];
}

function info(string $message): void
{
    fwrite(STDOUT, '[INFO] ' . $message . PHP_EOL);
}

function fail(string $message): void
{
    throw new RuntimeException($message);
}
