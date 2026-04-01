<?php

declare(strict_types=1);

/**
 * ENMA Guides Handler
 * 
 * Handles all guides-related operations
 */

/**
 * Save guides overrides from form data
 */
function enma_handle_save_guides(array &$errors, array $postData): ?string
{
    if (!csrf_is_valid($postData['csrf_token'] ?? null)) {
        $errors[] = 'Invalid request token.';
        return null;
    }

    $slugs = ['best-beginner-telescopes', 'best-telescope-accessories', 'best-telescopes-under-500'];
    $fields = ['title', 'description', 'intro', 'final_recommendation', 'cta_text', 'cta_note'];
    $payload = [];

    foreach ($slugs as $slug) {
        $payload[$slug] = [];
        foreach ($fields as $field) {
            $key = $slug . '__' . $field;
            $value = trim((string) ($postData[$key] ?? ''));
            if ($value !== '') {
                $payload[$slug][$field] = $value;
            }
        }
    }

    if (!save_guides_overrides($payload)) {
        $errors[] = 'Could not save guide overrides file.';
        return null;
    }

    return 'Guide text overrides saved.';
}

/**
 * Load guides overrides for display
 */
function enma_load_guides_data(): array
{
    return load_guides_overrides();
}
