<?php
require __DIR__.'/app/bootstrap.php';
require_reviewer();
$pdo = db();
$userId = (int)user()['id'];

// Casos activos del revisor.
$st = $pdo->prepare("SELECT a.*, r.public_code, r.status, r.system_priority, r.description_public,
                            p.neighborhood, p.address_private, m.name municipality, d.name department
                     FROM report_assignments a
                     JOIN damage_reports r ON r.id=a.report_id
                     JOIN properties p ON p.id=r.property_id
                     JOIN municipalities m ON m.id=p.municipality_id
                     JOIN departments d ON d.id=p.department_id
                     WHERE a.reviewer_id=? AND a.status='active'
                     ORDER BY FIELD(r.system_priority,'urgent','high','medium','low'), a.assigned_at ASC");
$st->execute([$userId]);
$activeCases = $st->fetchAll();
$activeCount = active_case_count($userId);
$account = account_state();
$maxActive = (int)(config('max_active_cases') ?: 5);

// Filtros de casos disponibles.
$dept = (int)($_GET['department'] ?? 0);
$city = (int)($_GET['city'] ?? 0);
$priority = $_GET['priority'] ?? '';
$allowedPriorities = ['urgent','high','medium','low'];
$where = ["r.status='pending'", "r.moderation_status='published'"];
$params = [];
if ($dept > 0) { $where[]='p.department_id=?'; $params[]=$dept; }
if ($city > 0) { $where[]='p.municipality_id=?'; $params[]=$city; }
if (in_array($priority,$allowedPriorities,true)) { $where[]='r.system_priority=?'; $params[]=$priority; }

$sql = "SELECT r.id, r.public_code, r.system_priority, r.description_public, r.created_at,
               p.neighborhood, p.sector, m.name municipality, d.name department,
               (SELECT storage_path FROM damage_photos ph WHERE ph.report_id=r.id AND ph.is_public=1 ORDER BY ph.id LIMIT 1) photo
        FROM damage_reports r
        JOIN properties p ON p.id=r.property_id
        JOIN municipalities m ON m.id=p.municipality_id
        JOIN departments d ON d.id=p.department_id
        WHERE ".implode(' AND ',$where)."
        ORDER BY FIELD(r.system_priority,'urgent','high','medium','low'), r.created_at ASC
        LIMIT 100";
$st = $pdo->prepare($sql);
$st->execute($params);
$availableCases = $st->fetchAll();

$departments = $pdo->query("SELECT id,name FROM departments WHERE is_active=1 ORDER BY name")->fetchAll();
$municipalities = [];
if ($dept > 0) {
    $st = $pdo->prepare("SELECT id,name FROM municipalities WHERE department_id=? AND is_enabled_for_reports=1 ORDER BY name");
    $st->execute([$dept]);
    $municipalities = $st->fetchAll();
}

render_header('Panel del revisor');
?>
<div class="container py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h1 class="mb-1">Panel del revisor</h1>
            <p class="text-muted mb-1">Gestiona tus casos asignados y toma nuevos casos pendientes desde un solo lugar.</p><div class="small"><strong>Sesión:</strong> <?=e($account['name'] ?? user()['name'])?> · <?=e(role_label($account['role'] ?? 'reviewer'))?><?php if(($account['verification_status']??'')!==''):?> · <?=e(verification_label($account['verification_status']))?><?php endif?></div>
        </div>
        <div class="reviewer-capacity <?= $activeCount >= $maxActive ? 'at-limit' : '' ?>">
            <strong><?= $activeCount ?> de <?= $maxActive ?></strong>
            <span>casos activos</span>
        </div>
    </div>

    <section class="mb-5" id="mis-casos">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h2 class="h4 mb-1">Mis casos activos</h2>
                <div class="text-muted small">Solo tú puedes ver los datos privados de los casos que tienes asignados.</div>
            </div>
        </div>

        <div class="row g-3">
            <?php foreach($activeCases as $c): ?>
                <div class="col-lg-6">
                    <div class="card h-100 shadow-sm border-0">
                        <div class="card-body">
                            <div class="d-flex justify-content-between gap-2 align-items-start">
                                <div>
                                    <strong><?=e($c['public_code'])?></strong>
                                    <div class="small text-muted"><?=e(status_label($c['status']))?></div>
                                </div>
                                <span class="priority priority-<?=e($c['system_priority'])?>"><?=e(priority_label($c['system_priority']))?></span>
                            </div>
                            <h5 class="mt-3 mb-1"><?=e($c['neighborhood'])?> · <?=e($c['municipality'])?></h5>
                            <div class="small text-muted mb-3"><?=e($c['department'])?></div>
                            <div class="private-note mb-3"><strong>Dirección privada:</strong> <?=e($c['address_private'])?></div>
                            <p><?=e(mb_strimwidth($c['description_public'],0,180,'…'))?></p>
                            <div class="d-flex flex-wrap gap-2">
                                <a class="btn btn-primary" href="review-case.php?id=<?=$c['report_id']?>">Gestionar caso</a>
                                <a class="btn btn-outline-secondary" href="case.php?code=<?=urlencode($c['public_code'])?>">Vista pública</a>
                            </div>
                            <div class="small text-muted mt-3">Asignado: <?=e(date('d/m/Y H:i',strtotime($c['assigned_at'])))?> · vence: <?=e(date('d/m/Y H:i',strtotime($c['expires_at'])))?></div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php if(!$activeCases): ?><div class="alert alert-info">Aún no tienes casos activos. Puedes tomar uno de los casos disponibles abajo.</div><?php endif; ?>
    </section>

    <section id="casos-disponibles">
        <div class="mb-3">
            <h2 class="h4 mb-1">Casos disponibles para revisión</h2>
            <div class="text-muted small">Se ordenan primero por prioridad y, dentro de la misma prioridad, por antigüedad.</div>
        </div>

        <form class="card card-body mb-4" method="get">
            <div class="row g-2">
                <div class="col-md-4">
                    <label class="form-label small">Departamento</label>
                    <select class="form-select" name="department" data-department>
                        <option value="">Todos</option>
                        <?php foreach($departments as $d): ?><option value="<?=$d['id']?>" <?=$dept===$d['id']?'selected':''?>><?=e($d['name'])?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label small">Ciudad / municipio</label>
                    <select class="form-select" name="city" data-municipality>
                        <option value="">Todas</option>
                        <?php foreach($municipalities as $m): ?><option value="<?=$m['id']?>" <?=$city===$m['id']?'selected':''?>><?=e($m['name'])?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Prioridad</label>
                    <select class="form-select" name="priority">
                        <option value="">Todas</option>
                        <?php foreach($allowedPriorities as $p): ?><option value="<?=$p?>" <?=$priority===$p?'selected':''?>><?=e(priority_label($p))?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-1 d-flex align-items-end"><button class="btn btn-primary w-100" title="Filtrar">Ir</button></div>
            </div>
        </form>

        <?php if($activeCount >= $maxActive): ?>
            <div class="alert alert-warning">Ya alcanzaste el máximo de <?= $maxActive ?> casos activos. Debes completar o liberar un caso antes de tomar otro.</div>
        <?php endif; ?>

        <div class="row g-3">
            <?php foreach($availableCases as $c): ?>
                <div class="col-md-6 col-xl-4">
                    <div class="card report-card h-100">
                        <?php if($c['photo']): ?><img class="card-img-top" style="height:180px;object-fit:cover" src="<?=e($c['photo'])?>" alt="Evidencia fotográfica"><?php endif; ?>
                        <div class="card-body d-flex flex-column">
                            <div class="d-flex justify-content-between gap-2">
                                <span class="priority priority-<?=e($c['system_priority'])?>"><?=e(priority_label($c['system_priority']))?></span>
                                <span class="badge text-bg-light">Pendiente</span>
                            </div>
                            <h5 class="mt-3 mb-1"><?=e($c['neighborhood'] ?: $c['sector'])?></h5>
                            <div class="text-muted small mb-2"><?=e($c['municipality'])?>, <?=e($c['department'])?></div>
                            <p class="flex-grow-1"><?=e(mb_strimwidth($c['description_public'],0,170,'…'))?></p>
                            <div class="d-flex flex-wrap gap-2">
                                <a class="btn btn-outline-primary" href="case.php?code=<?=urlencode($c['public_code'])?>">Ver detalles</a>
                                <form method="post" action="take-case.php" class="m-0">
                                    <?=csrf_field()?>
                                    <input type="hidden" name="report_id" value="<?=$c['id']?>">
                                    <button class="btn btn-primary" <?= $activeCount >= $maxActive ? 'disabled' : '' ?>>Tomar revisión</button>
                                </form>
                            </div>
                        </div>
                        <div class="card-footer small text-muted">Reportado <?=e(date('d/m/Y H:i',strtotime($c['created_at'])))?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php if(!$availableCases): ?><div class="alert alert-info"><strong>No hay casos disponibles para tomar con estos filtros.</strong><br>Solo aparecen aquí reportes publicados cuyo estado interno sea <code>pending</code>. Si ves reportes pendientes en la portada pero no aquí, revisaremos su estado en base de datos.</div><?php endif; ?>
    </section>
</div>
<?php render_footer(); ?>
