<?php
pageHeading('YOUR BUSINESS IDENTITY', 'My profile', 'Update your account and company details.');
$values = $error && ($_POST['action'] ?? '') === 'save-profile' ? $_POST : $user;
?>
<section class="panel editor">
<div class="section-heading"><div><h2><?= e($user['name']) ?></h2><p class="muted"><?= e($user['business_name'] ?: 'Add your company name below') ?></p></div><?= badge($user['role']) ?></div>
<form method="post" class="form-stack"><?= csrf() ?><input type="hidden" name="action" value="save-profile">
<div class="form-grid">
<label>Full name<input name="name" autocomplete="name" maxlength="120" required value="<?= e($values['name'] ?? '') ?>"></label>
<label>Company name<input name="business_name" autocomplete="organization" maxlength="160" required value="<?= e($values['business_name'] ?? '') ?>"></label>
<label>Email address<input type="email" name="email" autocomplete="email" maxlength="190" required value="<?= e($values['email'] ?? '') ?>"></label>
<label>Current password (only for email changes)<input type="password" name="current_password" autocomplete="current-password"></label>
</div><p class="field-help">Your company name appears on your business profile and marketplace orders. Enter your current password when changing your sign-in email.</p>
<button class="button">Save profile</button></form></section>
