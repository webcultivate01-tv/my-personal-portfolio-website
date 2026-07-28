<?php /** @var string $csrf @var array $target */ ?>
<?php $docsDone = (new \App\Models\User())->hasCompletedDocuments($target); ?>
<header class="page-head">
  <div>
    <h1 class="page-head__title">Edit <?= e($target['name']) ?></h1>
    <p class="page-head__sub">As an admin you can correct any field, including ones locked for the member themselves.</p>
  </div>
  <a class="btn btn--ghost" href="<?= url('/users') ?>">&larr; Back to Admin Management</a>
</header>

<section class="panel panel--pad">
  <div class="panel__head panel__head--plain">
    <h2 class="panel__title">Hire record</h2>
  </div>

  <form class="form-grid" method="post" action="<?= url('/users/update') ?>">
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
    <input type="hidden" name="id" value="<?= (int) $target['id'] ?>">

    <p class="form-grid__section">Account</p>
    <div class="field">
      <label class="form__label" for="name">Full name</label>
      <input class="form__input" type="text" id="name" name="name" value="<?= e($target['name']) ?>" required>
    </div>
    <div class="field">
      <label class="form__label">Role</label>
      <input class="form__input" type="text" value="<?= e(ucfirst($target['role'])) ?>" disabled>
      <span class="field__hint">Role can't be changed here — remove and re-add the member to change it.</span>
    </div>
    <div class="field">
      <label class="form__label" for="email">Email</label>
      <input class="form__input" type="email" id="email" name="email" value="<?= e($target['email']) ?>" required>
    </div>

    <p class="form-grid__section">Contact &amp; identity</p>
    <div class="field">
      <label class="form__label" for="phone">Mobile number</label>
      <input class="form__input" type="text" id="phone" name="phone" value="<?= e($target['phone'] ?? '') ?>" pattern="\d{10}" maxlength="10" required>
    </div>
    <div class="field">
      <label class="form__label" for="alternate_phone">Alternate mobile number</label>
      <input class="form__input" type="text" id="alternate_phone" name="alternate_phone" value="<?= e($target['alternate_phone'] ?? '') ?>" pattern="\d{10}" maxlength="10" required>
    </div>
    <div class="field field--wide">
      <label class="form__label" for="address">Address</label>
      <textarea class="form__input" id="address" name="address" rows="2" required><?= e($target['address'] ?? '') ?></textarea>
    </div>
    <div class="field">
      <label class="form__label" for="aadhar_number">Aadhar number</label>
      <input class="form__input" type="text" id="aadhar_number" name="aadhar_number" value="<?= e($target['aadhar_number'] ?? '') ?>" pattern="\d{12}" maxlength="12" required>
    </div>
    <div class="field">
      <label class="form__label" for="pan_number">PAN number</label>
      <input class="form__input" type="text" id="pan_number" name="pan_number" value="<?= e($target['pan_number'] ?? '') ?>" maxlength="10" style="text-transform:uppercase" required>
    </div>

    <p class="form-grid__section">Employment</p>
    <div class="field">
      <label class="form__label" for="designation">Designation</label>
      <input class="form__input" type="text" id="designation" name="designation" value="<?= e($target['designation'] ?? '') ?>" required>
    </div>
    <div class="field">
      <label class="form__label" for="date_of_joining">Date of joining</label>
      <input class="form__input" type="date" id="date_of_joining" name="date_of_joining" value="<?= e($target['date_of_joining'] ?? '') ?>" required>
    </div>
    <div class="field">
      <label class="form__label" for="date_of_birth">Date of birth</label>
      <input class="form__input" type="date" id="date_of_birth" name="date_of_birth" value="<?= e($target['date_of_birth'] ?? '') ?>" required>
    </div>

    <p class="form-grid__section">Emergency contact</p>
    <div class="field">
      <label class="form__label" for="emergency_contact_name">Contact name</label>
      <input class="form__input" type="text" id="emergency_contact_name" name="emergency_contact_name" value="<?= e($target['emergency_contact_name'] ?? '') ?>" required>
    </div>
    <div class="field">
      <label class="form__label" for="emergency_contact_phone">Contact number</label>
      <input class="form__input" type="text" id="emergency_contact_phone" name="emergency_contact_phone" value="<?= e($target['emergency_contact_phone'] ?? '') ?>" pattern="\d{10}" maxlength="10" required>
    </div>

    <div class="field field--action">
      <button type="submit" class="btn btn--primary">Save changes</button>
    </div>
  </form>
</section>

<section class="panel panel--pad">
  <div class="panel__head panel__head--plain">
    <h2 class="panel__title">Identity documents</h2>
  </div>
  <?php if (!$docsDone): ?>
    <div class="notice">
      <strong>Waiting on the member.</strong>
      <p>They'll be prompted to upload their profile photo and ID documents the first time they sign in.</p>
    </div>
  <?php else: ?>
    <div class="doc-grid">
      <?php foreach (\App\Models\User::DOCUMENT_FIELDS as $field): ?>
        <a class="doc-tile" target="_blank" rel="noopener"
           href="<?= url('/account/document?id=' . (int) $target['id'] . '&field=' . $field) ?>">
          <?= e(ucwords(str_replace('_', ' ', $field))) ?>
          <span>View &rarr;</span>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>
