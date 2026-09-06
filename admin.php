<?php
session_start();
require 'config/database.php';
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') { header('Location: login.php'); exit; }
$u=$_SESSION['user']; $pageTitle='Admin Dashboard';
function countRows($pdo,$table){ return (int)$pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn(); }
$stats=[
 'students'=>countRows($pdo,'students'),'teachers'=>countRows($pdo,'teachers'),'programs'=>countRows($pdo,'programs'),
 'subjects'=>countRows($pdo,'subjects'),'notices'=>countRows($pdo,'notices'),'applications'=>countRows($pdo,'admission_applications')
];
$recent=$pdo->query('SELECT title,audience,published_at FROM notices ORDER BY published_at DESC LIMIT 5')->fetchAll();
require 'includes/header.php';
?>
<div class="container-fluid"><div class="row">
<aside class="col-md-2 sidebar py-4"><h6 class="px-3 text-muted">ADMIN MENU</h6>
<a class="active" href="admin.php">Dashboard</a><a href="students.php">Students</a><a href="teachers.php">Teachers</a><a href="teacher_subjects.php">Teacher Subjects</a><a href="admin/admissions.php">Admissions & Merit</a><a href="admin/registration.php">Student Registration</a><a href="admin/fees.php">Fee Management</a><a href="admin/documents.php">Document Verification</a><a href="admin/id_cards.php">Student ID Cards</a><a href="admin/certificates.php">Certificates</a><a href="programs.php">Programs</a><a href="academic.php" class="btn btn-primary">Academic Management</a><a href="academic_structure.php">Academic Structure</a><a href="subjects.php">Subjects</a><a href="admin/results.php">Results & GPA</a><a href="notices.php">Notices</a><a href="dashboard.php">My Portal</a><a href="logout.php">Logout</a></aside>
<main class="col-md-10 p-4"><h2 class="fw-bold">Admin Dashboard</h2><p class="text-muted">IMCB F-10/4 · College & BS/ADP Portal</p>
<div class="row g-3 my-2">
<?php foreach([['Students',$stats['students'],'students.php'],['Teachers',$stats['teachers'],'teachers.php'],['Programs',$stats['programs'],'programs.php'],['Subjects',$stats['subjects'],'subjects.php'],['Notices',$stats['notices'],'notices.php'],['Applications',$stats['applications'],'admin/admissions.php']] as $c): ?>
<div class="col-lg col-md-4"><a class="text-decoration-none text-dark" href="<?= $c[2] ?>"><div class="card stat-card shadow-sm p-3 h-100"><small><?= $c[0] ?></small><h2 class="mb-0"><?= $c[1] ?></h2></div></a></div>
<?php endforeach; ?></div>
<div class="card shadow-sm mt-4"><div class="card-body"><div class="d-flex justify-content-between"><h5>Recent Notices</h5><a href="notices.php" class="btn btn-sm btn-primary">Manage Notices</a></div><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Title</th><th>Audience</th><th>Published</th></tr></thead><tbody><?php foreach($recent as $n): ?><tr><td><?=htmlspecialchars($n['title'])?></td><td><?=htmlspecialchars(strtoupper($n['audience']))?></td><td><?=htmlspecialchars($n['published_at'])?></td></tr><?php endforeach; if(!$recent): ?><tr><td colspan="3" class="text-muted">No notices yet.</td></tr><?php endif;?></tbody></table></div></div></div>
</main></div></div><?php require 'includes/footer.php'; ?>
