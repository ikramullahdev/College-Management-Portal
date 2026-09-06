<?php $pageTitle='IMCB F-10/4 | Academic Portal'; require 'includes/header.php'; ?>
<main class="container py-5">
<section class="hero shadow-sm mb-5">
<div class="row align-items-center">
<div class="col-lg-8"><span class="badge text-bg-primary mb-3">Government Institution</span><h1 class="display-5 fw-bold">IMCB F-10/4</h1><p class="lead">College & BS/ADP Student Academic Portal</p><p class="text-muted">Islamabad Model College for Boys, F-10/4. Academic services for HSSC, BS and ADP students.</p><a class="btn btn-primary btn-lg" href="admission.php">Open Student Portal</a></div>
<div class="col-lg-4 text-center"><div class="rounded-circle bg-white shadow p-5 d-inline-block"><strong class="fs-1" style="color:#173f68">IMCB</strong></div></div>
</div></section>
<h2 class="section-title mb-4">Academic Programs</h2>
<div class="row g-4 mb-5">
<?php $groups=['HSSC'=>['Pre-Engineering','Pre-Medical','General Science','Humanities','Commerce'],'BS'=>['BS English','BS Statistics — Specialization in Data Science','BS Urdu'],'ADP'=>['ADP Arts','ADP Science']]; foreach($groups as $type=>$items): ?>
<div class="col-lg-4"><div class="card program-card shadow-sm h-100"><div class="card-header fw-bold" style="background:#ffd447"><?= $type ?></div><div class="card-body"><ul class="mb-0"><?php foreach($items as $item): ?><li class="mb-2"><?= htmlspecialchars($item) ?></li><?php endforeach; ?></ul></div></div></div>
<?php endforeach; ?>
</div>
<h2 class="section-title mb-4">Portal Services</h2><div class="row g-3"><div class="col-md-3"><div class="card p-3 shadow-sm">Attendance</div></div><div class="col-md-3"><div class="card p-3 shadow-sm">Results & GPA/CGPA</div></div><div class="col-md-3"><div class="card p-3 shadow-sm">Timetable</div></div><div class="col-md-3"><div class="card p-3 shadow-sm">Notices</div></div></div>
</main><?php require 'includes/footer.php'; ?>
