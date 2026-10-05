<h1><?= e(t('auth.forgot_title')) ?></h1>
<p class="auth-sub"><?= e(t('auth.forgot_sub')) ?></p>
<?php if (!empty($sent)): ?><div class="alert alert-success" role="status"><?= e(t('auth.forgot_sent')) ?></div><?php endif; ?>
<form method="post" action="<?= e(url('/forgot')) ?>" class="form" novalidate>
  <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
  <label class="field"><span class="label"><?= e(t('auth.email')) ?></span>
    <span class="input-wrap"><input type="email" name="email" required dir="ltr" autocomplete="email"><svg class="i"><use href="#i-mail"/></svg></span></label>
  <button class="btn btn-primary btn-block btn-lg" type="submit"><?= e(t('auth.send_reset')) ?></button>
</form>
<p class="auth-foot"><a class="link" href="<?= e(url('/login')) ?>"><?= e(t('auth.back_to_login')) ?></a></p>
