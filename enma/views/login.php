<?php

declare(strict_types=1);

/**
 * ENMA View: Login Page
 */

if (!function_exists('enma_render_login')) {
    function enma_render_login(array $errors, bool $isLocked): void
    {
        ?>
        <section class="box">
            <h2>Admin Login</h2>
            <form method="post">
                <input type="hidden" name="action" value="login">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <label>User</label>
                <input type="text" name="user" required>
                <label>Password</label>
                <input type="password" name="pass" required>
                <button class="btn" type="submit">Login</button>
                <?php if ($isLocked): ?>
                    <p class="muted" style="margin-top:10px;">Too many attempts. Please wait before trying again.</p>
                <?php endif; ?>
            </form>
        </section>
        <?php
    }
}
