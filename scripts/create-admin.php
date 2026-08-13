<?php
if (php_sapi_name() !== 'cli') exit("Solo CLI\n");
require __DIR__.'/../app/bootstrap.php';
$email=$argv[1]??null;$password=$argv[2]??null;$name=$argv[3]??'Administrador';
if(!$email||!$password){exit("Uso: php scripts/create-admin.php correo@dominio.com contraseña \"Nombre\"\n");}
$st=db()->prepare("INSERT INTO users(uuid,name,email,phone,password_hash,role,status,email_verified_at,created_at,updated_at) VALUES(UUID(),?,?,NULL,?,'admin','active',NOW(),NOW(),NOW())");
$st->execute([$name,$email,password_hash($password,PASSWORD_DEFAULT)]);echo "Admin creado\n";