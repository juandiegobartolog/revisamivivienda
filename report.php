<?php
require __DIR__.'/app/bootstrap.php';
$pdo = db();

if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    $required=['department_id','municipality_id','neighborhood','address_private','reporter_name_private','reporter_phone_private','description_public','perceived_priority'];
    foreach($required as $f){ if(trim((string)($_POST[$f]??''))===''){ flash('error','Completa todos los campos obligatorios.'); redirect('report.php'); } }
    if(empty($_POST['consent']) || empty($_POST['truth'])){ flash('error','Debes aceptar las declaraciones de tratamiento de datos y veracidad.'); redirect('report.php'); }

    $departmentId = (int)$_POST['department_id'];
    $municipalityId = (int)$_POST['municipality_id'];
    $loc = $pdo->prepare("SELECT COUNT(*) FROM municipalities m JOIN departments d ON d.id=m.department_id WHERE m.id=? AND m.department_id=? AND m.is_enabled_for_reports=1 AND d.is_active=1");
    $loc->execute([$municipalityId,$departmentId]);
    if(!(int)$loc->fetchColumn()){ flash('error','La ciudad seleccionada no está habilitada para recibir reportes.'); redirect('report.php'); }

    $per = $_POST['perceived_priority'];
    if(!in_array($per,['urgent','high','medium','low'],true)){ flash('error','Prioridad no válida.'); redirect('report.php'); }

    $pdo->beginTransaction();
    try{
        $st=$pdo->prepare('INSERT INTO properties(public_uuid,department_id,municipality_id,neighborhood,sector,address_private,created_at) VALUES(UUID(),?,?,?,?,?,NOW())');
        $st->execute([$departmentId,$municipalityId,trim($_POST['neighborhood']),trim($_POST['sector']??''),trim($_POST['address_private'])]);
        $propertyId=$pdo->lastInsertId();

        $signals=['falling','columns','leaning','roof','collapsed','evacuated'];
        $score=0; foreach($signals as $s) if(!empty($_POST[$s])) $score++;
        $signalPriority = $score >= 2 ? 'urgent' : ($score === 1 ? 'high' : 'low');
        $system = priority_rank($per) <= priority_rank($signalPriority) ? $per : $signalPriority;

        $code=public_code();
        $st=$pdo->prepare("INSERT INTO damage_reports(public_code,property_id,reporter_name_private,reporter_phone_private,reporter_email_private,description_public,perceived_priority,system_priority,status,moderation_status,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?, 'pending','published',NOW(),NOW())");
        $st->execute([$code,$propertyId,trim($_POST['reporter_name_private']),trim($_POST['reporter_phone_private']),trim($_POST['reporter_email_private']??''),trim($_POST['description_public']),$per,$system]);
        $rid=$pdo->lastInsertId();

        $ans=$pdo->prepare('INSERT INTO damage_answers(report_id,question_key,answer_value) VALUES(?,?,?)');
        foreach($signals as $s) $ans->execute([$rid,$s,!empty($_POST[$s])?'yes':'no']);

        $uploadWarnings = 0;
        if(!empty($_FILES['photos']['name'][0])){
            $allowed=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
            $max=(int)config('max_photo_mb')*1024*1024;
            $count=min(count($_FILES['photos']['name']),(int)config('max_photos'));
            $finfo=new finfo(FILEINFO_MIME_TYPE);
            $uploadDir = rtrim((string)config('uploads_dir'),'/');
            // Compatibilidad con instalaciones 0.1.x donde config apuntaba a /public/uploads.
            if(!$uploadDir || (!is_dir($uploadDir) && is_dir(__DIR__.'/uploads'))) $uploadDir = __DIR__.'/uploads';
            if(!is_dir($uploadDir) && !mkdir($uploadDir,0755,true)) throw new RuntimeException('No fue posible preparar el directorio de evidencias.');
            for($i=0;$i<$count;$i++){
                if($_FILES['photos']['error'][$i]!==UPLOAD_ERR_OK){ $uploadWarnings++; continue; }
                if($_FILES['photos']['size'][$i]>$max){ $uploadWarnings++; continue; }
                $tmp=$_FILES['photos']['tmp_name'][$i];
                $mime=$finfo->file($tmp);
                if(!isset($allowed[$mime])){ $uploadWarnings++; continue; }
                $name=bin2hex(random_bytes(16)).'.'.$allowed[$mime];
                $dest=$uploadDir.'/'.$name;
                if(move_uploaded_file($tmp,$dest)){
                    $path='uploads/'.$name;
                    $ps=$pdo->prepare("INSERT INTO damage_photos(report_id,storage_disk,storage_path,mime_type,file_size,is_public,moderation_status,created_at) VALUES(?,?,?,?,?,1,'approved',NOW())");
                    $ps->execute([$rid,'local',$path,$mime,$_FILES['photos']['size'][$i]]);
                } else { $uploadWarnings++; }
            }
        }

        $pdo->prepare("INSERT INTO report_events(report_id,event_type,payload_json,created_at) VALUES(?,'report_created','{}',NOW())")->execute([$rid]);
        $pdo->commit();
        flash('success','Tu reporte fue registrado correctamente. Código: '.$code);
        if(!empty($uploadWarnings)) flash('warning','El reporte se guardó, pero una o más fotografías no pudieron cargarse por formato, tamaño o error de transferencia.');
        redirect('case.php?code='.urlencode($code));
    }catch(Throwable $e){
        if($pdo->inTransaction()) $pdo->rollBack();
        flash('error','No fue posible registrar el reporte. Intenta nuevamente.');
        redirect('report.php');
    }
}
$deps=$pdo->query('SELECT id,name FROM departments WHERE is_active=1 ORDER BY name')->fetchAll();
render_header('Reportar vivienda');
?>
<div class="container py-4" style="max-width:900px"><h1>Reportar una vivienda afectada</h1><p class="text-muted">La dirección exacta y tus datos de contacto no se mostrarán públicamente.</p><form method="post" enctype="multipart/form-data"><?=csrf_field()?>
<div class="form-section"><h4>Ubicación</h4><div class="row g-3"><div class="col-md-6"><label class="form-label">Departamento *</label><select class="form-select" name="department_id" data-department required><option value="">Seleccione...</option><?php foreach($deps as $d):?><option value="<?=$d['id']?>"><?=e($d['name'])?></option><?php endforeach?></select></div><div class="col-md-6"><label class="form-label">Ciudad / municipio *</label><select class="form-select" name="municipality_id" data-municipality required><option value="">Seleccione primero un departamento</option></select></div><div class="col-md-6"><label class="form-label">Barrio *</label><input class="form-control" name="neighborhood" required></div><div class="col-md-6"><label class="form-label">Sector</label><input class="form-control" name="sector"></div><div class="col-12"><label class="form-label">Dirección exacta *</label><input class="form-control" name="address_private" required><div class="form-text">Dato privado: solo administradores y revisor asignado.</div></div></div></div>
<div class="form-section"><h4>Contacto</h4><div class="row g-3"><div class="col-md-6"><label class="form-label">Nombre *</label><input class="form-control" name="reporter_name_private" required></div><div class="col-md-6"><label class="form-label">Teléfono *</label><input class="form-control" name="reporter_phone_private" required></div><div class="col-12"><label class="form-label">Correo</label><input type="email" class="form-control" name="reporter_email_private"></div></div></div>
<div class="form-section"><h4>Afectación</h4><label class="form-label">Descripción del problema *</label><textarea class="form-control mb-3" name="description_public" rows="5" required></textarea><label class="form-label">Urgencia percibida *</label><select class="form-select" name="perceived_priority" required><option value="urgent">Urgente</option><option value="high">Alta</option><option value="medium" selected>Media</option><option value="low">Baja</option></select><hr><h6>Señales observables</h6><?php foreach(['falling'=>'Hay desprendimientos visibles','columns'=>'Columnas con grietas importantes','leaning'=>'Paredes o elementos inclinados','roof'=>'Techo/losa desplazado o comprometido','collapsed'=>'Parte de la estructura colapsó','evacuated'=>'La vivienda tuvo que ser evacuada'] as $k=>$v):?><div class="form-check"><input class="form-check-input" type="checkbox" name="<?=$k?>" id="<?=$k?>"><label class="form-check-label" for="<?=$k?>"><?=e($v)?></label></div><?php endforeach?></div>
<div class="form-section"><h4>Evidencias</h4><label class="form-label">Fotografías (máximo <?=e(config('max_photos'))?>, JPG/PNG/WebP)</label><input id="photos" class="form-control" type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple><div class="form-text">Evita fotografías con documentos, menores, placas u otra información personal innecesaria.</div></div>
<div class="form-section"><div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="consent" value="1" required id="consent"><label class="form-check-label" for="consent">Autorizo el tratamiento de mis datos para gestionar este reporte y entiendo qué información será pública.</label></div><div class="form-check"><input class="form-check-input" type="checkbox" name="truth" value="1" required id="truth"><label class="form-check-label" for="truth">Confirmo que la información es verdadera según mi conocimiento y entiendo que esta plataforma no reemplaza una evaluación oficial ni un servicio de emergencia.</label></div></div><button class="btn btn-primary btn-lg w-100">Registrar afectación</button></form></div>
<?php render_footer(); ?>
