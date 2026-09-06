<?php if (session_status() === PHP_SESSION_NONE) session_start(); ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($pageTitle ?? 'IMCB F-10/4 Portal') ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<nav class="navbar navbar-expand-lg portal-nav shadow-sm">
<div class="container-fluid px-4">
<a class="navbar-brand fw-bold text-white" href="index.php">IMCB F-10/4 <small class="d-block fw-normal">Student & Academic Portal</small></a>
<div class="ms-auto"><a href="login.php" class="btn btn-light btn-sm px-3">Login</a></div>
</div>
</nav>
