<?php
require __DIR__.'/app/bootstrap.php';
require_reviewer();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Método no permitido.'); }
verify_csrf();
$rid = (int)($_POST['report_id'] ?? 0);
if ($rid <= 0) { flash('error','Caso inválido.'); redirect('reviewer.php#casos-disponibles'); }

$pdo = db();
$active = $pdo->prepare("SELECT COUNT(*) FROM report_assignments WHERE reviewer_id=? AND status='active'");
$active->execute([user()['id']]);
if ((int)$active->fetchColumn() >= (int)(config('max_active_cases') ?: 5)) {
    flash('error','Ya alcanzaste el máximo de casos activos.');
    redirect('reviewer.php#mis-casos');
}

$pdo->beginTransaction();
try {
    $st = $pdo->prepare("SELECT status, moderation_status FROM damage_reports WHERE id=? FOR UPDATE");
    $st->execute([$rid]);
    $report = $st->fetch();
    if (!$report || $report['status'] !== 'pending' || $report['moderation_status'] !== 'published') {
        throw new RuntimeException('Caso no disponible');
    }

    // Se vuelve a comprobar el límite dentro de la transacción para reducir condiciones de carrera.
    $active = $pdo->prepare("SELECT COUNT(*) FROM report_assignments WHERE reviewer_id=? AND status='active'");
    $active->execute([user()['id']]);
    if ((int)$active->fetchColumn() >= (int)(config('max_active_cases') ?: 5)) {
        throw new RuntimeException('Límite de casos alcanzado');
    }

    $exp = date('Y-m-d H:i:s', time() + (int)config('assignment_hours') * 3600);
    $pdo->prepare("INSERT INTO report_assignments(report_id,reviewer_id,assigned_at,expires_at,status) VALUES(?,?,NOW(),?,'active')")
        ->execute([$rid,user()['id'],$exp]);
    $pdo->prepare("UPDATE damage_reports SET status='assigned',updated_at=NOW() WHERE id=?")
        ->execute([$rid]);
    $pdo->prepare("INSERT INTO report_events(report_id,actor_user_id,event_type,payload_json,created_at) VALUES(?,?,'case_taken','{}',NOW())")
        ->execute([$rid,user()['id']]);
    $pdo->commit();

    flash('success','El caso fue asignado a tu cuenta. Ya puedes contactar al reportante y gestionar la revisión.');
    redirect('review-case.php?id='.$rid);
} catch(Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    flash('error','El caso ya no está disponible o no puede ser asignado en este momento.');
    redirect('reviewer.php#casos-disponibles');
}
