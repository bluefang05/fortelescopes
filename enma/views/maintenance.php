<?php

declare(strict_types=1);

/**
 * ENMA View: Maintenance Panel
 */

if (!function_exists('enma_render_maintenance')) {
    function enma_render_maintenance(array $dbTables, bool $advancedEnabled): void
    {
        ?>
        <section class="box">
            <h2>Database Tables</h2>
            <table>
                <thead>
                    <tr>
                        <th>Table</th>
                        <th>Rows</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dbTables as $table): ?>
                        <tr>
                            <td><code><?= e($table['name']) ?></code></td>
                            <td><?= $table['rows'] >= 0 ? number_format($table['rows']) : 'Error' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>

        <section class="box">
            <h2>Maintenance Tasks</h2>
            <form method="post">
                <input type="hidden" name="action" value="maintenance_run">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <div class="toolbar">
                    <select name="task" style="max-width:280px;">
                        <option value="">Select a task...</option>
                        <option value="normalize_affiliate_urls">Normalize Affiliate URLs</option>
                        <option value="update_db_schema">Update DB Schema</option>
                        <option value="refresh_sync_labels">Refresh Sync Labels</option>
                        <option value="fix_product_images">Fix Product Images</option>
                    </select>
                    <button class="btn" type="submit">Run Task</button>
                </div>
            </form>
        </section>

        <?php if ($advancedEnabled): ?>
        <section class="box" style="border-left:4px solid #f59e0b;">
            <h2>Advanced Tasks</h2>
            <p class="muted" style="margin-bottom:14px;">Dangerous operations. Use with caution.</p>
            <form method="post">
                <input type="hidden" name="action" value="maintenance_advanced_run">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                
                <div class="toolbar">
                    <div class="field">
                        <label>Task</label>
                        <select name="task" required>
                            <option value="">Select...</option>
                            <option value="refresh_sync_cli">Refresh Sync (CLI)</option>
                            <option value="reseed_real_catalog">Reseed Real Catalog</option>
                            <option value="seed_more_products">Seed More Products</option>
                        </select>
                    </div>
                    <div class="field">
                        <label>Advanced Key</label>
                        <input type="password" name="advanced_key" required placeholder="Enter advanced key">
                    </div>
                </div>
                <div class="toolbar">
                    <div class="field" style="flex:1;">
                        <label>Confirm</label>
                        <input type="text" name="confirm_text" required placeholder="Type: RUN [TASK_NAME]">
                    </div>
                </div>
                <button class="btn" type="submit" style="background:#dc3545;">Run Advanced Task</button>
            </form>
        </section>
        <?php endif; ?>
        <?php
    }
}

if (!function_exists('enma_render_maintenance_log')) {
    function enma_render_maintenance_log(array $log): void
    {
        if ($log === []) {
            return;
        }
        ?>
        <section class="box">
            <h2>Task Log</h2>
            <pre style="background:#f4f6f8;padding:12px;border-radius:6px;overflow-x:auto;font-size:12px;line-height:1.5;"><?php foreach ($log as $line): ?><?= e($line) . "\n" ?><?php endforeach; ?></pre>
        </section>
        <?php
    }
}
