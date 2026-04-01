<?php

declare(strict_types=1);

/**
 * ENMA View: Guides Editor
 */

if (!function_exists('enma_render_guides')) {
    function enma_render_guides(array $overrides): void
    {
        $slugs = [
            'best-beginner-telescopes' => 'Best Beginner Telescopes',
            'best-telescope-accessories' => 'Best Telescope Accessories',
            'best-telescopes-under-500' => 'Best Telescopes Under $500',
        ];
        $fields = [
            'title' => 'Title',
            'description' => 'Description',
            'intro' => 'Intro',
            'final_recommendation' => 'Final Recommendation',
            'cta_text' => 'CTA Text',
            'cta_note' => 'CTA Note',
        ];
        ?>
        <section class="box">
            <h2>Edit Guide Overrides</h2>
            <p class="muted" style="margin-bottom:14px;">Override default guide content. Leave blank to use defaults.</p>
            <form method="post">
                <input type="hidden" name="action" value="save_guides_overrides">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                
                <?php foreach ($slugs as $slug => $label): ?>
                    <div style="margin-bottom:24px;padding:16px;border:1px solid #e2e8f0;border-radius:8px;background:#fafbfc;">
                        <h3 style="margin-top:0;"><?= e($label) ?></h3>
                        <?php foreach ($fields as $field => $fieldLabel): ?>
                            <div style="margin-bottom:12px;">
                                <label><?= e($fieldLabel) ?></label>
                                <?php if (in_array($field, ['intro', 'final_recommendation', 'cta_note'], true)): ?>
                                    <textarea name="<?= e($slug . '__' . $field) ?>" rows="3"><?= e($overrides[$slug][$field] ?? '') ?></textarea>
                                <?php else: ?>
                                    <input type="text" name="<?= e($slug . '__' . $field) ?>" value="<?= e($overrides[$slug][$field] ?? '') ?>">
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
                
                <button class="btn" type="submit">Save All Guides</button>
            </form>
        </section>
        <?php
    }
}
