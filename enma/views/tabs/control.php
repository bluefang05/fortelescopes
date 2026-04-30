<section class="box">
    <h2>Control Diario</h2>
    <p class="muted" style="margin:0 0 10px;">Vista unica para operar ENMA solo: prioridades, recordatorios y acciones de dinero.</p>
    <div class="ops-kpis">
        <div class="ops-kpi"><div class="k">Reminders Open</div><div class="v"><?= number_format((int) ($operatorReminderStats['open'] ?? 0)) ?></div></div>
        <div class="ops-kpi"><div class="k">High Priority</div><div class="v"><?= number_format((int) ($operatorReminderStats['high_open'] ?? 0)) ?></div></div>
        <div class="ops-kpi"><div class="k">Published Posts</div><div class="v"><?= number_format((int) ($operatorMoneyStats['posts_published'] ?? 0)) ?></div></div>
        <div class="ops-kpi"><div class="k">Draft Posts</div><div class="v"><?= number_format((int) ($operatorMoneyStats['posts_draft'] ?? 0)) ?></div></div>
        <div class="ops-kpi"><div class="k">Missing Tags</div><div class="v"><?= number_format((int) ($operatorMoneyStats['products_missing_tags'] ?? 0)) ?></div></div>
        <div class="ops-kpi"><div class="k">Missing Images</div><div class="v"><?= number_format((int) ($operatorMoneyStats['products_missing_images'] ?? 0)) ?></div></div>
        <div class="ops-kpi"><div class="k">Index Pending</div><div class="v"><?= number_format((int) ($operatorMoneyStats['index_pending'] ?? 0)) ?></div></div>
        <div class="ops-kpi"><div class="k">Not Indexed</div><div class="v"><?= number_format((int) ($operatorMoneyStats['index_not_indexed'] ?? 0)) ?></div></div>
    </div>
    <div class="ops-nav">
        <a class="ops-link" href="<?= e(url('/enma/?tab=products#products-not-found-actions')) ?>">Fix Product Leaks</a>
        <a class="ops-link" href="<?= e(url('/enma/?tab=indexation')) ?>">Run Indexation Queue</a>
        <a class="ops-link" href="<?= e(url('/enma/?tab=prompts')) ?>">Prompts Workspace</a>
        <a class="ops-link" href="<?= e(url('/enma/?tab=posts#posts-add')) ?>">Create New Post</a>
        <a class="ops-link" href="<?= e(url('/enma/?tab=maintenance#ops-routines')) ?>">Maintenance Routines</a>
    </div>
</section>

<section class="box">
    <h2>Agregar Reminder</h2>
    <form method="post">
        <input type="hidden" name="action" value="operator_add_reminder">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <div style="display:grid;grid-template-columns:1.2fr 1fr 1fr;gap:10px;">
            <div>
                <label>Titulo</label>
                <input type="text" name="title" required placeholder="Ej: Revisar indexacion de 5 URLs nuevas">
            </div>
            <div>
                <label>Priority</label>
                <select name="priority">
                    <option value="high">high</option>
                    <option value="medium" selected>medium</option>
                    <option value="low">low</option>
                </select>
            </div>
            <div>
                <label>Due Date</label>
                <input type="date" name="due_date">
            </div>
        </div>
        <label>Detalle</label>
        <input type="text" name="details" placeholder="Que exactamente tienes que verificar">
        <button class="btn" type="submit">Guardar reminder</button>
    </form>
</section>

<section class="box">
    <h2>Reminders Activos</h2>
    <?php if (($operatorRemindersOpen ?? []) === []): ?>
        <div class="empty">No hay reminders activos.</div>
    <?php else: ?>
        <table>
            <thead>
            <tr>
                <th>Tarea</th>
                <th>Priority</th>
                <th>Due</th>
                <th>Actualizado</th>
                <th>Acciones</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ((array) $operatorRemindersOpen as $item): ?>
                <tr>
                    <td>
                        <strong><?= e((string) ($item['title'] ?? '')) ?></strong><br>
                        <span class="muted"><?= e((string) ($item['details'] ?? '')) ?></span>
                    </td>
                    <td><?= e((string) ($item['priority'] ?? 'medium')) ?></td>
                    <td><?= e((string) (($item['due_date'] ?? '') !== '' ? $item['due_date'] : '-')) ?></td>
                    <td><?= e((string) ($item['updated_at'] ?? '')) ?></td>
                    <td>
                        <form method="post" style="display:inline;">
                            <input type="hidden" name="action" value="operator_toggle_reminder">
                            <input type="hidden" name="id" value="<?= (int) ($item['id'] ?? 0) ?>">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <button class="btn" type="submit" style="padding:6px 10px;font-size:12px;">Done</button>
                        </form>
                        <form method="post" style="display:inline;" onsubmit="return confirm('Delete reminder?');">
                            <input type="hidden" name="action" value="operator_delete_reminder">
                            <input type="hidden" name="id" value="<?= (int) ($item['id'] ?? 0) ?>">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <button type="submit" style="background:none;border:none;color:#b91c1c;cursor:pointer;padding:0 0 0 8px;font-size:12px;">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>

<section class="box">
    <h2>Completados Recientes</h2>
    <?php if (($operatorRemindersDone ?? []) === []): ?>
        <div class="empty">Aun no hay reminders completados.</div>
    <?php else: ?>
        <table>
            <thead>
            <tr>
                <th>Tarea</th>
                <th>Done At</th>
                <th>Accion</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ((array) $operatorRemindersDone as $item): ?>
                <tr>
                    <td><?= e((string) ($item['title'] ?? '')) ?></td>
                    <td><?= e((string) (($item['last_done_at'] ?? '') !== '' ? $item['last_done_at'] : '-')) ?></td>
                    <td>
                        <form method="post" style="display:inline;">
                            <input type="hidden" name="action" value="operator_toggle_reminder">
                            <input type="hidden" name="id" value="<?= (int) ($item['id'] ?? 0) ?>">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <button class="btn" type="submit" style="padding:6px 10px;font-size:12px;">Reopen</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>
