<?php
session_start(); require 'config/database.php'; require 'lib_security.php'; require 'lib_documents.php';
if(!isset($_SESSION['user'])||$_SESSION['user']['role']!=='student'){header('Location: login.php');exit;}
$student=currentStudent($pdo,(int)$_SESSION['user']['id']); if(!$student) exit('Student profile not found.');
$types=documentTypes(); $error='';$success='';
if($_SERVER['REQUEST_METHOD']==='POST'){ verifyCsrf();
  $type=$_POST['document_type']??'';
  if(!isset($types[$type])) $error='Please select a valid document type.';
  elseif(!isset($_FILES['document'])||$_FILES['document']['error']!==UPLOAD_ERR_OK) $error='Please choose a file.';
  else {
    $f=$_FILES['document']; $allowed=['application/pdf','image/jpeg','image/png']; $max=5*1024*1024;
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    if($f['size']>$max||!in_array($mime,$allowed,true)) $error='Only PDF, JPG or PNG files up to 5 MB are allowed.';
    else { $extMap=['application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png']; $ext=$extMap[$mime]??'bin'; $stored=bin2hex(random_bytes(12)).'.'.$ext; $dir=__DIR__.'/uploads/student_docs';
      if(!is_dir($dir)) mkdir($dir,0755,true);
      if(!move_uploaded_file($f['tmp_name'],$dir.'/'.$stored)) $error='Could not save the uploaded file.';
      else { $q=$pdo->prepare('INSERT INTO student_documents(student_id,document_type,file_name,stored_name,mime_type,file_size) VALUES(?,?,?,?,?,?)');$q->execute([$student['id'],$type,basename($f['name']),$stored,$mime,$f['size']]);$success='Document uploaded successfully and sent for verification.'; }
    }
  }
}
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['delete'])){ verifyCsrf(); $id=(int)$_POST['delete'];$q=$pdo->prepare("SELECT * FROM student_documents WHERE id=? AND student_id=? AND status='rejected'");$q->execute([$id,$student['id']]);if($d=$q->fetch()){ @unlink(__DIR__.'/uploads/student_docs/'.$d['stored_name']);$pdo->prepare('DELETE FROM student_documents WHERE id=?')->execute([$id]);$success='Rejected document removed.';} }
$q=$pdo->prepare('SELECT * FROM student_documents WHERE student_id=? ORDER BY uploaded_at DESC');$q->execute([$student['id']]);$docs=$q->fetchAll();
$pageTitle='My Documents'; require 'includes/header.php';
?>
<div class="container py-5"><div class="row justify-content-center"><div class="col-lg-10"><div class="d-flex justify-content-between align-items-center mb-3"><div><h2 class="fw-bold">My Documents</h2><p class="text-muted mb-0">Upload admission/academic documents for verification.</p></div><a href="student.php" class="btn btn-outline-secondary">Back to Portal</a></div>
<?php if($success):?><div class="alert alert-success"><?=htmlspecialchars($success)?></div><?php endif;?><?php if($error):?><div class="alert alert-danger"><?=htmlspecialchars($error)?></div><?php endif;?>
<div class="card shadow-sm border-0 mb-4"><div class="card-body"><h5>Upload Document</h5><form method="post" enctype="multipart/form-data" class="row g-3"><?php echo csrfField(); ?><div class="col-md-5"><label class="form-label">Document Type</label><select name="document_type" class="form-select" required><option value="">Select</option><?php foreach($types as $k=>$v):?><option value="<?=$k?>"><?=htmlspecialchars($v)?></option><?php endforeach;?></select></div><div class="col-md-5"><label class="form-label">File</label><input type="file" name="document" class="form-control" accept=".pdf,.jpg,.jpeg,.png" required><div class="form-text">PDF/JPG/PNG, maximum 5 MB.</div></div><div class="col-md-2 d-flex align-items-end"><button class="btn btn-primary w-100">Upload</button></div></form></div></div>
<div class="card shadow-sm border-0"><div class="card-body"><h5>Uploaded Documents</h5><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Type</th><th>File</th><th>Status</th><th>Uploaded</th><th>Remarks</th><th></th></tr></thead><tbody><?php foreach($docs as $d):?><tr><td><?=htmlspecialchars($types[$d['document_type']]??$d['document_type'])?></td><td><?=htmlspecialchars($d['file_name'])?></td><td><span class="badge text-bg-<?=($d['status']==='verified'?'success':($d['status']==='rejected'?'danger':'warning'))?>"><?=htmlspecialchars(ucfirst($d['status']))?></span></td><td><?=htmlspecialchars($d['uploaded_at'])?></td><td><?=htmlspecialchars($d['remarks']??'')?></td><td><?php if($d['status']==='rejected'):?><form method="post" class="d-inline" onsubmit="return confirm('Remove this rejected document?')"><?php echo csrfField(); ?><button name="delete" value="<?=$d['id']?>" class="btn btn-sm btn-outline-danger">Remove</button></form><?php endif;?></td></tr><?php endforeach;if(!$docs):?><tr><td colspan="6" class="text-muted">No documents uploaded yet.</td></tr><?php endif;?></tbody></table></div></div></div></div></div></div><?php require 'includes/footer.php';?>
