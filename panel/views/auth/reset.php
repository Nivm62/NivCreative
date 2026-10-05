<h1><?= e(t('auth.reset_title')) ?></h1>
<p class="auth-sub"><?= e(t('validation.password_rule')) ?></p>
<?php if (!empty($error)): ?><div class="alert alert-danger" role="alert"><?= e($error) ?></div><?php endif; ?>
<form method="post" action="<?= e(url('/reset/' . rawurlencode($token))) ?>" class="form" novalidate>
  <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
  <label class="field"><span class="label"><?= e(t('auth.new_password')) ?></span>
    <span class="input-wrap"><input type="password" name="password" autocomplete="new-password" required dir="ltr"><svg class="i"><use href="#i-lock"/></svg></span></label>
  <label class="field"><span class="label"><?= e(t('auth.repeat_password')) ?></span>
    <span class="input-wrap"><input type="password" name="password2" autocomplete="new-password" required dir="ltr"><svg class="i"><use href="#i-lock"/></svg></span></label>
  <button class="btn btn-primary btn-block btn-lg" type="submit"><?= e(t('auth.save_password')) ?></button>
</form>
