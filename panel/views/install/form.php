<h1><?= e(t('install.title')) ?></h1>
<p class="auth-sub"><?= e(t('install.sub')) ?></p>
<?php if (!empty($errors['general'])): ?><div class="alert alert-danger" role="alert"><?= e($errors['general']) ?></div><?php endif; ?>
<form method="post" class="form" novalidate>
  <h3 class="form-h"><?= e(t('install.database')) ?></h3>
  <div class="grid-2">
    <label class="field"><span class="label"><?= e(t('install.db_host')) ?></span><input type="text" name="db_host" value="<?= e($vals['db_host']) ?>" dir="ltr" required></label>
    <label class="field"><span class="label"><?= e(t('install.db_port')) ?></span><input type="text" name="db_port" value="<?= e($vals['db_port']) ?>" dir="ltr" required></label>
  </div>
  <label class="field"><span class="label"><?= e(t('install.db_name')) ?></span><input type="text" name="db_name" value="<?= e($vals['db_name']) ?>" dir="ltr" required></label>
  <div class="grid-2">
    <label class="field"><span class="label"><?= e(t('install.db_user')) ?></span><input type="text" name="db_user" value="<?= e($vals['db_user']) ?>" dir="ltr" required></label>
    <label class="field"><span class="label"><?= e(t('install.db_pass')) ?></span><input type="password" name="db_pass" dir="ltr" autocomplete="off"></label>
  </div>
  <h3 class="form-h"><?= e(t('install.admin')) ?></h3>
  <label class="field"><span class="label"><?= e(t('install.admin_name')) ?></span><input type="text" name="admin_name" value="<?= e($vals['admin_name']) ?>" required>
    <?php if (!empty($errors['admin_name'])): ?><small class="err"><?= e($errors['admin_name']) ?></small><?php endif; ?></label>
  <label class="field"><span class="label"><?= e(t('auth.email')) ?></span><input type="email" name="admin_email" value="<?= e($vals['admin_email']) ?>" dir="ltr" required>
    <?php if (!empty($errors['admin_email'])): ?><small class="err"><?= e($errors['admin_email']) ?></small><?php endif; ?></label>
  <label class="field"><span class="label"><?= e(t('auth.password')) ?></span><input type="password" name="admin_pass" dir="ltr" autocomplete="new-password" required>
    <?php if (!empty($errors['admin_pass'])): ?><small class="err"><?= e($errors['admin_pass']) ?></small><?php endif; ?></label>
  <label class="field"><span class="label"><?= e(t('install.site_url')) ?></span><input type="url" name="site_url" value="<?= e($vals['site_url']) ?>" dir="ltr" placeholder="https://nivcreative.com/panel"><small class="hint"><?= e(t('install.site_url_hint')) ?></small></label>
  <button class="btn btn-primary btn-block btn-lg" type="submit"><?= e(t('install.run')) ?></button>
</form>
