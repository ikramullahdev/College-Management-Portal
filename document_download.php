<?php
session_start();require 'config/database.php';
if(!isset($_SESSION['user'])){http_response_code(403);exit('Login required.');}
$id=(int)($_GET['id']??0);$q=$pdo->prepare('SELECT d.*,st.user_id FROM student_documents d JOIN students st ON st.id=d.student_id WHERE d.id=?');$q->execute([$id]);$d=$q->fetch();if(!$d){http_response_code(404);exit('Document not found.');}
if($_SESSION['user']['role']==='student'&&(int)$d['user_id']!==(int)$_SESSION['user']['id']){http_response_code(403);exit('Access denied.');}
$path=__DIR__.'/uploads/student_docs/'.$d['stored_name'];if(!is_file($path)){http_response_code(404);exit('File missing.');}
header('Content-Type: '.($d['mime_type']?:'application/octet-stream'));header('Content-Length: '.filesize($path));header('Content-Disposition: inline; filename="'.basename($d['file_name']).'"');readfile($path);exit;
