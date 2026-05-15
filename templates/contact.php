<section class="hero">
    <span class="hero-kicker">Contact</span>
    <h1>Get in Touch</h1>
    <p>Questions, corrections, or partnership ideas? Send us a message and we will review it.</p>
</section>

<section class="panel">
    <?php if (!empty($data['contact_flash'])): ?>
        <div class="ok"><?= e((string) $data['contact_flash']) ?></div>
    <?php endif; ?>
    <?php foreach (($data['contact_errors'] ?? []) as $error): ?>
        <div class="error"><?= e((string) $error) ?></div>
    <?php endforeach; ?>

    <h2 class="section-title u-mt-0">Send a message</h2>
    <form method="post" class="contact-form">
        <input type="hidden" name="action" value="submit_contact_message">
        <input type="text" name="website" value="" class="u-hidden" tabindex="-1" autocomplete="off" aria-hidden="true">
        <div class="contact-grid">
            <div class="contact-field">
                <label for="contact-name">Name</label>
                <input id="contact-name" type="text" name="name" required maxlength="120" value="<?= e((string) (($data['contact_form']['name'] ?? ''))) ?>">
            </div>
            <div class="contact-field">
                <label for="contact-email">Email</label>
                <input id="contact-email" type="email" name="email" required maxlength="190" value="<?= e((string) (($data['contact_form']['email'] ?? ''))) ?>">
            </div>
        </div>
        <div class="contact-field">
            <label for="contact-subject">Subject</label>
            <input id="contact-subject" type="text" name="subject" required maxlength="190" value="<?= e((string) (($data['contact_form']['subject'] ?? ''))) ?>">
        </div>
        <div class="contact-field">
            <label for="contact-message">Message</label>
            <textarea id="contact-message" name="message" rows="7" required maxlength="5000"><?= e((string) (($data['contact_form']['message'] ?? ''))) ?></textarea>
        </div>
        <button class="btn contact-submit" type="submit">Send message</button>
    </form>

    <h2 class="section-title u-mt-18">Contact Details</h2>
    <p class="muted">Email: hello@fortelescopes.com</p>
    <p class="muted">For product corrections, include the product name and page URL so we can update faster.</p>

    <h2 class="section-title u-mt-18">Response time</h2>
    <p class="muted">We usually respond within 2-3 business days.</p>
</section>
