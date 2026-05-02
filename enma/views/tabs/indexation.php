<section class="box">
    <h2>Indexation Tracker</h2>
    <p class="muted" style="margin:0 0 10px;">Follow up every blog post/guide, mark index status, and schedule next checks.</p>
    <div class="ops-kpis">
        <div class="ops-kpi"><div class="k">Total URLs</div><div class="v"><?= number_format((int) ($indexationTotals['all_rows'] ?? 0)) ?></div></div>
        <div class="ops-kpi"><div class="k">Indexed</div><div class="v"><?= number_format((int) ($indexationTotals['indexed_rows'] ?? 0)) ?></div></div>
        <div class="ops-kpi"><div class="k">Pending</div><div class="v"><?= number_format((int) ($indexationTotals['pending_rows'] ?? 0)) ?></div></div>
        <div class="ops-kpi"><div class="k">Not Indexed</div><div class="v"><?= number_format((int) ($indexationTotals['not_indexed_rows'] ?? 0)) ?></div></div>
        <div class="ops-kpi"><div class="k">Excluded</div><div class="v"><?= number_format((int) ($indexationTotals['excluded_rows'] ?? 0)) ?></div></div>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
        <form method="post" style="margin:0;">
            <input type="hidden" name="action" value="maintenance_run">
            <input type="hidden" name="task" value="sync_post_indexation_tracker">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <button class="btn" type="submit">Sync Tracker Now</button>
        </form>
        <a class="ops-link" href="<?= e(url('/enma/?tab=maintenance#ops-routines')) ?>">Open maintenance routines</a>
    </div>
</section>

<section class="box">
    <h2>Checklist</h2>
    <form method="get" class="toolbar" style="margin-bottom:8px;">
        <input type="hidden" name="tab" value="indexation">
        <input type="hidden" name="indexation_page" value="1">
        <div class="field" style="max-width:220px;">
            <label>State</label>
            <select name="idx_state">
                <option value="all" <?= $indexationStateFilter === 'all' ? 'selected' : '' ?>>All</option>
                <option value="pending" <?= $indexationStateFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="indexed" <?= $indexationStateFilter === 'indexed' ? 'selected' : '' ?>>Indexed</option>
                <option value="not_indexed" <?= $indexationStateFilter === 'not_indexed' ? 'selected' : '' ?>>Not indexed</option>
                <option value="excluded" <?= $indexationStateFilter === 'excluded' ? 'selected' : '' ?>>Excluded</option>
            </select>
        </div>
        <div class="field" style="max-width:220px;">
            <label>Type</label>
            <select name="idx_type">
                <option value="all" <?= $indexationTypeFilter === 'all' ? 'selected' : '' ?>>All</option>
                <option value="post" <?= $indexationTypeFilter === 'post' ? 'selected' : '' ?>>Post</option>
                <option value="guide" <?= $indexationTypeFilter === 'guide' ? 'selected' : '' ?>>Guide</option>
            </select>
        </div>
        <div class="field" style="max-width:220px;">
            <label>Indexed</label>
            <select name="idx_indexed">
                <option value="all" <?= $indexationIndexedFilter === 'all' ? 'selected' : '' ?>>All</option>
                <option value="indexed" <?= $indexationIndexedFilter === 'indexed' ? 'selected' : '' ?>>Indexed</option>
                <option value="not_indexed" <?= $indexationIndexedFilter === 'not_indexed' ? 'selected' : '' ?>>Not Indexed</option>
            </select>
        </div>
        <div class="field" style="max-width:240px;">
            <label>Sort</label>
            <select name="idx_sort">
                <option value="priority" <?= $indexationSort === 'priority' ? 'selected' : '' ?>>Priority (default)</option>
                <option value="last_checked_oldest" <?= $indexationSort === 'last_checked_oldest' ? 'selected' : '' ?>>Last check oldest first</option>
                <option value="last_checked_newest" <?= $indexationSort === 'last_checked_newest' ? 'selected' : '' ?>>Last check newest first</option>
                <option value="title_asc" <?= $indexationSort === 'title_asc' ? 'selected' : '' ?>>Title A-Z</option>
                <option value="title_desc" <?= $indexationSort === 'title_desc' ? 'selected' : '' ?>>Title Z-A</option>
                <option value="recent_updates" <?= $indexationSort === 'recent_updates' ? 'selected' : '' ?>>Recently updated</option>
            </select>
        </div>
        <button class="btn" type="submit">Apply</button>
    </form>

    <?php if ($indexationRows === []): ?>
        <div class="empty">No rows for current filter.</div>
    <?php else: ?>
        <p class="muted">Showing <?= number_format(count($indexationRows)) ?> of <?= number_format($indexationTotal) ?> tracked URLs.</p>
        <table>
            <thead>
            <tr>
                <th>Post</th>
                <th>Type</th>
                <th>Post Status</th>
                <th>Index State</th>
                <th>Last Check</th>
                <th>Next Check</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($indexationRows as $row): ?>
                <?php
                $state = trim((string) ($row['index_state'] ?? 'pending'));
                $url = trim((string) ($row['canonical_url'] ?? ''));
                $searchTarget = $url !== '' ? $url : absolute_url(enma_post_public_path(['slug' => (string) ($row['slug'] ?? ''), 'post_type' => (string) ($row['post_type'] ?? 'post')]));
                $copyStatusId = 'index_copy_status_' . (int) ($row['post_id'] ?? 0);
                $gscResourceUrl = 'https://search.google.com/search-console?resource_id=' . rawurlencode('https://' . SITE_DOMAIN . '/');
                $gscInspectUrl = 'https://search.google.com/search-console/inspect?resource_id='
                    . rawurlencode('https://' . SITE_DOMAIN . '/')
                    . '&id=' . rawurlencode($searchTarget);
                ?>
                <tr>
                    <td>
                        <strong><?= e((string) ($row['title'] ?? 'Untitled')) ?></strong><br>
                        <a href="<?= e($searchTarget) ?>" target="_blank" rel="noopener noreferrer" style="font-size:12px;word-break:break-all;"><?= e($searchTarget) ?></a>
                    </td>
                    <td><?= e(strtoupper((string) ($row['post_type'] ?? 'post'))) ?></td>
                    <td><?= e((string) ($row['post_status'] ?? 'draft')) ?></td>
                    <td>
                        <span class="badge" style="background:#eef2f7;padding:2px 6px;border-radius:4px;font-size:11px;"><?= e($state) ?></span>
                        <?php if ((int) ($row['is_indexed'] ?? 0) === 1): ?>
                            <span class="badge" style="background:#dff6e6;color:#166534;padding:2px 6px;border-radius:4px;font-size:11px;">indexed</span>
                        <?php endif; ?>
                    </td>
                    <td><?= e((string) (($row['last_checked_at'] ?? '') !== '' ? $row['last_checked_at'] : 'Never')) ?></td>
                    <td><?= e((string) (($row['next_check_at'] ?? '') !== '' ? $row['next_check_at'] : '-')) ?></td>
                    <td>
                        <form method="post" style="display:grid;grid-template-columns:1fr;gap:6px;min-width:240px;">
                            <input type="hidden" name="action" value="indexation_update">
                            <input type="hidden" name="post_id" value="<?= (int) ($row['post_id'] ?? 0) ?>">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <select name="index_state" style="margin:0;">
                                <option value="pending" <?= $state === 'pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="indexed" <?= $state === 'indexed' ? 'selected' : '' ?>>Indexed</option>
                                <option value="not_indexed" <?= $state === 'not_indexed' ? 'selected' : '' ?>>Not indexed</option>
                                <option value="excluded" <?= $state === 'excluded' ? 'selected' : '' ?>>Excluded</option>
                            </select>
                            <input type="number" name="next_check_days" min="0" max="90" value="7" placeholder="Next check days" style="margin:0;">
                            <input type="text" name="notes" value="<?= e((string) ($row['notes'] ?? '')) ?>" placeholder="Notes (reason, action)" style="margin:0;">
                            <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;">
                                <button
                                    class="btn"
                                    type="submit"
                                    style="padding:6px 10px;font-size:12px;"
                                    onclick="this.form.querySelector('input[name=&quot;action&quot;]').value='indexation_update';"
                                >Save</button>
                                <a class="ops-link" href="<?= e($searchTarget) ?>" target="_blank" rel="noopener noreferrer">Open URL</a>
                                <button
                                    class="btn"
                                    type="button"
                                    style="padding:6px 10px;font-size:12px;"
                                    data-copy-text="<?= e($searchTarget) ?>"
                                    data-copy-status="<?= e($copyStatusId) ?>"
                                >Copy link</button>
                                <button
                                    class="btn"
                                    type="button"
                                    style="padding:6px 10px;font-size:12px;"
                                    data-copy-open-url="<?= e($gscInspectUrl) ?>"
                                    data-copy-text="<?= e($searchTarget) ?>"
                                    data-copy-status="<?= e($copyStatusId) ?>"
                                >Inspect in GSC</button>
                                <button
                                    class="btn"
                                    type="submit"
                                    style="padding:6px 10px;font-size:12px;"
                                    onclick="this.form.querySelector('input[name=&quot;action&quot;]').value='indexation_probe';"
                                >Heuristic Check</button>
                            </div>
                            <span id="<?= e($copyStatusId) ?>" class="copy-status"></span>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?= $indexationPagination ?>
    <?php endif; ?>
</section>
