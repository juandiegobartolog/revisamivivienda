<?php
require __DIR__.'/app/bootstrap.php';
$code = trim($_GET['code'] ?? '');
$st = db()->prepare("SELECT r.*, r.status AS status, r.moderation_status AS moderation_status, p.neighborhood, p.sector, p.address_private,
                            m.name municipality, d.name department,
                            a.reviewer_id AS assigned_reviewer_id,
                            au.name AS assigned_reviewer_name,
                            i.reviewer_id AS inspection_reviewer_id,
                            iu.name AS inspection_reviewer_name,
                            irp.profession AS inspection_reviewer_profession,
                            irp.verification_status AS inspection_reviewer_verification,
                            i.inspection_type, i.inspection_date, i.findings_public, i.recommendation, i.public_diagnosis
                     FROM damage_reports r
                     JOIN properties p ON p.id=r.property_id
                     JOIN municipalities m ON m.id=p.municipality_id
                     JOIN departments d ON d.id=p.department_id
                     LEFT JOIN report_assignments a ON a.report_id=r.id AND a.status='active'
                     LEFT JOIN users au ON au.id=a.reviewer_id
                     LEFT JOIN inspections i ON i.report_id=r.id
                     LEFT JOIN users iu ON iu.id=i.reviewer_id
                     LEFT JOIN reviewer_profiles irp ON irp.user_id=iu.id
                     WHERE r.public_code=? AND r.moderation_status='published'
                     ORDER BY i.id DESC
                     LIMIT 1");
$st->execute([$code]);
$r = $st->fetch();
if(!$r){ http_response_code(404); exit('Reporte no encontrado'); }

$ph = db()->prepare("SELECT * FROM damage_photos WHERE report_id=? AND is_public=1 AND moderation_status='approved' ORDER BY id");
$ph->execute([$r['id']]);
$photos = $ph->fetchAll();
$ev = db()->prepare('SELECT event_type,created_at FROM report_events WHERE report_id=? ORDER BY id');
$ev->execute([$r['id']]);
$events = $ev->fetchAll();
render_header($r['public_code']);
?>
<div class="container py-4"><div class="row g-4"><div class="col-lg-8">
<div class="d-flex justify-content-between align-items-start gap-3"><div><div class="small text-muted">Reporte <?=e($r['public_code'])?></div><h1><?=e($r['neighborhood'])?>, <?=e($r['municipality'])?></h1></div><div class="text-end"><div class="priority priority-<?=e($r['system_priority'])?>"><?=e(priority_label($r['system_priority']))?></div><span class="badge text-bg-secondary"><?=e(status_label($r['status']))?></span></div></div>
<p class="lead"><?=nl2br(e($r['description_public']))?></p>
<div class="row g-2 mb-4"><?php foreach($photos as $p):?><div class="col-6 col-md-4"><a href="<?=e($p['storage_path'])?>" target="_blank" rel="noopener"><img class="thumb" src="<?=e($p['storage_path'])?>" alt="Evidencia"></a></div><?php endforeach?></div>
<?php if($r['public_diagnosis']):?><div class="card border-success mb-4"><div class="card-body"><h4>Evaluación publicada</h4><div class="small text-muted mb-2"><?=e($r['inspection_reviewer_name'])?><?= $r['inspection_reviewer_profession']?' · '.e($r['inspection_reviewer_profession']):'' ?><?= $r['inspection_reviewer_verification']==='verified'?' · Profesional verificado':'' ?></div><p><strong>Tipo:</strong> <?=e($r['inspection_type'])?> · <strong>Fecha:</strong> <?=e($r['inspection_date'])?></p><p><strong>Hallazgos:</strong><br><?=nl2br(e($r['findings_public']))?></p><p><strong>Recomendación:</strong> <?=e($r['recommendation'])?></p><p class="mb-0"><strong>Diagnóstico / observación:</strong><br><?=nl2br(e($r['public_diagnosis']))?></p></div></div><?php endif?>
<h4>Historial</h4><div class="timeline"><?php foreach($events as $x):?><div class="timeline-item"><strong><?=e(event_label($x['event_type']))?></strong><div class="small text-muted"><?=e(date('d/m/Y H:i',strtotime($x['created_at'])))?></div></div><?php endforeach?></div>
</div><div class="col-lg-4"><div class="card"><div class="card-body"><h5>Estado</h5><p><?=e(status_label($r['status']))?></p><h6>Ubicación pública</h6><p><?=e($r['neighborhood'])?><?= $r['sector']?' · '.e($r['sector']):'' ?><br><?=e($r['municipality'])?>, <?=e($r['department'])?></p>
<?php if(is_reviewer()): $take=can_take_report($r); if($take[0]):?><form method="post" action="take-case.php"><?=csrf_field()?><input type="hidden" name="report_id" value="<?=$r['id']?>"><button class="btn btn-primary w-100">Tomar revisión</button></form><?php elseif($r['assigned_reviewer_name']):?><p><strong>Revisor asignado:</strong><br><?=e($r['assigned_reviewer_name'])?></p><div class="alert alert-light border small mb-0"><?=e($take[1])?></div><?php else:?><div class="alert alert-light border small mb-0"><?=e($take[1])?></div><?php endif; elseif($r['assigned_reviewer_name']):?><p><strong>Revisor asignado:</strong><br><?=e($r['assigned_reviewer_name'])?></p><?php endif?></div></div>
<?php if(is_reviewer() && (int)$r['assigned_reviewer_id']===(int)(user()['id']??0)):?><div class="private-note mt-3"><strong>Datos privados para coordinación</strong><br>Dirección: <?=e($r['address_private'])?><br><a class="btn btn-sm btn-primary mt-2" href="review-case.php?id=<?=$r['id']?>">Gestionar este caso</a></div><?php endif?></div></div></div>
<?php render_footer(); ?>
