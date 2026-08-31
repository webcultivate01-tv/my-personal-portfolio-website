<?php /** @var string $csrf  @var string $email  @var ?string $error */ ?>
<div class="auth-card">
  <h1 class="auth-card__title">Set a new password</h1>
  <p class="auth-card__sub">Resetting the password for <strong><?= e($email) ?></strong>.</p>

  <?php if (!empty($error)): ?>
    <div class="alert alert--error">
      <?= $error === 'mismatch' ? 'Both passwords must match.' : 'New password must be at least 8 characters.' ?>
    </div>
  <?php endif; ?>

  <form method="post" action="<?= url('/reset-password') ?>" class="form">
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">

    <label class="form__label" for="password">New password</label>
    <input class="form__input" type="password" id="password" name="password"
           placeholder="At least 8 characters" minlength="8" required autofocus>

    <label class="form__label" for="password_confirm">Confirm new password</label>
    <input class="form__input" type="password" id="password_confirm" name="password_confirm"
           placeholder="Repeat the new password" minlength="8" required>

    <button type="submit" class="btn btn--primary btn--block">Update password</button>
  </form>

  <p class="auth-card__foot"><a href="<?= url('/login') ?>">Back to sign in</a></p>
</div>
