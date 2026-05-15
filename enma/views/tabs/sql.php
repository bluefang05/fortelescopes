<?php
$sqlPresetQueries = [
    'Recent products' => 'SELECT id, title, category_name, status, last_synced_at FROM products ORDER BY id DESC LIMIT 25',
    'Published posts' => 'SELECT id, title, post_type, status, updated_at FROM posts ORDER BY updated_at DESC LIMIT 25',
    'Unread messages' => 'SELECT id, name, email, subject, status, created_at FROM contact_messages WHERE status = "new" ORDER BY created_at DESC LIMIT 25',
    'Table sizes' => 'SELECT table_name, table_rows, ROUND((data_length + index_length) / 1024 / 1024, 2) AS size_mb FROM information_schema.tables WHERE table_schema = DATABASE() ORDER BY size_mb DESC',
    'Duplicate path audit' => 'SELECT pv.page_type, pv.page_slug, pv.path, SUM(pv.views) AS total_views, COUNT(*) AS records FROM page_views pv INNER JOIN (SELECT page_type, page_slug FROM page_views WHERE page_slug <> "" GROUP BY page_type, page_slug HAVING COUNT(DISTINCT path) > 1) d ON d.page_type = pv.page_type AND d.page_slug = pv.page_slug GROUP BY pv.page_type, pv.page_slug, pv.path ORDER BY pv.page_type, pv.page_slug, total_views DESC',
    'Clean traffic report' => 'SELECT page_type, page_slug, path, SUM(views) AS total_views, COUNT(*) AS records FROM page_views WHERE page_type IN ("home","blog","guides","guide","category","page","post","product") GROUP BY page_type, page_slug, path ORDER BY total_views DESC LIMIT 200',
    'Security/not_found report' => 'SELECT page_type, page_slug, path, SUM(views) AS total_views, COUNT(*) AS records FROM page_views WHERE page_type IN ("not_found","security","bot") GROUP BY page_type, page_slug, path ORDER BY total_views DESC LIMIT 200',
    'Broken asset report' => 'SELECT path, SUM(views) AS total_views, COUNT(*) AS records FROM page_views WHERE page_type IN ("not_found","security") AND (path LIKE "%.ico%" OR path LIKE "%.png%" OR path LIKE "%.jpg%" OR path LIKE "%.jpeg%" OR path LIKE "%.webp%" OR path LIKE "%.svg%" OR path LIKE "/%https://%") GROUP BY path ORDER BY total_views DESC LIMIT 200',
    'Product route mismatch' => 'SELECT page_type, page_slug, path, SUM(views) AS total_views, COUNT(*) AS records FROM page_views WHERE path LIKE "/product/%" AND page_type <> "product" GROUP BY page_type, page_slug, path ORDER BY total_views DESC LIMIT 200',
    'SEO cannibalization seed report' => 'SELECT slug, post_type, status, title, updated_at FROM posts WHERE slug IN ("best-beginner-telescopes-for-stargazing-in-2026-your-first-steps-to-the-cosmos","best-beginner-telescopes-for-exploring-the-night-sky","best-celestron-telescopes-for-beginners-in-2026-every-budget-covered","dobsonian-vs-refractor-which-beginner-telescope-should-you-buy-in-2026","telescopes-for-viewing-planets-and-the-moon-under-300","best-telescopes-for-planets-and-moon-2026-buyer-s-guide","best-telescopes-for-planets-and-moon-2024-buyer-s-guide","the-best-telescope-accessories-to-upgrade-your-stargazing-experience","best-smart-telescope-accessories-2026","zwo-seestar-s50-review-499-smart-telescope","zwo-seestar-vs-unistellar-vs-celestron-origin","seestar-s50-vs-dwarf-ii-3-best-smart-telescope-for-beginners","seestar-s50-vs-dwarf-ii-3-best-smart-telescope-for-beginners-2","stop-struggling-why-the-zwo-seestar-s50-is-the-easiest-telescope-for-beginners-in-2026","best-smart-computerized-telescopes-for-beginners","best-computerized-goto-telescopes-for-beginners-2026") ORDER BY slug ASC',
];
$sqlTextareaValue = $sqlConsoleQuery !== '' ? $sqlConsoleQuery : $sqlPresetQueries['Recent products'];
$sqlResultTsv = '';
if ($sqlConsoleResultColumns !== []) {
    $tsvRows = [implode("\t", array_map('strval', $sqlConsoleResultColumns))];
    foreach ($sqlConsoleResultRows as $row) {
        $cells = [];
        foreach ($sqlConsoleResultColumns as $column) {
            $cells[] = str_replace(["\r", "\n", "\t"], [' ', ' ', ' '], (string) ($row[$column] ?? ''));
        }
        $tsvRows[] = implode("\t", $cells);
    }
    $sqlResultTsv = implode("\n", $tsvRows);
}
?>

<section class="box">
    <h2>SQL Console</h2>
    <p class="muted" style="margin:0 0 10px;">Run read queries directly inside ENMA. Write queries require explicit confirmation; destructive statements are blocked.</p>
    <div class="ops-kpis">
        <div class="ops-kpi"><div class="k">Mode</div><div class="v">Guarded</div></div>
        <div class="ops-kpi"><div class="k">Max Rows</div><div class="v"><?= number_format((int) $sqlConsoleMaxRows) ?></div></div>
        <div class="ops-kpi"><div class="k">Last Runtime</div><div class="v"><?= $sqlConsoleDurationMs === null ? '-' : number_format((float) $sqlConsoleDurationMs, 2) . 'ms' ?></div></div>
        <div class="ops-kpi"><div class="k">Tables</div><div class="v"><?= number_format(count($sqlConsoleTables)) ?></div></div>
    </div>
</section>

<section class="box">
    <h2>Run Query</h2>
    <?php if ($sqlConsoleMessage !== ''): ?>
        <div class="ok"><?= e($sqlConsoleMessage) ?></div>
    <?php endif; ?>
    <?php if ($sqlConsoleError !== ''): ?>
        <div class="error"><?= e($sqlConsoleError) ?></div>
    <?php endif; ?>

    <div class="ops-nav" style="margin-bottom:10px;">
        <?php foreach ($sqlPresetQueries as $label => $query): ?>
            <button class="ops-link sql-preset" type="button" data-sql="<?= e($query) ?>"><?= e($label) ?></button>
        <?php endforeach; ?>
    </div>
    <div class="toolbar" style="margin:0 0 10px;align-items:end;gap:10px;flex-wrap:wrap;">
        <div class="field" style="min-width:260px;">
            <label for="sql_table_quick_select">Quick table query</label>
            <select id="sql_table_quick_select">
                <option value="">Choose table to build SELECT *</option>
                <?php foreach ($sqlConsoleTables as $table): ?>
                    <?php $quickTableName = (string) ($table['table_name'] ?? ''); ?>
                    <option value="<?= e($quickTableName) ?>" <?= $sqlConsoleSelectedTable === $quickTableName ? 'selected' : '' ?>>
                        <?= e($quickTableName) ?><?= isset($table['table_rows']) ? ' (' . number_format((int) $table['table_rows']) . ')' : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button class="btn" type="button" id="sql_apply_select_all">Use SELECT * for table</button>
    </div>

    <form method="post">
        <input type="hidden" name="action" value="sql_console_run">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <label for="sql_query">SQL</label>
        <textarea id="sql_query" name="sql_query" rows="10" spellcheck="false" style="font-family:Consolas,monospace;"><?= e($sqlTextareaValue) ?></textarea>
        <textarea id="sql_query_copy_source" class="copy-source" readonly><?= e($sqlTextareaValue) ?></textarea>
        <textarea id="sql_result_copy_source" class="copy-source" readonly><?= e($sqlResultTsv) ?></textarea>

        <div class="toolbar" style="align-items:end;margin-top:10px;">
            <div class="field">
                <label for="max_rows">Max returned rows</label>
                <input id="max_rows" type="number" min="1" max="1000" name="max_rows" value="<?= (int) $sqlConsoleMaxRows ?>">
            </div>
            <label style="display:flex;gap:8px;align-items:center;margin:0;">
                <input type="checkbox" name="allow_write" value="1" <?= $sqlConsoleAllowWrite ? 'checked' : '' ?> style="width:auto;">
                Enable write queries
            </label>
            <label style="display:flex;gap:8px;align-items:center;margin:0;">
                <input type="checkbox" name="confirm_write" value="1" <?= $sqlConsoleConfirmWrite ? 'checked' : '' ?> style="width:auto;">
                I understand this can change data
            </label>
            <label style="display:flex;gap:8px;align-items:center;margin:0;">
                <input type="checkbox" name="batch_mode" value="1" <?= !empty($sqlConsoleBatchMode) ? 'checked' : '' ?> style="width:auto;">
                Enable batch mode (transactions)
            </label>
            <button class="btn" type="submit">Run SQL</button>
            <button class="btn btn-copy" type="button" data-copy-target="sql_query_copy_source" data-copy-status="sql_console_copy_status">Copy Query</button>
            <?php if ($sqlResultTsv !== ''): ?>
                <button class="btn btn-copy" type="button" data-copy-target="sql_result_copy_source" data-copy-status="sql_console_copy_status">Copy Results TSV</button>
            <?php endif; ?>
            <span id="sql_console_copy_status" class="copy-status"></span>
        </div>
    </form>
</section>

<?php if (!empty($sqlConsoleBatchLog)): ?>
<section class="box">
    <h2>Batch Steps</h2>
    <ul style="margin:0;padding-left:18px;">
        <?php foreach ($sqlConsoleBatchLog as $line): ?>
            <li><code><?= e((string) $line) ?></code></li>
        <?php endforeach; ?>
    </ul>
</section>
<?php endif; ?>

<section class="box">
    <h2>Schema Browser</h2>
    <form method="get" class="toolbar">
        <input type="hidden" name="tab" value="sql">
        <div class="field">
            <label for="sql_table">Table</label>
            <select id="sql_table" name="sql_table">
                <option value="">Choose table</option>
                <?php foreach ($sqlConsoleTables as $table): ?>
                    <?php $tableName = (string) ($table['table_name'] ?? ''); ?>
                    <option value="<?= e($tableName) ?>" <?= $sqlConsoleSelectedTable === $tableName ? 'selected' : '' ?>>
                        <?= e($tableName) ?><?= isset($table['table_rows']) ? ' (' . number_format((int) $table['table_rows']) . ')' : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button class="btn" type="submit">Inspect</button>
    </form>
    <?php if ($sqlConsoleColumns !== []): ?>
        <table>
            <thead>
            <tr>
                <th>Column</th>
                <th>Type</th>
                <th>Null</th>
                <th>Key</th>
                <th>Default</th>
                <th>Extra</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($sqlConsoleColumns as $column): ?>
                <tr>
                    <td><?= e((string) ($column['column_name'] ?? '')) ?></td>
                    <td><code><?= e((string) ($column['column_type'] ?? '')) ?></code></td>
                    <td><?= e((string) ($column['is_nullable'] ?? '')) ?></td>
                    <td><?= e((string) ($column['column_key'] ?? '')) ?></td>
                    <td><?= e((string) ($column['column_default'] ?? '')) ?></td>
                    <td><?= e((string) ($column['extra'] ?? '')) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>

<?php if ($sqlConsoleResultColumns !== []): ?>
<section class="box">
    <div style="display:flex;gap:10px;align-items:center;justify-content:space-between;flex-wrap:wrap;">
        <h2 style="margin:0;">Results</h2>
        <div style="display:flex;gap:8px;align-items:center;">
            <button class="btn btn-copy" type="button" data-copy-target="sql_result_copy_source" data-copy-status="sql_results_copy_status">Copy Results</button>
            <span id="sql_results_copy_status" class="copy-status"></span>
        </div>
    </div>
    <div style="overflow:auto;">
        <table>
            <thead>
            <tr>
                <?php foreach ($sqlConsoleResultColumns as $column): ?>
                    <th><?= e((string) $column) ?></th>
                <?php endforeach; ?>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($sqlConsoleResultRows as $row): ?>
                <tr>
                    <?php foreach ($sqlConsoleResultColumns as $column): ?>
                        <td><code><?= e((string) ($row[$column] ?? '')) ?></code></td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php elseif ($sqlConsoleAffectedRows !== null): ?>
<section class="box">
    <h2>Write Result</h2>
    <div class="ok">Affected rows: <?= number_format((int) $sqlConsoleAffectedRows) ?></div>
</section>
<?php endif; ?>

<section class="box">
    <h2>Recent SQL History</h2>
    <?php if ($sqlConsoleHistory === []): ?>
        <div class="empty">No SQL history yet.</div>
    <?php else: ?>
        <table>
            <thead>
            <tr>
                <th>When</th>
                <th>User</th>
                <th>Kind</th>
                <th>Status</th>
                <th>Rows</th>
                <th>Runtime</th>
                <th>Query</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($sqlConsoleHistory as $entry): ?>
                <tr>
                    <td><?= e((string) ($entry['created_at'] ?? '')) ?></td>
                    <td><?= e((string) ($entry['admin_username'] ?? '')) ?></td>
                    <td><?= e((string) ($entry['query_kind'] ?? '')) ?></td>
                    <td><?= e((string) ($entry['status'] ?? '')) ?></td>
                    <td><?= number_format((int) ($entry['row_count'] ?? 0)) ?></td>
                    <td><?= number_format((float) ($entry['duration_ms'] ?? 0), 2) ?>ms</td>
                    <td>
                        <code><?= e(mb_substr((string) ($entry['query_text'] ?? ''), 0, 240)) ?></code>
                        <?php if (!empty($entry['error_message'])): ?>
                            <div class="error" style="margin-top:6px;"><?= e((string) $entry['error_message']) ?></div>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>

<script>
(function () {
  var textarea = document.getElementById('sql_query');
  var tableQuickSelect = document.getElementById('sql_table_quick_select');
  var applySelectAllButton = document.getElementById('sql_apply_select_all');
  if (!textarea) return;

  function tableSelectQuery(tableName) {
    if (!tableName || !/^[A-Za-z0-9_]+$/.test(tableName)) return '';
    return 'SELECT * FROM `' + tableName + '`';
  }

  document.querySelectorAll('.sql-preset').forEach(function (button) {
    button.addEventListener('click', function () {
      textarea.value = button.getAttribute('data-sql') || '';
      textarea.focus();
    });
  });

  if (applySelectAllButton && tableQuickSelect) {
    applySelectAllButton.addEventListener('click', function () {
      var tableName = tableQuickSelect.value || '';
      var query = tableSelectQuery(tableName);
      if (!query) return;
      textarea.value = query;
      textarea.focus();
    });

    tableQuickSelect.addEventListener('change', function () {
      var tableName = tableQuickSelect.value || '';
      var query = tableSelectQuery(tableName);
      if (!query) return;
      textarea.value = query;
    });
  }
})();
</script>
