<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$guides = [
    'best-beginner-telescopes' => [
        'slug' => 'best-beginner-telescopes',
        'title' => 'Best Beginner Telescopes (2026) - Real Picks for First-Time Stargazers',
        'description' => 'A practical guide to choosing your first telescope: what matters, what to avoid, and which real models are easiest to start with.',
        'focus' => 'telescopes',
        'summary' => 'Practical first-telescope picks for beginners with no prior experience.',
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
        'summary' => 'High-impact upgrades that improve real observing sessions.',
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
        'summary' => 'Value-focused telescopes with real performance potential under $500.',
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

$now = gmdate('Y-m-d\TH:i:s+00:00');

foreach ($guides as $slug => $data) {
    $stmt = $pdo->prepare('SELECT id FROM posts WHERE slug = :slug');
    $stmt->execute([':slug' => $slug]);
    $existing = $stmt->fetch();

    $title = $data['title'];
    $summary = $data['summary'] ?? '';
    $featuredImage = match ($slug) {
        'best-beginner-telescopes' => '/assets/img/optimized_1.webp',
        'best-telescope-accessories' => '/assets/img/optimized_2.webp',
        'best-telescopes-under-500' => '/assets/img/optimized_3.webp',
        default => '/assets/img/product-placeholder.svg',
    };

    unset($data['title'], $data['summary']);
    $extraData = json_encode($data);

    if ($existing) {
        $stmt = $pdo->prepare(
            'UPDATE posts SET 
                title = :title, 
                excerpt = :excerpt, 
                featured_image = :featured_image,
                post_type = \'guide\', 
                status = \'published\', 
                extra_data = :extra_data, 
                updated_at = :updated_at 
            WHERE id = :id'
        );
        $stmt->execute([
            ':title' => $title,
            ':excerpt' => $summary,
            ':featured_image' => $featuredImage,
            ':extra_data' => $extraData,
            ':updated_at' => $now,
            ':id' => $existing['id']
        ]);
        echo "Updated guide: $slug\n";
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO posts (slug, title, excerpt, featured_image, post_type, status, extra_data, created_at, updated_at, published_at) 
             VALUES (:slug, :title, :excerpt, :featured_image, \'guide\', \'published\', :extra_data, :created_at, :updated_at, :published_at)'
        );
        $stmt->execute([
            ':slug' => $slug,
            ':title' => $title,
            ':excerpt' => $summary,
            ':featured_image' => $featuredImage,
            ':extra_data' => $extraData,
            ':created_at' => $now,
            ':updated_at' => $now,
            ':published_at' => $now
        ]);
        echo "Inserted guide: $slug\n";
    }
}
