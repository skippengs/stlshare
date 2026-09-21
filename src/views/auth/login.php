<div class="card center-card">
  <h1>Log in</h1>
  <form method="post" action="/login">
    <?= csrf_field() ?>
    <label for="email">Email</label>
    <input type="email" id="email" name="email" required>

    <label for="password">Password</label>
    <input type="password" id="password" name="password" required>

    <button type="submit" style="margin-top:1.25rem;width:100%;">Log in</button>
  </form>
  <p class="muted" style="margin-top:1.25rem;">No account yet? <a href="/register">Register</a></p>
</div>
