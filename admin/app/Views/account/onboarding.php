<?php /** @var string $csrf @var array $user */ ?>
<header class="page-head">
  <div>
    <h1 class="page-head__title">Complete your profile</h1>
    <p class="page-head__sub">Before you can use the panel, upload your photo and ID documents — just like a
      standard employee onboarding. These can't be changed once uploaded, so double-check each file first.</p>
  </div>
</header>

<section class="panel panel--pad">
  <form class="form-grid" method="post" action="<?= url('/account/onboarding') ?>" enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">

    <div class="field">
      <label class="form__label" for="profile_photo">Profile photo</label>
      <input class="form__input" type="file" id="profile_photo" name="profile_photo" accept=".jpg,.jpeg,.png" required>
      <span class="field__hint">JPG or PNG, up to 5MB.</span>
    </div>
    <div class="field"></div>

    <div class="field">
      <label class="form__label" for="aadhar_front">Aadhar card — front</label>
      <input class="form__input" type="file" id="aadhar_front" name="aadhar_front" accept=".jpg,.jpeg,.png,.pdf" required>
      <span class="field__hint">JPG, PNG or PDF, up to 5MB.</span>
    </div>
    <div class="field">
      <label class="form__label" for="aadhar_back">Aadhar card — back</label>
      <input class="form__input" type="file" id="aadhar_back" name="aadhar_back" accept=".jpg,.jpeg,.png,.pdf" required>
      <span class="field__hint">JPG, PNG or PDF, up to 5MB.</span>
    </div>

    <div class="field">
      <label class="form__label" for="pan_card_image">PAN card</label>
      <input class="form__input" type="file" id="pan_card_image" name="pan_card_image" accept=".jpg,.jpeg,.png,.pdf" required>
      <span class="field__hint">JPG, PNG or PDF, up to 5MB.</span>
    </div>
    <div class="field"></div>

    <div class="field field--action">
      <button type="submit" class="btn btn--primary">Upload &amp; continue</button>
    </div>
  </form>
</section>
