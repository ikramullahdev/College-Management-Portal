<?php
if (session_status() === PHP_SESSION_NONE) session_start();
function csrfToken(): string { if (empty($_SESSION['_csrf'])) $_SESSION['_csrf']=bin2hex(random_bytes(32)); return $_SESSION['_csrf']; }
function csrfField(): string { return '<input type="hidden" name="_csrf" value="'.htmlspecialchars(csrfToken(),ENT_QUOTES,'UTF-8').'">'; }
function verifyCsrf(): void { $token=$_POST['_csrf']??''; if(!$token || empty($_SESSION['_csrf']) || !hash_equals($_SESSION['_csrf'],$token)){ http_response_code(419); exit('Invalid or expired security token. Please go back and try again.'); } }
function requireRole(string $role,string $login='login.php'): void { if(!isset($_SESSION['user'])||$_SESSION['user']['role']!==$role){header('Location: '.$login);exit;} }
