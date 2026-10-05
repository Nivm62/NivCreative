<?php
use Nivc\Core\I18n;
?><!doctype html>
<html lang="<?= e(I18n::locale()) ?>" dir="<?= e(I18n::dir()) ?>">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?= e($status) ?> · NivCreative</title>
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="auth-body">
<main class="error-page">
  <div class="error-code"><?= e($status) ?></div>
  <h1><?= e($status === 404 ? t('error.not_found_title') : t('error.generic_title')) ?></h1>
  <p><?= e($message) ?></p>
  <a class="btn btn-primary" href="<?= e(url('/')) ?>"><?= e(t('error.back_home')) ?></a>
</main>
</body>
</html>
