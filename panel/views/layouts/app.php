<?php
use Nivc\Core\I18n;
$locale = I18n::locale();
$other  = $locale === 'he' ? 'en' : 'he';
$initial = mb_strtoupper(mb_substr($user->name, 0, 1));
$titleKeys = ['admin-dashboard' => 'dashboard', 'client-dashboard' => 'dashboard', 'admin-clients' => 'clients', 'leads' => 'leads',
    'landing-pages' => $area === 'admin' ? 'landing_pages' : 'landing_page', 'admin-websites' => 'websites', 'analytics' => 'analytics',
    'admin-billing' => 'billing', 'notifications' => 'notifications', 'admin-settings' => 'settings', 'account' => 'account', 'support' => 'support'];
?><!doctype html>
<html lang="<?= e($locale) ?>" dir="<?= e(I18n::dir()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<meta name="csrf" content="<?= e($boot['csrf']) ?>">
<title><?= e(t('nav.' . ($titleKeys[$page] ?? 'dashboard'))) ?> · <?= e($company) ?></title>
<link rel="icon" type="image/webp" href="<?= e(asset('img/logo-mark.webp')) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Heebo:wght@400;500;600;700;800&display=swap">
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="app-body area-<?= e($area) ?>">
<?php require __DIR__ . '/../partials/icons.php'; ?>
<a class="skip-link" href="#view"><?= e(t('common.skip_to_content')) ?></a>
<div class="app-shell">
  <aside class="sidebar" id="sidebar" aria-label="<?= e(t('common.main_navigation')) ?>">
    <div class="sidebar-brand">
      <img src="<?= e(asset('img/logo-mark.webp')) ?>" alt="" width="36" height="36">
      <span class="brand-word">NivCreative</span>
      <button class="icon-btn sidebar-close" type="button" data-close-sidebar aria-label="<?= e(t('common.close')) ?>"><svg class="i"><use href="#i-x"/></svg></button>
    </div>
    <nav class="nav">
      <?php foreach ($nav as [$key, $path, $icon]): $href = url($path); $active = ($page === 'admin-dashboard' && $key === 'dashboard' && $area === 'admin') ?: false; ?>
        <a class="nav-item" href="<?= e($href) ?>" data-nav="<?= e($path) ?>"<?= $key === 'notifications' ? ' data-nav-notif' : '' ?>>
          <svg class="i"><use href="#i-<?= e($icon) ?>"/></svg><span><?= e(t('nav.' . $key)) ?></span>
          <?php if ($key === 'notifications'): ?><em class="nav-badge" data-unread<?= $unread ? '' : ' hidden' ?>><?= e($unread) ?></em><?php endif; ?>
        </a>
      <?php endforeach; ?>
    </nav>
    <div class="sidebar-foot">
      <?php if ($area === 'admin'): ?>
        <div class="promo">
          <svg class="i promo-ico"><use href="#i-crown"/></svg>
          <strong>NivCreative Pro</strong>
          <p><?= e(t('nav.promo_text')) ?></p>
          <button type="button" class="btn btn-primary btn-block" data-action="add-client"><svg class="i"><use href="#i-plus"/></svg><?= e(t('client.add')) ?></button>
        </div>
      <?php endif; ?>
      <form method="post" action="<?= e(url('/logout')) ?>" class="logout-form">
        <input type="hidden" name="_csrf" value="<?= e($boot['csrf']) ?>">
        <div class="user-box">
          <span class="avatar"><?= e($initial) ?></span>
          <span class="user-meta"><b><?= e($user->name) ?></b><small><?= e(t('role.' . $user->role)) ?></small></span>
          <button class="icon-btn" type="submit" aria-label="<?= e(t('nav.logout')) ?>" title="<?= e(t('nav.logout')) ?>"><svg class="i"><use href="#i-logout"/></svg></button>
        </div>
      </form>
    </div>
  </aside>
  <div class="sidebar-scrim" data-close-sidebar></div>

  <div class="app-main">
    <header class="topbar">
      <button class="icon-btn menu-btn" type="button" data-open-sidebar aria-label="<?= e(t('common.menu')) ?>" aria-controls="sidebar"><svg class="i"><use href="#i-menu"/></svg></button>
      <div class="gsearch" role="search">
        <svg class="i"><use href="#i-search"/></svg>
        <input type="search" id="gsearch" placeholder="<?= e(t('common.global_search')) ?>" aria-label="<?= e(t('common.global_search')) ?>" autocomplete="off">
        <div class="gsearch-results" id="gsearch-results" hidden></div>
      </div>
      <div class="topbar-actions">
        <form method="post" action="<?= e(url('/set-language')) ?>" data-lang-form class="lang-switch"><input type="hidden" name="_csrf" value="<?= e($boot['csrf']) ?>">
          <button type="button" class="lang-btn" data-set-lang="<?= e($other) ?>" aria-label="<?= e(t('common.switch_language')) ?>"><svg class="i"><use href="#i-globe2"/></svg><?= e($other === 'en' ? 'EN' : 'עב') ?></button></form>
        <div class="notif-wrap">
          <button class="icon-btn bell" type="button" id="bell" aria-haspopup="true" aria-expanded="false" aria-label="<?= e(t('nav.notifications')) ?>"><svg class="i"><use href="#i-bell"/></svg><em class="bell-badge" data-unread<?= $unread ? '' : ' hidden' ?>><?= e($unread) ?></em></button>
          <div class="notif-pop" id="notif-pop" hidden></div>
        </div>
        <a class="user-chip" href="<?= e($area === 'admin' ? url('/admin/settings') : url('/account')) ?>"><span class="avatar"><?= e($initial) ?></span><span class="user-meta"><b><?= e($user->name) ?></b><small><?= e(t('role.' . $user->role)) ?></small></span></a>
      </div>
    </header>
    <main id="view" class="view" tabindex="-1">
      <div class="loading-block" role="status"><span class="spinner"></span><?= e(t('common.loading')) ?></div>
      <noscript><p class="alert alert-danger"><?= e(t('common.js_required')) ?></p></noscript>
    </main>
  </div>
</div>
<div id="overlay-root"></div>
<script type="application/json" id="boot"><?= json_encode($boot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<script src="<?= e(asset('js/core.js')) ?>" defer></script>
<script src="<?= e(asset('js/charts.js')) ?>" defer></script>
<?php if (in_array($page, ['leads', 'admin-dashboard', 'client-dashboard'], true)): ?><script src="<?= e(asset('js/lead-drawer.js')) ?>" defer></script><?php endif; ?>
<script src="<?= e(asset('js/pages/' . $page . '.js')) ?>" defer></script>
</body>
</html>
