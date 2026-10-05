<h1><?= e(t('auth.login_title')) ?></h1>
<p class="auth-sub"><?= e(t('auth.login_sub')) ?></p>
<?php if (!empty($error)): ?><div class="alert alert-danger" role="alert"><?= e($error) ?></div><?php endif; ?>
<form method="post" action="<?= e(url('/login')) ?>" class="form" autocomplete="on" novalidate>
  <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
  <input type="hidden" name="next" value="<?= e($next) ?>">
  <label class="field"><span class="label"><?= e(t('auth.email')) ?></span>
    <span class="input-wrap"><input type="email" name="email" value="<?= e($email ?? '') ?>" placeholder="example@company.co.il" autocomplete="username" required dir="ltr"><svg class="i"><use href="#i-mail"/></svg></span></label>
  <label class="field"><span class="label"><?= e(t('auth.password')) ?></span>
    <span class="input-wrap"><input type="password" name="password" id="pw" autocomplete="current-password" required dir="ltr"><svg class="i"><use href="#i-lock"/></svg>
    <button type="button" class="pw-toggle" data-toggle-pw="pw" aria-label="<?= e(t('auth.show_password')) ?>"><svg class="i"><use href="#i-eye"/></svg></button></span></label>
  <div class="row-between">
    <label class="check"><input type="checkbox" name="remember" value="1" checked><span><?= e(t('auth.remember')) ?></span></label>
    <a class="link" href="<?= e(url('/forgot')) ?>"><?= e(t('auth.forgot')) ?></a>
  </div>
  <button class="btn btn-primary btn-block btn-lg" type="submit"><?= e(t('auth.login')) ?><svg class="i flip-rtl"><use href="#i-arrow-left"/></svg></button>
</form>
<div class="auth-secure"><svg class="i"><use href="#i-lock"/></svg><span><?= e(t('auth.secure_note')) ?></span></div>
<div class="auth-support">
  <div class="auth-support-ico"><svg class="i"><use href="#i-help"/></svg><span><?= e(t('auth.support')) ?></span></div>
  <div><strong><?= e(t('auth.no_access')) ?></strong><br>
    <a class="link" href="<?= !empty($supportWa) ? 'https://wa.me/' . e(preg_replace('/\D+/', '', $supportWa)) : 'mailto:' . e($supportEmail) ?>"><?= e(t('auth.contact_nivcreative')) ?></a></div>
</div>
