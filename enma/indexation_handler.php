<?php

declare(strict_types=1);

if (!function_exists('enma_indexation_init_table')) {
    function enma_indexation_init_table(PDO $pdo): void
    {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS post_indexation_tracker (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                post_id BIGINT UNSIGNED NOT NULL,
                slug VARCHAR(191) NOT NULL,
                post_type VARCHAR(20) NOT NULL DEFAULT "post",
                post_status VARCHAR(20) NOT NULL DEFAULT "draft",
                canonical_url VARCHAR(255) NOT NULL DEFAULT "",
                index_state VARCHAR(20) NOT NULL DEFAULT "pending",
                is_indexed TINYINT(1) NOT NULL DEFAULT 0,
                check_priority TINYINT UNSIGNED NOT NULL DEFAULT 1,
                notes VARCHAR(500) NOT NULL DEFAULT "",
                last_checked_at VARCHAR(40) NULL DEFAULT NULL,
                next_check_at VARCHAR(40) NULL DEFAULT NULL,
                created_at VARCHAR(40) NOT NULL,
                updated_at VARCHAR(40) NOT NULL,
                UNIQUE KEY uniq_post_id (post_id),
                INDEX idx_state (index_state),
                INDEX idx_is_indexed (is_indexed),
                INDEX idx_post_type (post_type),
                INDEX idx_post_status (post_status),
                INDEX idx_next_check (next_check_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }
}

if (!function_exists('enma_indexation_sync_tracker')) {
    function enma_indexation_sync_tracker(PDO $pdo): array
    {
        enma_indexation_init_table($pdo);

        $postRows = $pdo->query(
            'SELECT id, slug, post_type, status
             FROM posts
             WHERE TRIM(COALESCE(slug, "")) <> ""
               AND post_type IN ("post", "review", "guide")'
        )->fetchAll();

        $existingIds = $pdo->query('SELECT post_id FROM post_indexation_tracker')->fetchAll(PDO::FETCH_COLUMN);
        $existingMap = [];
        foreach ($existingIds as $existingId) {
            $existingMap[(int) $existingId] = true;
        }

        $upsert = $pdo->prepare(
            'INSERT INTO post_indexation_tracker (
                post_id, slug, post_type, post_status, canonical_url,
                index_state, is_indexed, check_priority, notes, created_at, updated_at
             ) VALUES (
                :post_id, :slug, :post_type, :post_status, :canonical_url,
                :index_state, :is_indexed, 1, "", :created_at, :updated_at
             )
             ON DUPLICATE KEY UPDATE
                slug = VALUES(slug),
                post_type = VALUES(post_type),
                post_status = VALUES(post_status),
                canonical_url = VALUES(canonical_url),
                index_state = CASE
                    WHEN post_indexation_tracker.is_indexed = 1 THEN "indexed"
                    WHEN VALUES(post_status) <> "published" THEN "excluded"
                    WHEN post_indexation_tracker.index_state = "excluded" THEN "pending"
                    ELSE post_indexation_tracker.index_state
                END,
                is_indexed = CASE
                    WHEN post_indexation_tracker.index_state = "indexed" OR post_indexation_tracker.is_indexed = 1 THEN 1
                    WHEN VALUES(post_status) <> "published" THEN 0
                    ELSE post_indexation_tracker.is_indexed
                END,
                updated_at = VALUES(updated_at)'
        );

        $processed = 0;
        $added = 0;
        $seenIds = [];
        $now = now_iso();
        foreach ($postRows as $row) {
            $postId = (int) ($row['id'] ?? 0);
            if ($postId <= 0) {
                continue;
            }

            $slug = trim((string) ($row['slug'] ?? ''));
            if ($slug === '') {
                continue;
            }

            $postType = trim((string) ($row['post_type'] ?? 'post'));
            $postStatus = trim((string) ($row['status'] ?? 'draft'));
            $path = post_url_path($row);
            $canonicalUrl = absolute_url($path);

            $upsert->execute([
                ':post_id' => $postId,
                ':slug' => $slug,
                ':post_type' => $postType,
                ':post_status' => $postStatus,
                ':canonical_url' => $canonicalUrl,
                ':index_state' => $postStatus === 'published' ? 'pending' : 'excluded',
                ':is_indexed' => 0,
                ':created_at' => $now,
                ':updated_at' => $now,
            ]);

            $processed++;
            if (!isset($existingMap[$postId])) {
                $added++;
            }
            $seenIds[$postId] = true;
        }

        $staleIds = [];
        foreach ($existingMap as $postId => $_) {
            if (!isset($seenIds[$postId])) {
                $staleIds[] = (int) $postId;
            }
        }

        $removed = 0;
        if ($staleIds !== []) {
            $chunks = array_chunk($staleIds, 200);
            foreach ($chunks as $chunk) {
                $placeholders = implode(',', array_fill(0, count($chunk), '?'));
                $stmt = $pdo->prepare('DELETE FROM post_indexation_tracker WHERE post_id IN (' . $placeholders . ')');
                $stmt->execute($chunk);
                $removed += (int) $stmt->rowCount();
            }
        }

        return [
            'processed' => $processed,
            'added' => $added,
            'updated' => max(0, $processed - $added),
            'removed' => $removed,
        ];
    }
}

if (!function_exists('enma_indexation_google_probe')) {
    function enma_indexation_google_probe(string $targetUrl): array
    {
        $targetUrl = trim($targetUrl);
        if ($targetUrl === '') {
            return ['ok' => false, 'indexed' => false, 'message' => 'Missing URL.'];
        }

        $query = 'site:' . SITE_DOMAIN . ' "' . $targetUrl . '"';
        $searchUrl = 'https://www.google.com/search?q=' . rawurlencode($query) . '&num=10&hl=en';

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 12,
                'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0 Safari/537.36\r\nAccept-Language: en-US,en;q=0.9\r\n",
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $html = @file_get_contents($searchUrl, false, $context);
        if (!is_string($html) || $html === '') {
            return ['ok' => false, 'indexed' => false, 'message' => 'Search probe failed (network or blocked).'];
        }

        $lower = strtolower($html);
        if (strpos($lower, 'unusual traffic') !== false || strpos($lower, 'sorry/index') !== false) {
            return ['ok' => false, 'indexed' => false, 'message' => 'Google blocked automated query (captcha/rate-limit).'];
        }

        $decoded = html_entity_decode($html, ENT_QUOTES | ENT_HTML5);
        $encodedTarget = rawurlencode($targetUrl);
        $indexed = (
            strpos($decoded, $targetUrl) !== false
            || strpos($decoded, '/url?q=' . $encodedTarget) !== false
            || strpos($decoded, urldecode($encodedTarget)) !== false
        );

        return [
            'ok' => true,
            'indexed' => $indexed,
            'message' => $indexed ? 'Indexed match found in Google result HTML.' : 'No indexed match found in sampled Google results.',
        ];
    }
}

if ($authenticated && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'indexation_update') {
    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid request token.';
    } else {
        $postId = (int) ($_POST['post_id'] ?? 0);
        $indexState = trim((string) ($_POST['index_state'] ?? 'pending'));
        $notes = trim((string) ($_POST['notes'] ?? ''));
        $nextCheckDays = max(0, min(90, (int) ($_POST['next_check_days'] ?? 0)));
        $allowedStates = ['pending', 'indexed', 'not_indexed', 'excluded'];

        if ($postId <= 0) {
            $errors[] = 'Invalid post id for indexation update.';
        } elseif (!in_array($indexState, $allowedStates, true)) {
            $errors[] = 'Invalid indexation state.';
        } else {
            try {
                enma_indexation_sync_tracker($pdo);
                $isIndexed = $indexState === 'indexed' ? 1 : 0;
                $nextCheckAt = $nextCheckDays > 0 ? gmdate('c', time() + ($nextCheckDays * 86400)) : null;

                $stmt = $pdo->prepare(
                    'UPDATE post_indexation_tracker
                     SET index_state = :index_state,
                         is_indexed = :is_indexed,
                         notes = :notes,
                         last_checked_at = :last_checked_at,
                         next_check_at = :next_check_at,
                         updated_at = :updated_at
                     WHERE post_id = :post_id'
                );
                $stmt->execute([
                    ':index_state' => $indexState,
                    ':is_indexed' => $isIndexed,
                    ':notes' => mb_substr($notes, 0, 500),
                    ':last_checked_at' => now_iso(),
                    ':next_check_at' => $nextCheckAt,
                    ':updated_at' => now_iso(),
                    ':post_id' => $postId,
                ]);

                $flash = 'Indexation checklist updated.';
            } catch (Throwable $e) {
                $errors[] = 'Indexation update failed: ' . $e->getMessage();
            }
        }
    }
}

if ($authenticated && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'indexation_probe') {
    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid request token.';
    } else {
        $postId = (int) ($_POST['post_id'] ?? 0);
        if ($postId <= 0) {
            $errors[] = 'Invalid post id for indexation probe.';
        } else {
            try {
                enma_indexation_sync_tracker($pdo);
                $rowStmt = $pdo->prepare(
                    'SELECT post_id, canonical_url, post_status
                     FROM post_indexation_tracker
                     WHERE post_id = :post_id
                     LIMIT 1'
                );
                $rowStmt->execute([':post_id' => $postId]);
                $row = $rowStmt->fetch();
                if (!is_array($row)) {
                    $errors[] = 'Indexation row not found.';
                } else {
                    $postStatus = trim((string) ($row['post_status'] ?? 'draft'));
                    if ($postStatus !== 'published') {
                        $errors[] = 'Only published URLs can be checked for indexation.';
                    } else {
                        $targetUrl = trim((string) ($row['canonical_url'] ?? ''));
                        $probe = enma_indexation_google_probe($targetUrl);
                        if (empty($probe['ok'])) {
                            $errors[] = 'Heuristic probe failed: ' . (string) ($probe['message'] ?? 'Unknown error.');
                        } else {
                            $indexed = !empty($probe['indexed']);
                            $state = $indexed ? 'indexed' : 'not_indexed';
                            $note = '[' . gmdate('Y-m-d') . ' heuristic] ' . (string) ($probe['message'] ?? '');
                            $nextCheckAt = gmdate('c', time() + ($indexed ? 21 : 7) * 86400);
                            $updateStmt = $pdo->prepare(
                                'UPDATE post_indexation_tracker
                                 SET index_state = :index_state,
                                     is_indexed = :is_indexed,
                                     last_checked_at = :last_checked_at,
                                     next_check_at = :next_check_at,
                                     notes = CASE
                                        WHEN TRIM(COALESCE(notes, "")) = "" THEN :new_note
                                        ELSE CONCAT(:new_note, " | ", notes)
                                     END,
                                     updated_at = :updated_at
                                 WHERE post_id = :post_id'
                            );
                            $updateStmt->execute([
                                ':index_state' => $state,
                                ':is_indexed' => $indexed ? 1 : 0,
                                ':last_checked_at' => now_iso(),
                                ':next_check_at' => $nextCheckAt,
                                ':new_note' => mb_substr($note, 0, 500),
                                ':updated_at' => now_iso(),
                                ':post_id' => $postId,
                            ]);

                            $flash = $indexed ? 'Heuristic check: indexed.' : 'Heuristic check: not indexed.';
                        }
                    }
                }
            } catch (Throwable $e) {
                $errors[] = 'Indexation probe failed: ' . $e->getMessage();
            }
        }
    }
}

$indexationPage = $authenticated ? enma_page_value('indexation_page') : 1;
$indexationPerPage = 25;
$indexationTotal = 0;
$indexationTotalPages = 1;
$indexationRows = [];
$indexationStateFilter = $authenticated ? strtolower(trim((string) ($_GET['idx_state'] ?? 'all'))) : 'all';
$indexationTypeFilter = $authenticated ? strtolower(trim((string) ($_GET['idx_type'] ?? 'all'))) : 'all';
$indexationIndexedFilter = $authenticated ? strtolower(trim((string) ($_GET['idx_indexed'] ?? 'all'))) : 'all';
$indexationSort = $authenticated ? strtolower(trim((string) ($_GET['idx_sort'] ?? 'priority'))) : 'priority';
$indexationStateOptions = ['all', 'pending', 'indexed', 'not_indexed', 'excluded'];
$indexationTypeOptions = ['all', 'post', 'guide'];
$indexationIndexedOptions = ['all', 'indexed', 'not_indexed'];
$indexationSortOptions = ['priority', 'last_checked_oldest', 'last_checked_newest', 'title_asc', 'title_desc', 'recent_updates'];
if (!in_array($indexationStateFilter, $indexationStateOptions, true)) {
    $indexationStateFilter = 'all';
}
if (!in_array($indexationTypeFilter, $indexationTypeOptions, true)) {
    $indexationTypeFilter = 'all';
}
if (!in_array($indexationIndexedFilter, $indexationIndexedOptions, true)) {
    $indexationIndexedFilter = 'all';
}
if (!in_array($indexationSort, $indexationSortOptions, true)) {
    $indexationSort = 'priority';
}
$indexationTotals = [
    'all_rows' => 0,
    'indexed_rows' => 0,
    'pending_rows' => 0,
    'not_indexed_rows' => 0,
    'excluded_rows' => 0,
];
if ($authenticated && $activeTab === 'indexation') {
    try {
        enma_indexation_sync_tracker($pdo);

        $totalsRow = $pdo->query(
            'SELECT
                COUNT(*) AS all_rows,
                SUM(CASE WHEN is_indexed = 1 THEN 1 ELSE 0 END) AS indexed_rows,
                SUM(CASE WHEN index_state = "pending" THEN 1 ELSE 0 END) AS pending_rows,
                SUM(CASE WHEN index_state = "not_indexed" THEN 1 ELSE 0 END) AS not_indexed_rows,
                SUM(CASE WHEN index_state = "excluded" THEN 1 ELSE 0 END) AS excluded_rows
             FROM post_indexation_tracker'
        )->fetch();
        if (is_array($totalsRow)) {
            $indexationTotals = [
                'all_rows' => (int) ($totalsRow['all_rows'] ?? 0),
                'indexed_rows' => (int) ($totalsRow['indexed_rows'] ?? 0),
                'pending_rows' => (int) ($totalsRow['pending_rows'] ?? 0),
                'not_indexed_rows' => (int) ($totalsRow['not_indexed_rows'] ?? 0),
                'excluded_rows' => (int) ($totalsRow['excluded_rows'] ?? 0),
            ];
        }

        $whereParts = [];
        $params = [];
        if ($indexationStateFilter !== 'all') {
            $whereParts[] = 'pit.index_state = :idx_state';
            $params[':idx_state'] = $indexationStateFilter;
        }
        if ($indexationTypeFilter !== 'all') {
            $whereParts[] = 'pit.post_type = :idx_type';
            $params[':idx_type'] = $indexationTypeFilter;
        }
        if ($indexationIndexedFilter === 'indexed') {
            $whereParts[] = 'pit.is_indexed = 1';
        } elseif ($indexationIndexedFilter === 'not_indexed') {
            $whereParts[] = 'pit.is_indexed = 0';
        }
        $whereSql = $whereParts === [] ? '' : (' WHERE ' . implode(' AND ', $whereParts));

        $orderBySql = 'pit.is_indexed ASC, (pit.last_checked_at IS NULL) DESC, pit.next_check_at ASC, pit.post_id DESC';
        if ($indexationSort === 'last_checked_oldest') {
            $orderBySql = '(pit.last_checked_at IS NULL) DESC, pit.last_checked_at ASC, pit.post_id DESC';
        } elseif ($indexationSort === 'last_checked_newest') {
            $orderBySql = 'pit.last_checked_at DESC, pit.post_id DESC';
        } elseif ($indexationSort === 'title_asc') {
            $orderBySql = 'p.title ASC, pit.post_id DESC';
        } elseif ($indexationSort === 'title_desc') {
            $orderBySql = 'p.title DESC, pit.post_id DESC';
        } elseif ($indexationSort === 'recent_updates') {
            $orderBySql = 'pit.updated_at DESC, pit.post_id DESC';
        }

        $countStmt = $pdo->prepare(
            'SELECT COUNT(*)
             FROM post_indexation_tracker pit
             INNER JOIN posts p ON p.id = pit.post_id'
            . $whereSql
        );
        foreach ($params as $key => $value) {
            $countStmt->bindValue($key, $value, PDO::PARAM_STR);
        }
        $countStmt->execute();
        $indexationTotal = (int) $countStmt->fetchColumn();

        $indexationTotalPages = enma_total_pages($indexationTotal, $indexationPerPage);
        $indexationPage = min($indexationPage, $indexationTotalPages);
        $stmt = $pdo->prepare(
            'SELECT
                pit.post_id,
                pit.slug,
                pit.post_type,
                pit.post_status,
                pit.canonical_url,
                pit.index_state,
                pit.is_indexed,
                pit.last_checked_at,
                pit.next_check_at,
                pit.notes,
                pit.updated_at,
                p.title
             FROM post_indexation_tracker pit
             INNER JOIN posts p ON p.id = pit.post_id'
            . $whereSql
            . ' ORDER BY '
            . $orderBySql
            . '
                LIMIT :limit OFFSET :offset'
        );
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $indexationPerPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', ($indexationPage - 1) * $indexationPerPage, PDO::PARAM_INT);
        $stmt->execute();
        $indexationRows = $stmt->fetchAll();
    } catch (Throwable $e) {
        $errors[] = 'Indexation tracker load failed: ' . $e->getMessage();
    }
}
