<?php

declare(strict_types=1);

/**
 * ENMA View: Overview Dashboard
 */

if (!function_exists('enma_render_overview')) {
    function enma_render_overview(array $stats): void
    {
        ?>
        <section class="box">
            <h2>Overview</h2>
            <div class="stats">
                <div class="stat">
                    <div class="stat-k">Products</div>
                    <div class="stat-v"><?= (int) ($stats['products'] ?? 0) ?></div>
                </div>
                <div class="stat">
                    <div class="stat-k">Categories</div>
                    <div class="stat-v"><?= (int) ($stats['categories'] ?? 0) ?></div>
                </div>
                <div class="stat">
                    <div class="stat-k">Missing Tags</div>
                    <div class="stat-v"><?= (int) ($stats['missing_tags'] ?? 0) ?></div>
                </div>
                <div class="stat">
                    <div class="stat-k">Missing Images</div>
                    <div class="stat-v"><?= (int) ($stats['missing_images'] ?? 0) ?></div>
                </div>
                <div class="stat">
                    <div class="stat-k">Views (30d)</div>
                    <div class="stat-v"><?= (int) ($stats['views_30d'] ?? 0) ?></div>
                </div>
                <div class="stat">
                    <div class="stat-k">Posts</div>
                    <div class="stat-v"><?= (int) ($stats['posts'] ?? 0) ?></div>
                </div>
            </div>
        </section>
        <?php
    }
}
