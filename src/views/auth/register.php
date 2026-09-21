<div class="card center-card">
  <h1>Create your account</h1>
  <p class="muted">New accounts need admin approval before they can log in — except the very first account, which becomes the admin automatically.</p>
  <form method="post" action="/register">
    <?= csrf_field() ?>
    <label for="display_name">Display name</label>
    <input type="text" id="display_name" name="display_name" value="<?= e($old['display_name'] ?? '') ?>" required>

    <label for="email">Email</label>
    <input type="email" id="email" name="email" value="<?= e($old['email'] ?? '') ?>" required>

    <label for="password">Password</label>
    <input type="password" id="password" name="password" minlength="8" required>

    <label class="checkbox-label" style="margin-top:1.1rem;">
      <input type="checkbox" name="consent" required>
      <span>I accept the <a href="/privacy" target="_blank">privacy policy</a> and <a href="/terms" target="_blank">terms &amp; upload rules</a>.</span>
    </label>

    <button type="submit" style="margin-top:1.25rem;width:100%;">Create account</button>
  </form>
  <p class="muted" style="margin-top:1.25rem;">Already have an account? <a href="/login">Log in</a></p>
</div>
