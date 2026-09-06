<?php
session_start();
require 'config/database.php';
require 'lib_security.php';
if(!isset($_SESSION['user'])||$_SESSION['user']['role']!=='admin'){header('Location: login.php');exit;}

function termOptionsForLevel(string $level): array {
    if($level==='HSSC') return ['1st Year','2nd Year'];
    if($level==='BS') return ['1st Semester','2nd Semester','3rd Semester','4th Semester','5th Semester','6th Semester','7th Semester','8th Semester'];
    return ['1st Semester','2nd Semester','3rd Semester','4th Semester'];
}

if($_SERVER['REQUEST_METHOD']==='POST'){
    verifyCsrf();
    if(isset($_POST['remove_assignment'])){
        $id=(int)$_POST['remove_assignment'];
        $pdo->prepare('UPDATE teacher_assignments SET active=0 WHERE id=?')->execute([$id]);
        header('Location: teacher_subjects.php'); exit;
    }
    $tid=(int)($_POST['teacher_id']??0);
    $pid=(int)($_POST['program_id']??0);
    $sid=(int)($_POST['subject_id']??0);
    $sectionId=(int)($_POST['section_id']??0);
    $term=trim($_POST['term']??'');
    $year=trim($_POST['academic_year']??'2026-27');
    if($tid&&$pid&&$sid&&$term&&$year){
       $v=$pdo->prepare('
    SELECT s.id, s.semester, p.level
    FROM subjects s
    JOIN programs p ON p.id = s.program_id
    WHERE s.id = ? AND s.program_id = ?
    LIMIT 1
');
        $v->execute([$sid,$pid]); $sub=$v->fetch();
        $validTerms=termOptionsForLevel($sub['level']??'');
        if($sub && in_array($term,$validTerms,true) && ($sub['semester']===''||$sub['semester']===null||$sub['semester']===$term)){
            if($sectionId){
                $sq=$pdo->prepare('SELECT id FROM sections WHERE id=? AND program_id=? AND term=? AND academic_year=?');
                $sq->execute([$sectionId,$pid,$term,$year]); if(!$sq->fetch())$sectionId=0;
            }
            $check=$pdo->prepare('SELECT id FROM teacher_assignments WHERE teacher_id=? AND subject_id=? AND program_id=? AND term=? AND academic_year=? AND ((section_id IS NULL AND ?=0) OR section_id=?) AND active=1 LIMIT 1');
            $check->execute([$tid,$sid,$pid,$term,$year,$sectionId,$sectionId]);
            if(!$check->fetchColumn()){
                $ins=$pdo->prepare('INSERT INTO teacher_assignments(teacher_id,subject_id,program_id,section_id,term,academic_year,active) VALUES(?,?,?,?,?,?,1)');
                $ins->execute([$tid,$sid,$pid,$sectionId?:null,$term,$year]);
            }
        }
    }
    header('Location: teacher_subjects.php'); exit;
}

$teachers=$pdo->query('SELECT t.id,u.name,u.email,t.department FROM teachers t JOIN users u ON u.id=t.user_id ORDER BY u.name')->fetchAll();
$programs=$pdo->query("SELECT id,level,name,specialization FROM programs WHERE active=1 ORDER BY FIELD(level,'HSSC','BS','ADP'),name")->fetchAll();
$subjects=$pdo->query("SELECT s.id,s.program_id,s.code,s.name,s.semester,p.level,p.name program_name FROM subjects s JOIN programs p ON p.id=s.program_id WHERE p.active=1 ORDER BY FIELD(p.level,'HSSC','BS','ADP'),p.name,s.semester,s.name")->fetchAll();
$sections=$pdo->query('SELECT id,program_id,name,term,academic_year FROM sections ORDER BY academic_year DESC,program_id,name,term,id')->fetchAll();
$rows=$pdo->query("SELECT ta.id,ta.term,ta.academic_year,u.name teacher_name,t.department,s.code,s.name subject_name,p.level,p.name program_name,sec.name section_name
 FROM teacher_assignments ta JOIN teachers t ON t.id=ta.teacher_id JOIN users u ON u.id=t.user_id JOIN subjects s ON s.id=ta.subject_id JOIN programs p ON p.id=ta.program_id LEFT JOIN sections sec ON sec.id=ta.section_id WHERE ta.active=1 ORDER BY u.name,FIELD(p.level,'HSSC','BS','ADP'),p.name,ta.term,s.name")->fetchAll();
$pageTitle='Teacher Class & Subject Assignment'; require 'includes/header.php';
?>
<div class="container py-4">
<div class="d-flex justify-content-between align-items-center"><div><h2>Teacher Class & Subject Assignment</h2><p class="text-muted mb-0">Admin decides exactly which teacher teaches which subject to which program and term.</p></div><a href="admin.php" class="btn btn-outline-secondary">Dashboard</a></div>
<div class="card shadow-sm my-3"><div class="card-body"><form method="post" class="row g-3"><?php echo csrfField(); ?>
<div class="col-md-4"><label class="form-label">Teacher</label><select name="teacher_id" class="form-select" required><option value="">Select Teacher</option><?php foreach($teachers as $t):?><option value="<?=$t['id']?>"><?=htmlspecialchars($t['name'].' — '.$t['department'])?></option><?php endforeach;?></select></div>
<div class="col-md-4"><label class="form-label">Program / Class</label><select name="program_id" id="program_id" class="form-select" required><option value="">Select Program</option><?php foreach($programs as $p):?><option value="<?=$p['id']?>" data-level="<?=$p['level']?>"><?=htmlspecialchars($p['level'].' — '.$p['name'].($p['specialization']?' — '.$p['specialization']:''))?></option><?php endforeach;?></select></div>
<div class="col-md-4"><label class="form-label">Year / Semester</label><select name="term" id="term" class="form-select" required><option value="">Select after program</option></select></div>
<div class="col-md-5"><label class="form-label">Subject</label><select name="subject_id" id="subject_id" class="form-select" required><option value="">Select after program/term</option><?php foreach($subjects as $s):?><option value="<?=$s['id']?>" data-program="<?=$s['program_id']?>" data-term="<?=htmlspecialchars($s['semester']??'')?>"><?=htmlspecialchars(($s['code']?:'No Code').' — '.$s['name'].' — '.($s['semester']?:'General'))?></option><?php endforeach;?></select></div>
<div class="col-md-4"><label class="form-label">Section / Class (Optional)</label><select name="section_id" id="section_id" class="form-select"><option value="0">All sections</option><?php foreach($sections as $s):?><option value="<?=$s['id']?>" data-program="<?=$s['program_id']?>" data-term="<?=htmlspecialchars($s['term'])?>" data-year="<?=htmlspecialchars($s['academic_year'])?>"><?=htmlspecialchars($s['name'].' — '.$s['term'].' — '.$s['academic_year'])?></option><?php endforeach;?></select></div>
<div class="col-md-3"><label class="form-label">Academic Year</label><input name="academic_year" id="academic_year" class="form-control" value="2026-27" required></div>
<div class="col-12"><button class="btn btn-primary px-4">Assign Teacher</button></div></form></div></div>
<div class="card shadow-sm"><div class="card-header bg-white"><strong>Active Assignments</strong></div><div class="table-responsive"><table class="table mb-0 align-middle"><thead><tr><th>Teacher</th><th>Level</th><th>Program</th><th>Year/Semester</th><th>Subject</th><th>Section</th><th>Academic Year</th><th></th></tr></thead><tbody><?php foreach($rows as $r):?><tr><td><?=htmlspecialchars($r['teacher_name'])?></td><td><?=htmlspecialchars($r['level'])?></td><td><?=htmlspecialchars($r['program_name'])?></td><td><?=htmlspecialchars($r['term'])?></td><td><?=htmlspecialchars(($r['code']?:'').' — '.$r['subject_name'])?></td><td><?=htmlspecialchars($r['section_name']?:'All Sections')?></td><td><?=htmlspecialchars($r['academic_year'])?></td><td><form method="post" class="d-inline"><?php echo csrfField(); ?><input type="hidden" name="remove_assignment" value="<?=$r['id']?>"><button class="btn btn-sm btn-outline-danger" onclick="return confirm('Remove this assignment?')">Remove</button></form></td></tr><?php endforeach;if(!$rows):?><tr><td colspan="8" class="text-muted">No active assignments yet.</td></tr><?php endif;?></tbody></table></div></div></div>
<script>
const program=document.getElementById('program_id'), term=document.getElementById('term'), subject=document.getElementById('subject_id'), section=document.getElementById('section_id'), year=document.getElementById('academic_year');
const terms={HSSC:['1st Year','2nd Year'],BS:['1st Semester','2nd Semester','3rd Semester','4th Semester','5th Semester','6th Semester','7th Semester','8th Semester'],ADP:['1st Semester','2nd Semester','3rd Semester','4th Semester']};
function refresh(){const opt=program.options[program.selectedIndex], level=opt?opt.dataset.level:''; term.innerHTML='<option value="">Select</option>'+(terms[level]||[]).map(x=>`<option value="${x}">${x}</option>`).join(''); filterSubjects(); filterSections();}
function filterSubjects(){const pid=program.value, tm=term.value; [...subject.options].forEach((o,i)=>{if(i===0){o.hidden=false;return;} const ok=o.dataset.program===pid && (!o.dataset.term || o.dataset.term===tm); o.hidden=!ok;}); if(subject.selectedOptions[0] && subject.selectedOptions[0].hidden) subject.value='';}
function filterSections(){const pid=program.value, tm=term.value, yr=year.value; [...section.options].forEach((o,i)=>{if(i===0){o.hidden=false;return;} o.hidden=!(o.dataset.program===pid&&o.dataset.term===tm&&(!yr||o.dataset.year===yr));}); if(section.selectedOptions[0]&&section.selectedOptions[0].hidden)section.value='0';}
program.addEventListener('change',refresh); term.addEventListener('change',()=>{filterSubjects();filterSections();}); year.addEventListener('input',filterSections); refresh();
</script>
<?php require 'includes/footer.php'; ?>
