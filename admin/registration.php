<?php
session_start(); require '../config/database.php'; require '../lib_security.php'; require '../lib_academic.php';
if(!isset($_SESSION['user'])||$_SESSION['user']['role']!=='admin'){header('Location: ../login.php');exit;}
$error=''; $success=''; $credentials=null;
function makeRoll(PDO $pdo, int $year): string {
  do { $roll='IMCB-'.$year.'-'.str_pad((string)random_int(1,99999),5,'0',STR_PAD_LEFT); $q=$pdo->prepare('SELECT COUNT(*) FROM students WHERE roll_no=?'); $q->execute([$roll]); } while((int)$q->fetchColumn()>0);
  return $roll;
}
if($_SERVER['REQUEST_METHOD']==='POST'){ verifyCsrf();
  $id=(int)($_POST['id']??0); $fee=(float)($_POST['admission_fee']??0); $due=$_POST['fee_due_date']??date('Y-m-d',strtotime('+15 days'));
  try{
    $pdo->beginTransaction();
    $q=$pdo->prepare('SELECT a.*,p.level,p.name program_name,p.specialization FROM admission_applications a JOIN programs p ON p.id=a.program_id WHERE a.id=? FOR UPDATE'); $q->execute([$id]); $a=$q->fetch();
    if(!$a) throw new Exception('Application not found.');
    if($a['status']!=='accepted') throw new Exception('Only accepted applications can be registered.');
    if($a['document_status']!=='verified') throw new Exception('Documents must be verified before registration.');
    if(!empty($a['student_id'])) throw new Exception('This application is already converted into a student.');
    $baseEmail=trim($a['email']); $q=$pdo->prepare('SELECT id FROM users WHERE email=?'); $q->execute([$baseEmail]); if($q->fetch()) throw new Exception('Applicant email already belongs to an existing portal account.');
    $session=$a['admission_session'] ?: (date('Y').'-'.substr((string)(date('Y')+1),-2));
    $year=(int)substr($session,0,4); $roll=makeRoll($pdo,$year); $password=bin2hex(random_bytes(4)); $hash=password_hash($password,PASSWORD_DEFAULT);
    $u=$pdo->prepare('INSERT INTO users(name,email,password_hash,role,status) VALUES(?,?,?,"student","active")'); $u->execute([$a['name'],$baseEmail,$hash]); $uid=(int)$pdo->lastInsertId();
    $s=$pdo->prepare('INSERT INTO students(user_id,roll_no,program_id,semester_or_year) VALUES(?,?,?,?)'); $s->execute([$uid,$roll,$a['program_id'],academicTerms($a['level'])[0]]); $sid=(int)$pdo->lastInsertId();
    $en=$pdo->prepare('INSERT IGNORE INTO enrollments(student_id,subject_id) SELECT ?,id FROM subjects WHERE program_id=? AND (semester IS NULL OR semester=? OR semester="")'); $en->execute([$sid,$a['program_id'],academicTerms($a['level'])[0]]);
    if($fee>0){$challan='CH-'.date('YmdHis').'-'.random_int(10,99); $fq=$pdo->prepare('INSERT INTO fee_challans(student_id,challan_no,purpose,amount,issue_date,due_date,notes) VALUES(?,?,"Admission Fee",?,CURDATE(),?,?)'); $fq->execute([$sid,$challan,$fee,$due,'Generated during admission registration']);}
    $up=$pdo->prepare('UPDATE admission_applications SET student_id=?,converted_at=NOW() WHERE id=?'); $up->execute([$sid,$id]);
    $pdo->commit(); $credentials=['name'=>$a['name'],'roll'=>$roll,'email'=>$baseEmail,'password'=>$password,'program'=>$a['level'].' — '.$a['program_name'],'term'=>academicTerms($a['level'])[0]];
  }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();$error=$e->getMessage();}
}
$rows=$pdo->query('SELECT a.*,p.level,p.name program_name,p.specialization,u.name student_name,s.roll_no FROM admission_applications a JOIN programs p ON p.id=a.program_id LEFT JOIN students s ON s.id=a.student_id LEFT JOIN users u ON u.id=s.user_id WHERE a.status="accepted" ORDER BY a.merit_score DESC,a.created_at ASC')->fetchAll();
$pageTitle='Student Registration'; require '../includes/header.php';
?>
<div class="container py-4"><div class="d-flex justify-content-between align-items-center"><div><h2>Student Registration</h2><p class="text-muted mb-0">Convert verified, accepted applicants into official portal student accounts.</p></div><a href="../admin.php" class="btn btn-outline-secondary">Dashboard</a></div>
<?php if($error):?><div class="alert alert-danger mt-3"><?=htmlspecialchars($error)?></div><?php endif;?>
<?php if($credentials):?><div class="alert alert-success mt-3"><h5>Registration completed</h5><p class="mb-2">Student account and subject enrollment have been created. Save these credentials for the student:</p><div class="row"><div class="col-md-6"><strong>Name:</strong> <?=htmlspecialchars($credentials['name'])?><br><strong>Roll No:</strong> <?=htmlspecialchars($credentials['roll'])?><br><strong>Program:</strong> <?=htmlspecialchars($credentials['program'])?><br><strong>Term:</strong> <?=htmlspecialchars($credentials['term'])?></div><div class="col-md-6"><strong>Email:</strong> <?=htmlspecialchars($credentials['email'])?><br><strong>Temporary Password:</strong> <code><?=htmlspecialchars($credentials['password'])?></code></div></div><div class="small mt-2">The temporary password is shown only on this page. Ask the student to change it after first login.</div></div><?php endif;?>
<div class="card shadow-sm mt-4"><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Merit</th><th>Application</th><th>Applicant</th><th>Program</th><th>Session</th><th>Documents</th><th>Registration</th></tr></thead><tbody><?php foreach($rows as $r):?><tr><td><strong><?=number_format((float)$r['merit_score'],2)?>%</strong></td><td><?=htmlspecialchars($r['application_no'])?></td><td><?=htmlspecialchars($r['name'])?><br><small><?=htmlspecialchars($r['email'])?></small></td><td><?=$r['level']?> — <?=htmlspecialchars($r['program_name'])?></td><td><?=htmlspecialchars($r['admission_session']??'')?></td><td><span class="badge <?= $r['document_status']==='verified'?'text-bg-success':($r['document_status']==='rejected'?'text-bg-danger':'text-bg-warning')?>"><?=htmlspecialchars($r['document_status'])?></span></td><td><?php if($r['student_id']):?><span class="badge text-bg-primary">Registered: <?=htmlspecialchars($r['roll_no'])?></span><?php elseif($r['document_status']==='verified'):?><form method="post" class="d-flex gap-2 align-items-center"><?php echo csrfField(); ?><input type="hidden" name="id" value="<?=$r['id']?>"><input name="admission_fee" type="number" step="0.01" min="0" class="form-control form-control-sm" placeholder="Admission fee"><input name="fee_due_date" type="date" value="<?=date('Y-m-d',strtotime('+15 days'))?>" class="form-control form-control-sm"><button class="btn btn-sm btn-primary" onclick="return confirm('Convert this accepted applicant into a student?')">Register Student</button></form><?php else:?><span class="text-muted">Verify documents first</span><?php endif;?></td></tr><?php endforeach;?><?php if(!$rows):?><tr><td colspan="7" class="text-center text-muted py-4">No accepted applicants yet.</td></tr><?php endif;?></tbody></table></div></div></div>
<?php require '../includes/footer.php'; ?>
