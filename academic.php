<?php
session_start(); require 'config/database.php'; require 'lib_security.php'; require 'lib_academic.php';
if(!isset($_SESSION['user'])||$_SESSION['user']['role']!=='admin'){header('Location: login.php');exit;}
$msg=''; $error='';
if($_SERVER['REQUEST_METHOD']==='POST'){ verifyCsrf();
  $action=$_POST['action']??'';
  try {
    if($action==='section'){
      $name=trim($_POST['name']); $pid=(int)$_POST['program_id']; $term=trim($_POST['term']); $year=trim($_POST['academic_year']);
      $pdo->prepare('INSERT INTO sections(name,program_id,term,academic_year) VALUES(?,?,?,?)')->execute([$name,$pid,$term,$year]); $msg='Section created.';
    } elseif($action==='enroll') {
      $sid=(int)$_POST['student_id']; $section=(int)$_POST['section_id'];
      $q=$pdo->prepare('SELECT st.id,st.program_id,st.semester_or_year FROM students st WHERE st.id=?'); $q->execute([$sid]); $st=$q->fetch();
      $q=$pdo->prepare('SELECT * FROM sections WHERE id=?'); $q->execute([$section]); $sec=$q->fetch();
      if(!$st||!$sec||$st['program_id']!=$sec['program_id']) throw new Exception('Student and section program must match.');
      $pdo->prepare('UPDATE students SET section_id=? WHERE id=?')->execute([$section,$sid]);
      $count=$pdo->prepare('INSERT IGNORE INTO enrollments(student_id,subject_id) SELECT ?,id FROM subjects WHERE program_id=? AND (semester IS NULL OR semester=? OR semester="" )');
      $count->execute([$sid,$st['program_id'],$sec['term']]); $msg='Student assigned to section and matching subjects enrolled.';
    } elseif($action==='auto') {
      $pid=(int)$_POST['program_id']; $term=trim($_POST['term']);
      $q=$pdo->prepare('SELECT id FROM subjects WHERE program_id=? AND (semester IS NULL OR semester=? OR semester="")'); $q->execute([$pid,$term]); $subjects=$q->fetchAll(PDO::FETCH_COLUMN);
      $q=$pdo->prepare('SELECT id FROM students WHERE program_id=? AND semester_or_year=?'); $q->execute([$pid,$term]); $students=$q->fetchAll(PDO::FETCH_COLUMN);
      $ins=$pdo->prepare('INSERT IGNORE INTO enrollments(student_id,subject_id) VALUES(?,?)'); $n=0;
      foreach($students as $sid) foreach($subjects as $sub){$ins->execute([$sid,$sub]);$n+= $ins->rowCount();}
      $msg="$n enrollment records created/confirmed.";
    }
  } catch(Throwable $e){$error=$e->getMessage();}
}
$programs=$pdo->query('SELECT * FROM programs WHERE active=1 ORDER BY FIELD(level,"HSSC","BS","ADP"),name')->fetchAll();
$sections=$pdo->query('SELECT s.*,p.level,p.name program_name,COUNT(st.id) student_count FROM sections s JOIN programs p ON p.id=s.program_id LEFT JOIN students st ON st.section_id=s.id GROUP BY s.id ORDER BY p.level,p.name,s.academic_year DESC,s.term,s.name')->fetchAll();
$students=$pdo->query('SELECT st.id,st.roll_no,u.name,p.level,p.name program_name,st.semester_or_year FROM students st JOIN users u ON u.id=st.user_id JOIN programs p ON p.id=st.program_id ORDER BY u.name')->fetchAll();
$pageTitle='Academic Management'; require 'includes/header.php';
?>
<div class="container py-4">
<div class="d-flex justify-content-between align-items-center"><div><h2 class="fw-bold">Academic Management</h2><p class="text-muted mb-0">Programs, sections, semesters and student enrollment</p></div><a href="admin.php" class="btn btn-outline-secondary">Admin Dashboard</a></div>
<?php if($msg):?><div class="alert alert-success mt-3"><?=htmlspecialchars($msg)?></div><?php endif;?><?php if($error):?><div class="alert alert-danger mt-3"><?=htmlspecialchars($error)?></div><?php endif;?>
<div class="row g-3 my-2">
<div class="col-lg-6"><div class="card shadow-sm border-0 h-100"><div class="card-body"><h5>Create Class/Section</h5><form method="post"><?php echo csrfField(); ?><input type="hidden" name="action" value="section"><label class="form-label">Program</label><select name="program_id" class="form-select mb-2" required><?php foreach($programs as $p):?><option value="<?=$p['id']?>"><?=$p['level']?> — <?=htmlspecialchars($p['name'])?></option><?php endforeach;?></select><div class="row g-2"><div class="col-md-4"><input name="name" class="form-control" placeholder="Section (A/B)" required></div><div class="col-md-4"><select name="term" id="sectionTerm" class="form-select" required><option value="">Term</option></select></div><div class="col-md-4"><input name="academic_year" class="form-control" placeholder="2026-27" required></div></div><button class="btn btn-primary mt-3">Create Section</button></form></div></div></div>
<div class="col-lg-6"><div class="card shadow-sm border-0 h-100"><div class="card-body"><h5>Auto-Enroll Students</h5><p class="small text-muted">Matches students by program + term and enrolls them into matching subjects.</p><form method="post"><?php echo csrfField(); ?><input type="hidden" name="action" value="auto"><select name="program_id" class="form-select mb-2" required><?php foreach($programs as $p):?><option value="<?=$p['id']?>"><?=$p['level']?> — <?=htmlspecialchars($p['name'])?></option><?php endforeach;?></select><select name="term" id="autoTerm" class="form-select mb-2" required><option value="">Term</option></select><button class="btn btn-success">Run Auto Enrollment</button></form></div></div></div>
</div>
<div class="card shadow-sm border-0 mb-4"><div class="card-body"><h5>Assign Student to Section</h5><form method="post" class="row g-2"><?php echo csrfField(); ?><input type="hidden" name="action" value="enroll"><div class="col-md-5"><select name="student_id" class="form-select" required><option value="">Select student</option><?php foreach($students as $s):?><option value="<?=$s['id']?>"><?=htmlspecialchars($s['roll_no'].' — '.$s['name'].' — '.$s['program_name'].' — '.$s['semester_or_year'])?></option><?php endforeach;?></select></div><div class="col-md-5"><select name="section_id" class="form-select" required><option value="">Select section</option><?php foreach($sections as $s):?><option value="<?=$s['id']?>"><?=$s['level']?> — <?=htmlspecialchars($s['program_name'].' — '.$s['name'].' — '.$s['term'].' — '.$s['academic_year'])?></option><?php endforeach;?></select></div><div class="col-md-2"><button class="btn btn-primary w-100">Assign</button></div></form></div></div>
<div class="card shadow-sm border-0"><div class="card-body"><h5>Sections</h5><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Program</th><th>Section</th><th>Term</th><th>Academic Year</th><th>Students</th></tr></thead><tbody><?php foreach($sections as $s):?><tr><td><?=$s['level']?> — <?=htmlspecialchars($s['program_name'])?></td><td><?=htmlspecialchars($s['name'])?></td><td><?=htmlspecialchars($s['term'])?></td><td><?=htmlspecialchars($s['academic_year'])?></td><td><span class="badge text-bg-primary"><?=$s['student_count']?></span></td></tr><?php endforeach;?></tbody></table></div></div></div>
<script>const terms={HSSC:['Part-I','Part-II'],BS:['1st Semester','2nd Semester','3rd Semester','4th Semester','5th Semester','6th Semester','7th Semester','8th Semester'],ADP:['1st Year','2nd Year','1st Semester','2nd Semester','3rd Semester','4th Semester']};function bindTerm(sel,termSel){const fill=()=>{const level=sel.options[sel.selectedIndex]?.text.split(' — ')[0];termSel.innerHTML='<option value="">Term</option>'+(terms[level]||[]).map(x=>'<option>'+x+'</option>').join('');};sel.addEventListener('change',fill);fill();}const sectionProgram=document.querySelector('input[name="action"][value="section"]').closest('form').querySelector('[name="program_id"]');bindTerm(sectionProgram,document.getElementById('sectionTerm'));const autoProgram=document.querySelector('input[name="action"][value="auto"]').closest('form').querySelector('[name="program_id"]');bindTerm(autoProgram,document.getElementById('autoTerm'));</script></div><?php require 'includes/footer.php';?>
