<?php require 'config/database.php';
$users=['admin@imcb.edu.pk'=>'Admin@123','student@imcb.edu.pk'=>'Student@123','teacher@imcb.edu.pk'=>'Teacher@123'];
foreach($users as $email=>$pass){$q=$pdo->prepare('UPDATE users SET password_hash=? WHERE email=?');$q->execute([password_hash($pass,PASSWORD_DEFAULT),$email]);}
echo 'Demo passwords reset successfully.';
?>
