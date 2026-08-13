<?php
require __DIR__.'/app/bootstrap.php';
require_reviewer();
$pdo = db();
$rid = (int)($_GET['id'] ?? $_POST['report_id'] ?? 0);

$load = $pdo->prepare("SELECT r.*, r.status AS status, r.moderation_status AS moderation_status, p.address_private, p.neighborhood, p.sector, m.name municipality,
                              a.id assignment_id, a.expires_at
                       FROM damage_reports r
                       JOIN properties p ON p.id=r.property_id
                       JOIN municipalities m ON m.id=p.municipality_id
                       JOIN report_assignments a ON a.report_id=r.id AND a.reviewer_id=? AND a.status='active'
                       WHERE r.id=? LIMIT 1");
$load->execute([user()['id'],$rid]);
$r = $load->fetch();
if(!$r){ http_response_code(403); exit('Este caso no está asignado actualmente a tu cuenta.'); }

if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if($action==='status'){
        $status = $_POST['status'] ?? '';
        $allowed = ['contacted','scheduled','reviewing','second_opinion','referred'];
        if(!in_array($status,$allowed,true)){
            flash('error','Estado no válido.');
            redirect('review-case.php?id='.$rid);
        }
        $pdo->beginTransaction();
        try {
            $pdo->prepare('UPDATE damage_reports SET status=?,updated_at=NOW() WHERE id=?')->execute([$status,$rid]);
            $pdo->prepare("INSERT INTO report_events(report_id,actor_user_id,event_type,payload_json,created_at) VALUES(?,?,?,'{}',NOW())")
                ->execute([$rid,user()['id'],'status_'.$status]);
            $pdo->commit();
            flash('success','Estado actualizado.');
        } catch(Throwable $e) {
            if($pdo->inTransaction()) $pdo->rollBack();
            flash('error','No fue posible actualizar el estado.');
        }
        redirect('review-case.php?id='.$rid);
    }

    if($action==='release'){
        $pdo->beginTransaction();
        try {
            $lock = $pdo->prepare("SELECT id FROM report_assignments WHERE id=? AND reviewer_id=? AND status='active' FOR UPDATE");
            $lock->execute([$r['assignment_id'],user()['id']]);
            if(!$lock->fetch()) throw new RuntimeException('Asignación no disponible');
            $pdo->prepare("UPDATE report_assignments SET status='released',released_at=NOW() WHERE id=?")->execute([$r['assignment_id']]);
            $pdo->prepare("UPDATE damage_reports SET status='pending',updated_at=NOW() WHERE id=?")->execute([$rid]);
            $pdo->prepare("INSERT INTO report_events(report_id,actor_user_id,event_type,payload_json,created_at) VALUES(?,?,'case_released','{}',NOW())")
                ->execute([$rid,user()['id']]);
            $pdo->commit();
            flash('success','El caso fue liberado y vuelve a estar disponible para otros revisores.');
            redirect('reviewer.php#casos-disponibles');
        } catch(Throwable $e) {
            if($pdo->inTransaction()) $pdo->rollBack();
            flash('error','No fue posible liberar el caso.');
            redirect('review-case.php?id='.$rid);
        }
    }

    if($action==='diagnosis'){
        $inspectionType = trim($_POST['inspection_type'] ?? '');
        $inspectionDate = trim($_POST['inspection_date'] ?? '');
        $findings = trim($_POST['findings_public'] ?? '');
        $recommendation = trim($_POST['recommendation'] ?? '');
        $diagnosis = trim($_POST['public_diagnosis'] ?? '');
        $types = ['Presencial','Visual','Revisión fotográfica'];
        $recommendations = [
            'No se identificaron señales visibles críticas',
            'Requiere evaluación adicional',
            'Recomendada evacuación preventiva',
            'Requiere ingeniero estructural',
            'Requiere atención de autoridad competente'
        ];
        $dateOk = preg_match('/^\d{4}-\d{2}-\d{2}$/',$inspectionDate) === 1;
        if(!in_array($inspectionType,$types,true) || !$dateOk || !$findings || !in_array($recommendation,$recommendations,true) || !$diagnosis){
            flash('error','Completa correctamente todos los campos de la evaluación.');
            redirect('review-case.php?id='.$rid);
        }

        $pdo->beginTransaction();
        try{
            $pdo->prepare('INSERT INTO inspections(report_id,reviewer_id,inspection_type,inspection_date,findings_public,recommendation,public_diagnosis,created_at,updated_at) VALUES(?,?,?,?,?,?,?,NOW(),NOW())')
                ->execute([$rid,user()['id'],$inspectionType,$inspectionDate,$findings,$recommendation,$diagnosis]);
            $pdo->prepare("UPDATE damage_reports SET status='reviewed',updated_at=NOW() WHERE id=?")->execute([$rid]);
            $pdo->prepare("UPDATE report_assignments SET status='completed',released_at=NOW() WHERE id=? AND reviewer_id=? AND status='active'")
                ->execute([$r['assignment_id'],user()['id']]);
            $pdo->prepare("INSERT INTO report_events(report_id,actor_user_id,event_type,payload_json,created_at) VALUES(?,?,'inspection_published','{}',NOW())")
                ->execute([$rid,user()['id']]);
            $pdo->commit();
            flash('success','Evaluación publicada y caso marcado como revisado.');
            redirect('case.php?code='.urlencode($r['public_code']));
        }catch(Throwable $e){
            if($pdo->inTransaction()) $pdo->rollBack();
            flash('error','No fue posible publicar la evaluación.');
            redirect('review-case.php?id='.$rid);
        }
    }
}
render_header('Gestionar caso');
?>
<div class="container py-4" style="max-width:900px">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
        <div><div class="text-muted small">Caso asignado</div><h1 class="mb-1"><?=e($r['public_code'])?></h1><p class="mb-0"><strong><?=e($r['neighborhood'])?>, <?=e($r['municipality'])?></strong></p></div>
        <div class="text-end"><span class="priority priority-<?=e($r['system_priority'])?>"><?=e(priority_label($r['system_priority']))?></span><div class="small text-muted mt-1"><?=e(status_label($r['status']))?></div></div>
    </div>

    <div class="private-note mb-3">
        <strong>Datos privados para coordinación</strong><br>
        Dirección: <?=e($r['address_private'])?><br>
        Contacto: <?=e($r['reporter_name_private'])?> · <?=e($r['reporter_phone_private'])?><?= $r['reporter_email_private']?' · '.e($r['reporter_email_private']):'' ?>
    </div>
    <p><?=nl2br(e($r['description_public']))?></p>
    <div class="small text-muted mb-4">La asignación actual vence: <?=e(date('d/m/Y H:i',strtotime($r['expires_at'])))?></div>

    <div class="form-section">
        <h4>Actualizar estado</h4>
        <form method="post">
            <?=csrf_field()?><input type="hidden" name="action" value="status"><input type="hidden" name="report_id" value="<?=$rid?>">
            <div class="input-group">
                <select class="form-select" name="status">
                    <option value="contacted" <?=$r['status']==='contacted'?'selected':''?>>Contactado</option>
                    <option value="scheduled" <?=$r['status']==='scheduled'?'selected':''?>>Visita programada</option>
                    <option value="reviewing" <?=$r['status']==='reviewing'?'selected':''?>>En revisión</option>
                    <option value="second_opinion" <?=$r['status']==='second_opinion'?'selected':''?>>Requiere segunda opinión</option>
                    <option value="referred" <?=$r['status']==='referred'?'selected':''?>>Derivado a autoridad</option>
                </select>
                <button class="btn btn-outline-primary">Actualizar</button>
            </div>
        </form>
    </div>

    <div class="form-section">
        <h4>Publicar evaluación</h4>
        <form method="post">
            <?=csrf_field()?><input type="hidden" name="action" value="diagnosis"><input type="hidden" name="report_id" value="<?=$rid?>">
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label">Tipo de inspección</label><select class="form-select" name="inspection_type"><option>Presencial</option><option>Visual</option><option>Revisión fotográfica</option></select></div>
                <div class="col-md-6"><label class="form-label">Fecha</label><input class="form-control" type="date" name="inspection_date" value="<?=date('Y-m-d')?>" required></div>
                <div class="col-12"><label class="form-label">Hallazgos</label><textarea class="form-control" name="findings_public" rows="4" required></textarea></div>
                <div class="col-12"><label class="form-label">Recomendación</label><select class="form-select" name="recommendation"><option>No se identificaron señales visibles críticas</option><option>Requiere evaluación adicional</option><option>Recomendada evacuación preventiva</option><option>Requiere ingeniero estructural</option><option>Requiere atención de autoridad competente</option></select></div>
                <div class="col-12"><label class="form-label">Diagnóstico / observación pública</label><textarea class="form-control" name="public_diagnosis" rows="5" required></textarea><div class="form-text">Usa lenguaje de evaluación preliminar. No presentes esto como certificación oficial.</div></div>
            </div>
            <button class="btn btn-success btn-lg mt-3">Publicar evaluación y marcar revisado</button>
        </form>
    </div>

    <div class="card border-danger-subtle mt-4"><div class="card-body"><h5 class="card-title">¿No puedes continuar con este caso?</h5><p class="text-muted">Libéralo para que otro revisor pueda tomarlo. La acción quedará registrada en el historial.</p><form method="post" onsubmit="return confirm('¿Seguro que deseas liberar este caso? Volverá a quedar disponible para otros revisores.');"><?=csrf_field()?><input type="hidden" name="action" value="release"><input type="hidden" name="report_id" value="<?=$rid?>"><button class="btn btn-outline-danger">Liberar caso</button></form></div></div>
</div>
<?php render_footer(); ?>
