<section class="box">
    <h2>Inbox Messages</h2>
    <p class="muted" style="margin:0 0 10px;">Messages submitted from the public contact form. Manage status and clean old entries.</p>
    <div class="ops-kpis">
        <div class="ops-kpi"><div class="k">Visible Rows</div><div class="v"><?= number_format(count($messagesRows)) ?></div></div>
        <div class="ops-kpi"><div class="k">Total Messages</div><div class="v"><?= number_format($messagesTotal) ?></div></div>
        <div class="ops-kpi"><div class="k">Current Filter</div><div class="v"><?= e($messageStatusFilter === 'all' ? 'all' : $messageStatusFilter) ?></div></div>
    </div>
</section>

<section class="box">
    <h2>Filter</h2>
    <form method="get" class="toolbar">
        <input type="hidden" name="tab" value="messages">
        <input type="hidden" name="messages_page" value="1">
        <div class="field">
            <label>Status</label>
            <select name="msg_status">
                <option value="all" <?= $messageStatusFilter === 'all' ? 'selected' : '' ?>>All</option>
                <option value="new" <?= $messageStatusFilter === 'new' ? 'selected' : '' ?>>New</option>
                <option value="read" <?= $messageStatusFilter === 'read' ? 'selected' : '' ?>>Read</option>
                <option value="archived" <?= $messageStatusFilter === 'archived' ? 'selected' : '' ?>>Archived</option>
            </select>
        </div>
        <div class="field">
            <label>Search</label>
            <input type="text" name="msg_q" value="<?= e($messageSearch) ?>" placeholder="name, email, subject, body">
        </div>
        <button class="btn" type="submit">Apply</button>
    </form>
</section>

<section class="box">
    <h2>Messages</h2>
    <?php if ($messagesRows === []): ?>
        <div class="empty">No messages found for this filter.</div>
    <?php else: ?>
        <p class="muted">Showing <?= number_format(count($messagesRows)) ?> of <?= number_format($messagesTotal) ?> messages.</p>
        <table>
            <thead>
            <tr>
                <th>ID</th>
                <th>Contact</th>
                <th>Subject</th>
                <th>Message</th>
                <th>Status</th>
                <th>Created</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($messagesRows as $row): ?>
                <tr>
                    <td><?= (int) ($row['id'] ?? 0) ?></td>
                    <td>
                        <strong><?= e((string) ($row['name'] ?? '')) ?></strong><br>
                        <span class="muted"><?= e((string) ($row['email'] ?? '')) ?></span>
                    </td>
                    <td><?= e((string) ($row['subject'] ?? '')) ?></td>
                    <td style="max-width: 360px;">
                        <?= nl2br(e((string) ($row['message_text'] ?? ''))) ?>
                    </td>
                    <td><?= e((string) ($row['status'] ?? 'new')) ?></td>
                    <td><?= e((string) ($row['created_at'] ?? '')) ?></td>
                    <td style="white-space:nowrap;">
                        <form method="post" style="display:inline;">
                            <input type="hidden" name="action" value="messages_update_status">
                            <input type="hidden" name="message_id" value="<?= (int) ($row['id'] ?? 0) ?>">
                            <input type="hidden" name="message_status" value="read">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <button class="tab" type="submit">Mark read</button>
                        </form>
                        <form method="post" style="display:inline;">
                            <input type="hidden" name="action" value="messages_update_status">
                            <input type="hidden" name="message_id" value="<?= (int) ($row['id'] ?? 0) ?>">
                            <input type="hidden" name="message_status" value="archived">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <button class="tab" type="submit">Archive</button>
                        </form>
                        <form method="post" style="display:inline;" onsubmit="return confirm('Delete this message?');">
                            <input type="hidden" name="action" value="messages_delete">
                            <input type="hidden" name="message_id" value="<?= (int) ($row['id'] ?? 0) ?>">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <button class="tab" type="submit" style="color:#b91c1c;">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?= $messagesPagination ?>
    <?php endif; ?>
</section>
