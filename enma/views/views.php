<?php

declare(strict_types=1);

/**
 * ENMA View: Views Dashboard
 */

if (!function_exists('enma_render_views')) {
    function enma_render_views(array $viewsData, int $days): void
    {
        ?>
        <section class="box">
            <h2>Page Views Dashboard</h2>
            <form method="get" class="toolbar">
                <input type="hidden" name="tab" value="views">
                <div class="field">
                    <label>Days</label>
                    <select name="days">
                        <?php foreach ([7, 14, 30, 60, 90, 180] as $d): ?>
                            <option value="<?= $d ?>" <?= $d === $days ? 'selected' : '' ?>><?= $d ?> days</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button class="btn" type="submit">Update</button>
            </form>

            <?php if ($viewsData === []): ?>
                <div class="empty">No view data available.</div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Path</th>
                            <th>Type</th>
                            <th>Slug</th>
                            <th>Product ID</th>
                            <th>Views</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($viewsData['rows'] ?? [] as $row): ?>
                            <tr>
                                <td><?= e($row['view_date']) ?></td>
                                <td><code><?= e($row['path']) ?></code></td>
                                <td><?= e($row['page_type']) ?></td>
                                <td><?= $row['page_slug'] ? e($row['page_slug']) : '-' ?></td>
                                <td><?= $row['product_id'] ? (int) $row['product_id'] : '-' ?></td>
                                <td style="font-weight:700;"><?= (int) $row['total_views'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <?php if (!empty($viewsData['top_paths'])): ?>
                    <h3 style="margin-top:20px;">Top Paths</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Path</th>
                                <th>Total Views</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($viewsData['top_paths'], 0, 10) as $path => $views): ?>
                                <tr>
                                    <td><code><?= e($path) ?></code></td>
                                    <td style="font-weight:700;"><?= (int) $views ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            <?php endif; ?>
        </section>
        <?php
    }
}
