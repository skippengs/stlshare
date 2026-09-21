<div class="card center-card" style="text-align:center;">
  <h1>Awaiting approval</h1>
  <p class="muted">Your account has been created and is waiting for an admin to approve it. You'll be able to log in once that happens.</p>
  <form method="post" action="/logout" style="margin-top:1rem;">
    <?= csrf_field() ?>
    <button type="submit" class="secondary">Log out</button>
  </form>
</div>
