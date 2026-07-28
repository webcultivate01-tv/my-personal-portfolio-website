<?php /** @var string $csrf @var array $user @var bool $canEdit */ ?>
<?php $isAdmin = \App\Core\Auth::isAdmin(); ?>
<?php $docsDone = (new \App\Models\User())->hasCompletedDocuments($user); ?>
<header class="page-head">
  <div>
    <h1 class="page-head__title">My Account</h1>
    <p class="page-head__sub">Your profile details and sign-in security.</p>
  </div>
</header>

<section class="profile-head">
  <?php if ($docsDone): ?>
    <img class="profile-head__photo" src="<?= url('/account/document?id=' . (int) $user['id'] . '&field=profile_photo') ?>" alt="">
  <?php else: ?>
    <span class="avatar avatar--xl"><?= e(strtoupper(substr($user['name'] ?? '', 0, 1))) ?></span>
  <?php endif; ?>
  <div>
    <h2 class="profile-head__name"><?= e($user['name'] ?? '') ?></h2>
    <span class="role-badge role-badge--<?= e($user['role'] ?? 'manager') ?>"><?= e(ucfirst($user['role'] ?? 'manager')) ?></span>
    <?php if (!empty($user['designation'])): ?><span class="profile-head__role"><?= e($user['designation']) ?></span><?php endif; ?>
  </div>
</section>

<section class="panel panel--pad">
  <div class="panel__head panel__head--plain">
    <h2 class="panel__title">Contact &amp; identity</h2>
    <?php if (!$isAdmin): ?><span class="lock-hint">🔒 Set by an admin — ask them to change these</span><?php endif; ?>
  </div>
  <div class="stat-grid stat-grid--profile">
    <div class="stat"><span class="stat__label">Email</span><span class="stat__value stat__value--sm"><?= e($user['email'] ?? '') ?></span></div>
    <div class="stat"><span class="stat__label">Mobile number</span><span class="stat__value stat__value--sm"><?= e($user['phone'] ?? '—') ?></span></div>
    <div class="stat"><span class="stat__label">Alternate mobile</span><span class="stat__value stat__value--sm"><?= e($user['alternate_phone'] ?? '—') ?></span></div>
    <div class="stat"><span class="stat__label">Aadhar number</span><span class="stat__value stat__value--sm"><?= e($user['aadhar_number'] ?? '—') ?></span></div>
    <div class="stat"><span class="stat__label">PAN number</span><span class="stat__value stat__value--sm"><?= e($user['pan_number'] ?? '—') ?></span></div>
    <div class="stat"><span class="stat__label">Address</span><span class="stat__value stat__value--sm"><?= e($user['address'] ?? '—') ?></span></div>
  </div>
  <?php if ($isAdmin): ?>
    <p class="field__hint" style="padding:0 22px">Need to fix one of these? Edit yourself from <a href="<?= url('/users') ?>">Admin Management</a>.</p>
  <?php endif; ?>
</section>

<section class="panel panel--pad">
  <div class="panel__head panel__head--plain">
    <h2 class="panel__title">Employment</h2>
    <span class="lock-hint">🔒 Managed by an admin</span>
  </div>
  <div class="stat-grid stat-grid--profile">
    <div class="stat"><span class="stat__label">Designation</span><span class="stat__value stat__value--sm"><?= e($user['designation'] ?? '—') ?></span></div>
    <div class="stat"><span class="stat__label">Date of joining</span><span class="stat__value stat__value--sm"><?= e($user['date_of_joining'] ?? '—') ?></span></div>
  </div>
</section>

<section class="panel panel--pad">
  <div class="panel__head panel__head--plain">
    <h2 class="panel__title">Personal details</h2>
  </div>
  <form class="form-grid" method="post" action="<?= url('/account/profile') ?>">
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
    <div class="field">
      <label class="form__label" for="date_of_birth">Date of birth</label>
      <input class="form__input" type="date" id="date_of_birth" name="date_of_birth" value="<?= e($user['date_of_birth'] ?? '') ?>">
    </div>
    <div class="field">
      <label class="form__label" for="emergency_contact_name">Emergency contact name</label>
      <input class="form__input" type="text" id="emergency_contact_name" name="emergency_contact_name" value="<?= e($user['emergency_contact_name'] ?? '') ?>">
    </div>
    <div class="field">
      <label class="form__label" for="emergency_contact_phone">Emergency contact number</label>
      <input class="form__input" type="text" id="emergency_contact_phone" name="emergency_contact_phone" value="<?= e($user['emergency_contact_phone'] ?? '') ?>" pattern="\d{10}" maxlength="10">
    </div>
    <div class="field field--action">
      <button type="submit" class="btn btn--primary">Save details</button>
    </div>
  </form>
</section>

<section class="panel panel--pad">
  <div class="panel__head panel__head--plain">
    <h2 class="panel__title">Identity documents</h2>
    <span class="lock-hint">🔒 Uploaded once, can't be changed</span>
  </div>
  <?php if ($docsDone): ?>
    <div class="doc-grid">
      <?php foreach (\App\Models\User::DOCUMENT_FIELDS as $field): ?>
        <a class="doc-tile" target="_blank" rel="noopener"
           href="<?= url('/account/document?id=' . (int) $user['id'] . '&field=' . $field) ?>">
          <?= e(ucwords(str_replace('_', ' ', $field))) ?>
          <span>View &rarr;</span>
        </a>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="notice">
      <strong>Documents not uploaded yet.</strong>
      <p><a href="<?= url('/account/onboarding') ?>">Upload your profile photo and ID documents</a> to complete your profile.</p>
    </div>
  <?php endif; ?>
</section>

<section class="panel panel--pad">
  <div class="panel__head panel__head--plain">
    <h2 class="panel__title">Change password</h2>
  </div>

  <?php if ($canEdit): ?>
    <form class="form form--narrow" method="post" action="<?= url('/account/password') ?>">
      <input type="hidden" name="csrf" value="<?= e($csrf) ?>">

      <label class="form__label" for="current_password">Current password</label>
      <input class="form__input" type="password" id="current_password" name="current_password" required>

      <label class="form__label" for="new_password">New password</label>
      <input class="form__input" type="password" id="new_password" name="new_password" minlength="8" required>

      <label class="form__label" for="confirm_password">Confirm new password</label>
      <input class="form__input" type="password" id="confirm_password" name="confirm_password" minlength="8" required>

      <button type="submit" class="btn btn--primary btn--block">Update password</button>
    </form>
  <?php else: ?>
    <div class="notice">
      <strong>Password changes are managed by an admin.</strong>
      <p>As a manager you can’t change your own password. If you need it reset, ask an
      administrator to set a new one for you from Admin Management.</p>
    </div>
  <?php endif; ?>
</section>
