<?php
use Nivc\Core\I18n;
$locale = I18n::locale();
$other  = $locale === 'he' ? 'en' : 'he';
?><!doctype html>
<html lang="<?= e($locale) ?>" dir="<?= e(I18n::dir()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<meta name="csrf" content="<?= e($csrf) ?>">
<title><?= e($title) ?> · NivCreative</title>
<link rel="icon" type="image/webp" href="<?= e(asset('img/logo-mark.webp')) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Heebo:wght@400;500;600;700;800&display=swap">
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="auth-body">
<?php require __DIR__ . '/../partials/icons.php'; ?>
<div class="auth-shell">
  <aside class="auth-hero" aria-hidden="true">
    <div class="auth-hero-top">
      <img src="<?= e(asset('img/logo-mark.webp')) ?>" alt="" width="48" height="48"><span class="brand-word">NivCreative</span>
    </div>
    <p class="auth-tagline"><?= e(t('auth.tagline')) ?></p>
    <div class="auth-hero-copy">
      <h2><?= e(t('auth.hero_title')) ?></h2>
      <p><?= e(t('auth.hero_text')) ?></p>
    </div>
    <div class="auth-preview">
      <div class="pv-card pv-a"><span><?= e(t('auth.pv_leads')) ?></span><b>1,248</b><i>+12%</i></div>
      <div class="pv-card pv-b"><span><?= e(t('auth.pv_new')) ?></span><b>312</b><i>+8%</i></div>
      <div class="pv-card pv-c"><span><?= e(t('auth.pv_rate')) ?></span><b>24%</b><i>+3%</i></div>
      <svg class="pv-chart" viewBox="0 0 320 110" preserveAspectRatio="none"><defs><linearGradient id="pvg" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#8b7bff" stop-opacity=".45"/><stop offset="1" stop-color="#8b7bff" stop-opacity="0"/></linearGradient></defs><path d="M0 85 C30 70 45 78 70 62 S110 40 140 52 190 70 215 40 270 30 320 12 V110 H0Z" fill="url(#pvg)"/><path d="M0 85 C30 70 45 78 70 62 S110 40 140 52 190 70 215 40 270 30 320 12" fill="none" stroke="#a99bff" stroke-width="3" stroke-linecap="round"/></svg>
    </div>
    <ul class="auth-chips">
      <li><svg class="i"><use href="#i-chart"/></svg><?= e(t('auth.chip_tracking')) ?></li>
      <li><svg class="i"><use href="#i-zap"/></svg><?= e(t('auth.chip_leads')) ?></li>
      <li><svg class="i"><use href="#i-target"/></svg><?= e(t('auth.chip_growth')) ?></li>
    </ul>
  </aside>
  <main class="auth-main">
    <div class="auth-top">
      <?php if (empty($install)): ?>
        <a class="auth-back" href="https://nivcreative.com"><svg class="i"><use href="#i-home"/></svg><?= e(t('auth.back_to_site')) ?></a>
        <form method="post" action="<?= e(url('/set-language')) ?>" class="lang-switch" data-lang-form>
          <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
          <button type="button" class="lang-btn" data-set-lang="<?= e($other) ?>" aria-label="<?= e(t('common.switch_language')) ?>"><svg class="i"><use href="#i-globe2"/></svg><?= e($other === 'en' ? 'English' : 'עברית') ?></button>
        </form>
      <?php endif; ?>
    </div>
    <div class="auth-card">
      <img class="auth-logo" src="<?= e(asset('img/logo-full.webp')) ?>" alt="NivCreative" width="220" height="48">
      <?php require __DIR__ . '/../' . $view . '.php'; ?>
    </div>
  </main>
</div>
<script src="<?= e(asset('js/auth.js')) ?>" defer></script>
</body>
</html>
