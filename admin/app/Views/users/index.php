<?php /** @var string $csrf @var array $users @var int $meId */ ?>
<?php $userModel = new \App\Models\User(); ?>
<header class="page-head">
  <div>
    <h1 class="page-head__title">Admin Management</h1>
    <p class="page-head__sub">Add team members with a full hire record and control who can do what.</p>
  </div>
</header>

<section class="panel panel--pad">
  <div class="panel__head panel__head--plain">
    <h2 class="panel__title">Add a team member</h2>
  </div>
  <p class="form-intro">Just like a company onboarding a new hire, every field below is required up front.
    Once saved, only an admin can change a member's email, mobile numbers, address, Aadhar number or PAN
    number — the member themselves cannot edit those later.</p>

  <form class="form-grid" method="post" action="<?= url('/users/create') ?>">
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">

    <p class="form-grid__section">Account</p>
    <div class="field">
      <label class="form__label" for="name">Full name</label>
      <input class="form__input" type="text" id="name" name="name" placeholder="Jane Doe" required>
    </div>
    <div class="field">
      <label class="form__label" for="role">Role</label>
      <select class="form__input" id="role" name="role">
        <option value="manager">Manager — day-to-day access, can’t manage users or change own password</option>
        <option value="admin">Admin — full access</option>
      </select>
    </div>
    <div class="field">
      <label class="form__label" for="email">Email</label>
      <input class="form__input" type="email" id="email" name="email" placeholder="jane@example.com" required>
    </div>
    <div class="field">
      <label class="form__label" for="password">Temporary password</label>
      <input class="form__input" type="text" id="password" name="password" placeholder="At least 8 characters" minlength="8" required>
      <span class="field__hint">Share this with them; they can keep it (managers can’t change it themselves).</span>
    </div>

    <p class="form-grid__section">Contact &amp; identity — locked once saved</p>
    <div class="field">
      <label class="form__label" for="phone">Mobile number</label>
      <input class="form__input" type="text" id="phone" name="phone" placeholder="10-digit mobile number" pattern="\d{10}" maxlength="10" required>
    </div>
    <div class="field">
      <label class="form__label" for="alternate_phone">Alternate mobile number</label>
      <input class="form__input" type="text" id="alternate_phone" name="alternate_phone" placeholder="10-digit alternate number" pattern="\d{10}" maxlength="10" required>
    </div>
    <div class="field field--wide">
      <label class="form__label" for="address">Address</label>
      <textarea class="form__input" id="address" name="address" rows="2" placeholder="Full residential address" required></textarea>
    </div>
    <div class="field">
      <label class="form__label" for="aadhar_number">Aadhar number</label>
      <input class="form__input" type="text" id="aadhar_number" name="aadhar_number" placeholder="12-digit Aadhar number" pattern="\d{12}" maxlength="12" required>
    </div>
    <div class="field">
      <label class="form__label" for="pan_number">PAN number</label>
      <input class="form__input" type="text" id="pan_number" name="pan_number" placeholder="ABCDE1234F" maxlength="10" style="text-transform:uppercase" required>
    </div>

    <p class="form-grid__section">Employment</p>
    <div class="field">
      <label class="form__label" for="designation">Designation</label>
      <input class="form__input" type="text" id="designation" name="designation" placeholder="e.g. Operations Manager" required>
    </div>
    <div class="field">
      <label class="form__label" for="date_of_joining">Date of joining</label>
      <input class="form__input" type="date" id="date_of_joining" name="date_of_joining" required>
    </div>
    <div class="field">
      <label class="form__label" for="date_of_birth">Date of birth</label>
      <input class="form__input" type="date" id="date_of_birth" name="date_of_birth" required>
    </div>

    <p class="form-grid__section">Emergency contact</p>
    <div class="field">
      <label class="form__label" for="emergency_contact_name">Contact name</label>
      <input class="form__input" type="text" id="emergency_contact_name" name="emergency_contact_name" placeholder="Full name" required>
    </div>
    <div class="field">
      <label class="form__label" for="emergency_contact_phone">Contact number</label>
      <input class="form__input" type="text" id="emergency_contact_phone" name="emergency_contact_phone" placeholder="10-digit mobile number" pattern="\d{10}" maxlength="10" required>
    </div>

    <div class="field field--action">
      <button type="submit" class="btn btn--primary">Add member</button>
    </div>
  </form>

  <div class="notice" style="margin-top:18px">
    <strong>Photo &amp; ID documents come next.</strong>
    <p>Profile photo, Aadhar card (front &amp; back) and PAN card are uploaded by the member themselves —
    they'll be required to add these the first time they sign in, and can't be changed once uploaded.</p>
  </div>
</section>

<section class="panel">
  <div class="panel__head">
    <h2 class="panel__title">Team members</h2>
    <span class="panel__count"><?= count($users) ?> total</span>
  </div>

  <table class="table">
    <thead>
      <tr><th>Name</th><th>Email</th><th>Role</th><th>Documents</th><th>Added</th><th class="ta-right">Actions</th></tr>
    </thead>
    <tbody>
      <?php foreach ($users as $u): ?>
        <?php $isSelf = ((int) $u['id'] === (int) $meId); ?>
        <?php $docsDone = $userModel->hasCompletedDocuments($u); ?>
        <tr>
          <td>
            <div class="user-cell">
              <span class="avatar"><?= e(strtoupper(substr($u['name'], 0, 1))) ?></span>
              <span><?= e($u['name']) ?><?php if ($isSelf): ?> <span class="tag-you">you</span><?php endif; ?></span>
            </div>
          </td>
          <td><?= e($u['email']) ?></td>
          <td><span class="role-badge role-badge--<?= e($u['role']) ?>"><?= e(ucfirst($u['role'])) ?></span></td>
          <td>
            <?php if ($docsDone): ?>
              <span class="badge badge--won">Complete</span>
            <?php else: ?>
              <span class="badge badge--contacted">Pending</span>
            <?php endif; ?>
          </td>
          <td class="muted"><?= e(date('M j, Y', strtotime($u['created_at']))) ?></td>
          <td class="ta-right">
            <div class="row-actions">
              <a class="btn btn--sm btn--ghost" href="<?= url('/users/edit?id=' . (int) $u['id']) ?>">Edit</a>

              <!-- Reset password -->
              <form method="post" action="<?= url('/users/reset-password') ?>" class="inline-form"
                    onsubmit="return promptPassword(this, '<?= e($u['name']) ?>')">
                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                <input type="hidden" name="password" value="">
                <button type="submit" class="btn btn--sm btn--ghost">Reset password</button>
              </form>

              <!-- Delete -->
              <?php if (!$isSelf): ?>
                <form method="post" action="<?= url('/users/delete') ?>" class="inline-form"
                      onsubmit="return confirm('Remove <?= e($u['name']) ?>? This cannot be undone.')">
                  <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                  <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                  <button type="submit" class="btn btn--sm btn--danger">Delete</button>
                </form>
              <?php endif; ?>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</section>

<script>
  // Ask for the new password in the browser, then submit the hidden field.
  function promptPassword(form, name) {
    var pw = window.prompt('New password for ' + name + ' (min 8 characters):');
    if (pw === null) return false;            // cancelled
    if (pw.length < 8) { alert('Password must be at least 8 characters.'); return false; }
    form.password.value = pw;
    return true;
  }
</script>
