<?php
session_start();
require '../config/database.php';
require '../lib_security.php';
require '../lib_phase8.php';
if(!isset($_SESSION['user'])||$_SESSION['user']['role']!=='teacher'){header('Location: ../login.php');exit;}
$userId=(int)$_SESSION['user']['id'];
$assignmentId=(int)($_GET['assignment_id']??$_POST['assignment_id']??0);
$assignment=getTeacherAssignment($pdo,$userId,$assignmentId);
if(!$assignment)exit('Assignment not found or you are not authorized for this class.');
$subjectId=(int)$assignment['subject_id'];$programId=(int)$assignment['program_id'];$term=$assignment['term'];$sectionId=(int)($assignment['section_id']??0);
$date=$_POST['attendance_date']??date('Y-m-d');$msg='';$msgType='info';
if($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['status'])){
 verifyCsrf();
 $pdo->beginTransaction();
 try{
  foreach($_POST['status'] as $eid=>$status){
   $eid=(int)$eid;$status=in_array($status,['present','absent','leave'],true)?$status:'absent';
   $sql='SELECT e.id,st.id student_id FROM enrollments e JOIN students st ON st.id=e.student_id WHERE e.id=? AND e.subject_id=? AND st.program_id=? AND st.semester_or_year=?';$args=[$eid,$subjectId,$programId,$term];
   if($sectionId){$sql.=' AND st.section_id=?';$args[]=$sectionId;}$sql.=' LIMIT 1';$q=$pdo->prepare($sql);$q->execute($args);if(!$q->fetch())continue;
   $q=$pdo->prepare('SELECT id FROM attendance WHERE enrollment_id=? AND attendance_date=? LIMIT 1');$q->execute([$eid,$date]);$id=$q->fetchColumn();
   if($id)$pdo->prepare('UPDATE attendance SET status=? WHERE id=?')->execute([$status,$id]);else$pdo->prepare('INSERT INTO attendance(enrollment_id,attendance_date,status) VALUES(?,?,?)')->execute([$eid,$date,$status]);
  }
  $pdo->commit();$msg='Attendance saved successfully.';$msgType='success';
 }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();$msg='Unable to save attendance. Please try again.';$msgType='danger';}
}
$sql='SELECT e.id enrollment_id,u.name,st.roll_no FROM enrollments e JOIN students st ON st.id=e.student_id JOIN users u ON u.id=st.user_id WHERE e.subject_id=? AND st.program_id=? AND st.semester_or_year=?';$args=[$subjectId,$programId,$term];if($sectionId){$sql.=' AND st.section_id=?';$args[]=$sectionId;}$sql.=' ORDER BY st.roll_no';$st=$pdo->prepare($sql);$st->execute($args);$students=$st->fetchAll(PDO::FETCH_ASSOC);
$pageTitle='Attendance';require '../includes/header.php';
?>
<div class="container py-4"><a href="index.php" class="btn btn-link ps-0">← Teacher Dashboard</a><div class="mb-3"><h3 class="fw-bold mb-1">Attendance — <?=htmlspecialchars($assignment['subject_name'])?></h3><div class="text-muted"><?=htmlspecialchars($assignment['level'].' — '.$assignment['program_name'].' — '.$term.' — '.($assignment['section_name']?:'All Sections'))?></div></div><?php if($msg):?><div class="alert alert-<?=htmlspecialchars($msgType)?>"><?=htmlspecialchars($msg)?></div><?php endif;?><form method="post"><?php echo csrfField(); ?><input type="hidden" name="assignment_id" value="<?=$assignmentId?>"><div class="row g-3 mb-3"><div class="col-md-3"><label class="form-label">Date</label><input type="date" name="attendance_date" value="<?=htmlspecialchars($date)?>" class="form-control" required></div></div><div class="card shadow-sm border-0"><div class="card-body table-responsive"><table class="table align-middle"><thead><tr><th>Roll No</th><th>Student</th><th>Status</th></tr></thead><tbody><?php foreach($students as $r):$aq=$pdo->prepare('SELECT status FROM attendance WHERE enrollment_id=? AND attendance_date=? LIMIT 1');$aq->execute([$r['enrollment_id'],$date]);$existing=$aq->fetchColumn()?:'present';?><tr><td><?=htmlspecialchars($r['roll_no'])?></td><td><?=htmlspecialchars($r['name'])?></td><td><select name="status[<?=$r['enrollment_id']?>]" class="form-select" style="max-width:180px"><option value="present" <?=$existing==='present'?'selected':''?>>Present</option><option value="absent" <?=$existing==='absent'?'selected':''?>>Absent</option><option value="leave" <?=$existing==='leave'?'selected':''?>>Leave</option></select></td></tr><?php endforeach;if(!$students):?><tr><td colspan="3" class="text-muted">No students are enrolled in this subject for this class/term.</td></tr><?php endif;?></tbody></table><?php if($students):?><button class="btn btn-primary">Save Attendance</button><?php endif;?></div></div></form></div>
<?php require '../includes/footer.php';?>
