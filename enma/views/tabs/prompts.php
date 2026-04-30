<section class="box">
    <h2>Prompts Workspace</h2>
    <p class="muted" style="margin:0 0 10px;">Todo lo de copy/paste para generacion de contenido en una sola vista, separado de Maintenance.</p>
    <p class="muted" style="margin:0 0 12px;">
        Usa los iconos
        <span class="help-icon" title="Que: resume para que sirve cada prompt. Por que: te ayuda a elegir rapido el prompt correcto sin repetir trabajo." aria-label="Ayuda: que y por que">?</span>
        para recordar que hace cada bloque y por que conviene usarlo.
    </p>
    <div class="copy-toolbar" style="margin-bottom:12px;">
        <h3 style="margin:0;">Blog / Guide / Product Prompts
            <span class="help-icon" title="Que: prompts para generar contenido nuevo con enfoque comercial. Por que: estandariza estructura y evita improvisar." aria-label="Ayuda de prompts de contenido">?</span>
        </h3>
        <div class="copy-actions">
            <button class="btn btn-copy" type="button" data-copy-target="blog_post_prompt_copy_source" data-copy-status="blog_post_prompt_copy_status">Copy Blog Post Prompt</button>
            <span id="blog_post_prompt_copy_status" class="copy-status"></span>
        </div>
        <div class="copy-actions">
            <button class="btn btn-copy" type="button" data-copy-target="guide_prompt_copy_source" data-copy-status="guide_prompt_copy_status">Copy Guide Prompt</button>
            <span id="guide_prompt_copy_status" class="copy-status"></span>
        </div>
        <div class="copy-actions">
            <button class="btn btn-copy" type="button" data-copy-target="product_single_review_prompt_copy_source" data-copy-status="product_single_review_prompt_copy_status">Copy Product Single Review Prompt</button>
            <span id="product_single_review_prompt_copy_status" class="copy-status"></span>
        </div>
        <div class="copy-actions">
            <button class="btn btn-copy" type="button" data-copy-target="product_versus_prompt_copy_source" data-copy-status="product_versus_prompt_copy_status">Copy Product Versus Prompt</button>
            <span id="product_versus_prompt_copy_status" class="copy-status"></span>
        </div>
        <div class="copy-actions">
            <button class="btn btn-copy" type="button" data-copy-target="best_for_y_prompt_copy_source" data-copy-status="best_for_y_prompt_copy_status">Copy Best X for Y Prompt</button>
            <span id="best_for_y_prompt_copy_status" class="copy-status"></span>
        </div>
        <div class="copy-actions">
            <button class="btn btn-copy" type="button" data-copy-target="update_existing_posts_prompt_copy_source" data-copy-status="update_existing_posts_prompt_copy_status">Copy Update Existing Posts Prompt</button>
            <span id="update_existing_posts_prompt_copy_status" class="copy-status"></span>
        </div>
        <div class="copy-actions">
            <button class="btn btn-copy" type="button" data-copy-target="existing_posts_baseline_copy_source" data-copy-status="existing_posts_baseline_copy_status">Copy Existing Posts Baseline</button>
            <span id="existing_posts_baseline_copy_status" class="copy-status"></span>
        </div>
        <div class="copy-actions">
            <button class="btn btn-copy" type="button" data-copy-target="existing_posts_with_indexation_copy_source" data-copy-status="existing_posts_with_indexation_copy_status">Copy Existing Posts + Indexation Status</button>
            <span id="existing_posts_with_indexation_copy_status" class="copy-status"></span>
        </div>
        <div class="copy-actions">
            <button class="btn btn-copy" type="button" data-copy-target="blog_post_sitemap_prompt_copy_source" data-copy-status="blog_post_sitemap_prompt_copy_status">Copy Blog Prompt + Sitemap</button>
            <span id="blog_post_sitemap_prompt_copy_status" class="copy-status"></span>
        </div>
        <div class="copy-actions">
            <button class="btn btn-copy" type="button" data-copy-target="catalog_prompt_copy_source" data-copy-status="catalog_prompt_copy_status">Copy Catalog Master Prompt</button>
            <span id="catalog_prompt_copy_status" class="copy-status"></span>
        </div>
    </div>
    <textarea id="blog_post_prompt_copy_source" class="copy-source" readonly><?= e($blogPostPromptTemplate) ?></textarea>
    <textarea id="guide_prompt_copy_source" class="copy-source" readonly><?= e($guidePromptTemplate) ?></textarea>
    <textarea id="product_single_review_prompt_copy_source" class="copy-source" readonly><?= e($productSingleReviewPromptTemplate) ?></textarea>
    <textarea id="product_versus_prompt_copy_source" class="copy-source" readonly><?= e($productVersusPromptTemplate) ?></textarea>
    <textarea id="best_for_y_prompt_copy_source" class="copy-source" readonly><?= e($bestForYPromptTemplate) ?></textarea>
    <textarea id="update_existing_posts_prompt_copy_source" class="copy-source" readonly><?= e($updateExistingPostPromptTemplate) ?></textarea>
    <textarea id="existing_posts_baseline_copy_source" class="copy-source" readonly><?= e($existingPostsBaselineCopyText) ?></textarea>
    <textarea id="existing_posts_with_indexation_copy_source" class="copy-source" readonly><?= e($existingPostsWithIndexationCopyText) ?></textarea>
    <textarea id="blog_post_sitemap_prompt_copy_source" class="copy-source" readonly><?= e($promptPlusSitemapCopyText) ?></textarea>
    <textarea id="catalog_prompt_copy_source" class="copy-source" readonly><?= e($catalogPromptTemplate) ?></textarea>
</section>

<section class="box">
    <h2>Claude Catalog Import (2 Steps)
        <span class="help-icon" title="Que: importar lote de productos en formato array PHP. Por que: acelera catalogo sin editar fila por fila." aria-label="Ayuda de catalog import">?</span>
    </h2>
    <p class="muted" style="margin:0 0 10px;">1) Copy Catalog Master Prompt, paste in Claude y copia el <code>$products</code> array. 2) Pegalo aqui y ejecuta update.</p>
    <?php $catalogImportForm = is_array($catalogImportForm ?? null) ? $catalogImportForm : ['payload' => '']; ?>
    <form method="post" style="margin:0;">
        <input type="hidden" name="action" value="maintenance_import_catalog_array">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <label for="catalog_payload_prompts">Paste Claude PHP array</label>
        <textarea id="catalog_payload_prompts" name="catalog_payload" rows="12" placeholder="$products = [ ... ];"><?= e((string) ($catalogImportForm['payload'] ?? '')) ?></textarea>
        <div style="display:flex;gap:10px;align-items:center;margin-top:10px;">
            <button class="btn" type="submit">Update Catalog DB</button>
        </div>
    </form>
    <?php if (is_array($catalogImportResult ?? null)): ?>
        <div style="margin-top:10px;border:1px solid #e2e8f0;border-radius:8px;padding:10px;background:#f8fbff;">
            <p style="margin:0 0 8px;font-size:13px;">
                Result:
                <strong class="<?= !empty($catalogImportResult['ok']) ? 'ok' : 'fail' ?>">
                    <?= !empty($catalogImportResult['ok']) ? 'OK' : 'FAIL' ?>
                </strong>
                | Exit code: <?= (int) ($catalogImportResult['exit_code'] ?? 1) ?>
            </p>
            <?php if (!empty($catalogImportResult['php_binary'])): ?>
                <p class="muted" style="margin:0 0 8px;font-size:12px;">PHP CLI used: <code><?= e((string) $catalogImportResult['php_binary']) ?></code></p>
            <?php endif; ?>
            <?php if (!empty($catalogImportResult['output_lines']) && is_array($catalogImportResult['output_lines'])): ?>
                <?php foreach ($catalogImportResult['output_lines'] as $line): ?>
                    <div style="font-family:monospace;font-size:12px;"><?= e((string) $line) ?></div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>

<section class="box">
    <h2>AI Draft Generator (Gemini)
        <span class="help-icon" title="Que: generar borrador inicial de post. Por que: ahorrar tiempo de arranque y luego editar manualmente." aria-label="Ayuda de AI draft">?</span>
    </h2>
    <p class="muted" style="margin:0 0 10px;">Modo auto: un click para generar borrador comercial (sin auto-publicar).</p>
    <?php $affiliateDraftForm = is_array($affiliateDraftForm ?? null) ? $affiliateDraftForm : ['auto_mode' => '1', 'topic' => '', 'keyword' => '', 'product' => '', 'category' => '', 'model' => 'gemini-2.0-flash']; ?>
    <form method="post" style="margin:0;">
        <input type="hidden" name="action" value="maintenance_generate_affiliate_post">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <div style="display:flex;gap:8px;align-items:center;margin:0 0 10px;">
            <input type="checkbox" id="ai_auto_mode_prompts" name="auto_mode" value="1" <?= (($affiliateDraftForm['auto_mode'] ?? '1') === '1') ? 'checked' : '' ?> style="width:auto;margin:0;">
            <label for="ai_auto_mode_prompts" style="margin:0;">Auto mode</label>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <div>
                <label>Topic (manual override)</label>
                <input type="text" name="topic" value="<?= e((string) ($affiliateDraftForm['topic'] ?? '')) ?>" placeholder="Best beginner telescope for city skies">
            </div>
            <div>
                <label>Keyword (manual override)</label>
                <input type="text" name="keyword" value="<?= e((string) ($affiliateDraftForm['keyword'] ?? '')) ?>" placeholder="best beginner telescope for city skies">
            </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <div>
                <label>Main Product (manual override)</label>
                <input type="text" name="product" value="<?= e((string) ($affiliateDraftForm['product'] ?? '')) ?>" placeholder="Celestron NexStar 4SE">
            </div>
            <div>
                <label>Category (manual override)</label>
                <input type="text" name="category" value="<?= e((string) ($affiliateDraftForm['category'] ?? '')) ?>" placeholder="telescopes">
            </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr auto;gap:12px;align-items:end;">
            <div>
                <label>Model</label>
                <input type="text" name="model" value="<?= e((string) ($affiliateDraftForm['model'] ?? 'gemini-2.0-flash')) ?>" placeholder="gemini-2.0-flash">
            </div>
            <div>
                <button class="btn" type="submit">Generate Draft</button>
            </div>
        </div>
    </form>
    <?php if (is_array($affiliateDraftResult ?? null)): ?>
        <div style="margin-top:10px;border:1px solid #e2e8f0;border-radius:8px;padding:10px;background:#f8fbff;">
            <p style="margin:0 0 8px;font-size:13px;">
                Result:
                <strong class="<?= !empty($affiliateDraftResult['ok']) ? 'ok' : 'fail' ?>">
                    <?= !empty($affiliateDraftResult['ok']) ? 'OK' : 'FAIL' ?>
                </strong>
                | Exit code: <?= (int) ($affiliateDraftResult['exit_code'] ?? 1) ?>
            </p>
            <?php if (!empty($affiliateDraftResult['php_binary'])): ?>
                <p class="muted" style="margin:0 0 8px;font-size:12px;">PHP CLI used: <code><?= e((string) $affiliateDraftResult['php_binary']) ?></code></p>
            <?php endif; ?>
            <?php if (!empty($affiliateDraftResult['output_lines']) && is_array($affiliateDraftResult['output_lines'])): ?>
                <?php foreach ($affiliateDraftResult['output_lines'] as $line): ?>
                    <div style="font-family:monospace;font-size:12px;"><?= e((string) $line) ?></div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>
