<?php /** @var string $title */ ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>404 — <?= e(APP_NAME) ?></title>
  <link rel="stylesheet" href="<?= asset('css/admin.css') ?>">
</head>
<body class="auth">
  <div class="auth-card" style="text-align:center">
    <h1 class="auth-card__title">404</h1>
    <p class="auth-card__sub">That page doesn’t exist.</p>
    <a class="btn btn--primary btn--block" href="<?= url('/') ?>">Go to dashboard</a>
  </div>
</body>
</html>
