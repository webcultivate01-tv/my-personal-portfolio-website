<?php /** @var string $csrf  @var ?string $error  @var bool $reset */ ?>
<div class="auth-card">
  <h1 class="auth-card__title">Welcome back</h1>
  <p class="auth-card__sub">Sign in to manage your leads.</p>

  <?php if (!empty($reset)): ?>
    <div class="alert alert--success">Password updated. Sign in with your new password.</div>
  <?php endif; ?>

  <?php if (!empty($error)): ?>
    <div class="alert alert--error">
      <?= $error === 'invalid' ? 'Incorrect email or password.' : 'Please fill in both fields.' ?>
    </div>
  <?php endif; ?>

  <form method="post" action="<?= url('/login') ?>" class="form">
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">

    <label class="form__label" for="email">Email</label>
    <input class="form__input" type="email" id="email" name="email"
           placeholder="you@example.com" required autofocus>

    <label class="form__label" for="password">Password</label>
    <input class="form__input" type="password" id="password" name="password"
           placeholder="••••••••" required>

    <button type="submit" class="btn btn--primary btn--block">Sign in</button>
  </form>

  <p class="auth-card__foot"><a href="<?= url('/forgot-password') ?>">Forgot your password?</a></p>
</div>
