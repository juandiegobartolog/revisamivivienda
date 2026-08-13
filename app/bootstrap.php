<?php
session_start();
$config = require __DIR__ . '/../config/config.php';

function config($key = null) {
    global $config;
    if ($key === null) return $config;
    $parts = explode('.', $key);
    $value = $config;
    foreach ($parts as $part) $value = $value[$part] ?? null;
    return $value;
}

function db(): PDO {
    static $pdo;
    if (!$pdo) {
        $db = config('db');
        $dsn = "mysql:host={$db['host']};dbname={$db['name']};charset={$db['charset']}";
        $pdo = new PDO($dsn, $db['user'], $db['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // Normaliza claves devueltas por PDO a minúsculas. Esto evita
            // incompatibilidades con esquemas legados que tengan columnas
            // como NAME, CODE o STATUS en mayúsculas.
            PDO::ATTR_CASE => PDO::CASE_LOWER,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
    return $pdo;
}

function e($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function redirect($url){ header('Location: '.$url); exit; }
function csrf_token(): string { if (empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(32)); return $_SESSION['csrf']; }
function csrf_field(): string { return '<input type="hidden" name="csrf" value="'.e(csrf_token()).'">'; }
function verify_csrf(){ if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) { http_response_code(419); exit('Sesión expirada. Recarga la página.'); }}
function flash($key,$value=null){ if($value!==null){$_SESSION['flash'][$key]=$value; return;} $v=$_SESSION['flash'][$key]??null; unset($_SESSION['flash'][$key]); return $v; }
function user(){ return $_SESSION['user'] ?? null; }
function require_login(){ if(!user()) redirect('login.php'); }

function account_state(): ?array {
    static $stateLoaded = false;
    static $state = null;
    if ($stateLoaded) return $state;
    $stateLoaded = true;
    $u = user();
    if (!$u || empty($u['id'])) return null;
    $st = db()->prepare("SELECT u.id,u.name,u.email,u.role,u.status,rp.verification_status,rp.profession FROM users u LEFT JOIN reviewer_profiles rp ON rp.user_id=u.id WHERE u.id=? LIMIT 1");
    $st->execute([(int)$u['id']]);
    $state = $st->fetch() ?: null;
    return $state;
}
function require_admin(){
    require_login();
    $a=account_state();
    if(!$a || $a['role']!=='admin' || $a['status']!=='active'){ http_response_code(403); exit('Acceso denegado'); }
}
function is_reviewer(){
    $a=account_state();
    return $a && $a['role']==='reviewer' && $a['status']==='active' && !in_array($a['verification_status'],['suspended','revoked'],true);
}
function require_reviewer(){
    require_login();
    if (!is_reviewer()) { http_response_code(403); exit('Acceso reservado para revisores habilitados.'); }
}
function active_case_count(?int $reviewerId=null): int {
    $reviewerId = $reviewerId ?: (int)(user()['id'] ?? 0);
    if ($reviewerId <= 0) return 0;
    $st=db()->prepare("SELECT COUNT(*) FROM report_assignments WHERE reviewer_id=? AND status='active'");
    $st->execute([$reviewerId]);
    return (int)$st->fetchColumn();
}
function can_take_report(array $report): array {
    if (!is_reviewer()) return [false,'Debes ingresar con una cuenta de revisor habilitada.'];
    if (($report['moderation_status'] ?? 'published') !== 'published') return [false,'Este reporte no está publicado.'];
    if (($report['status'] ?? '') !== 'pending') {
        $status = status_label($report['status'] ?? '');
        return [false,'Este reporte no está disponible para tomar. Estado actual: '.$status.'.'];
    }
    $max=(int)(config('max_active_cases') ?: 5);
    if (active_case_count() >= $max) return [false,'Alcanzaste el máximo de '.$max.' casos activos.'];
    return [true,''];
}

function public_code(): string { return 'COL26-'.strtoupper(substr(bin2hex(random_bytes(5)),0,8)); }
function priority_rank($p){ return ['urgent'=>1,'high'=>2,'medium'=>3,'low'=>4][$p]??5; }
function priority_label($p){ return ['urgent'=>'Urgente','high'=>'Alta','medium'=>'Media','low'=>'Baja'][$p]??$p; }
function status_label($s){ return ['pending'=>'Pendiente','assigned'=>'Asignado','contacted'=>'Contactado','scheduled'=>'Visita programada','reviewing'=>'En revisión','reviewed'=>'Revisado','second_opinion'=>'Segunda opinión','referred'=>'Derivado a autoridad','closed'=>'Cerrado'][$s]??$s; }
function status_group($s){ if(in_array($s,['pending'],true)) return 'pending'; if(in_array($s,['assigned','contacted','scheduled','reviewing','second_opinion','referred'],true)) return 'reviewing'; return 'reviewed'; }
function verification_label($s){ return ['pending'=>'Pendiente de verificación','verified'=>'Verificado','suspended'=>'Suspendido','revoked'=>'Revocado'][$s]??$s; }
function event_label($e){ return [
    'report_created'=>'Reporte creado',
    'case_taken'=>'Caso tomado por un revisor',
    'assignment_expired'=>'Asignación vencida; caso liberado',
    'status_contacted'=>'Contacto registrado',
    'status_scheduled'=>'Visita programada',
    'status_reviewing'=>'Caso en revisión',
    'status_second_opinion'=>'Se solicitó una segunda opinión',
    'status_referred'=>'Caso derivado a una autoridad',
    'inspection_published'=>'Evaluación publicada',
    'report_published'=>'Reporte publicado',
    'report_hidden'=>'Reporte ocultado',
    'case_released'=>'Caso liberado por el revisor',
    'case_closed'=>'Caso cerrado'
  ][$e]??'Actualización del reporte'; }
function role_label($r){ return ['reviewer'=>'Revisor','admin'=>'Administrador'][$r]??$r; }

function render_header($title=''){
    $app=e(config('app_name')); $u=user();
    echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.e($title?$title.' · '.config('app_name'):config('app_name')).'</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link rel="stylesheet" href="assets/css/app.css"></head><body>';
    echo '<nav class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top"><div class="container"><a class="navbar-brand fw-bold" href="index.php">'.$app.'</a><button class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#nav"><span class="navbar-toggler-icon"></span></button><div class="collapse navbar-collapse" id="nav"><div class="navbar-nav ms-auto">';
    echo '<a class="nav-link" href="index.php">Reportes</a><a class="nav-link" href="report.php">Reportar vivienda</a>';
    if($u){
        if(is_reviewer()) echo '<a class="nav-link" href="reviewer.php">Panel del revisor</a>';
        if(($u['role']??'')==='admin') echo '<a class="nav-link" href="admin.php">Administración</a>';
        echo '<a class="nav-link" href="logout.php">Salir</a>';
    } else {
        echo '<a class="nav-link" href="reviewer-register.php">Quiero ayudar</a><a class="nav-link" href="login.php">Ingresar</a>';
    }
    echo '</div></div></div></nav><main>';
    if($m=flash('success')) echo '<div class="container mt-3"><div class="alert alert-success">'.e($m).'</div></div>';
    if($m=flash('error')) echo '<div class="container mt-3"><div class="alert alert-danger">'.e($m).'</div></div>';
    if($m=flash('warning')) echo '<div class="container mt-3"><div class="alert alert-warning">'.e($m).'</div></div>';
}
function render_footer(){ echo '</main><footer class="border-top mt-5 py-4 bg-light"><div class="container small text-muted"><strong>Importante:</strong> esta plataforma facilita visibilidad y coordinación. No reemplaza servicios de emergencia ni constituye una certificación de habitabilidad o seguridad estructural.</div></footer><script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script><script src="assets/js/app.js"></script></body></html>'; }
