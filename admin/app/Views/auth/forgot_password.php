<?php /** @var string $csrf  @var ?string $error */ ?>
<div class="auth-card">
  <h1 class="auth-card__title">Forgot password</h1>
  <p class="auth-card__sub">Enter the email address on your account and we'll let you set a new password.</p>

  <?php if (!empty($error)): ?>
    <div class="alert alert--error">
      <?php
      echo match ($error) {
          'notfound'  => 'No account is registered with that email address.',
          'expired'   => 'That reset session expired. Please enter your email again.',
          'throttled' => 'Too many attempts. Please wait a few minutes and try again.',
          default     => 'Please enter your email address.',
      };
      ?>
    </div>
  <?php endif; ?>

  <form method="post" action="<?= url('/forgot-password') ?>" class="form">
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">

    <label class="form__label" for="email">Email</label>
    <input class="form__input" type="email" id="email" name="email"
           placeholder="you@example.com" required autofocus>

    <button type="submit" class="btn btn--primary btn--block">Continue</button>
  </form>

  <p class="auth-card__foot"><a href="<?= url('/login') ?>">Back to sign in</a></p>
</div>
