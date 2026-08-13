<?php
require __DIR__.'/../app/bootstrap.php';
$pdo=db();
$st=$pdo->query("SELECT id,report_id FROM report_assignments WHERE status='active' AND expires_at<NOW()");
foreach($st as $a){
    $pdo->beginTransaction();
    try{
        $pdo->prepare("UPDATE report_assignments SET status='expired',released_at=NOW() WHERE id=? AND status='active'")->execute([$a['id']]);
        $pdo->prepare("UPDATE damage_reports SET status='pending',updated_at=NOW() WHERE id=? AND status IN ('assigned','contacted','scheduled','reviewing','second_opinion')")->execute([$a['report_id']]);
        $pdo->prepare("INSERT INTO report_events(report_id,event_type,payload_json,created_at) VALUES(?,'assignment_expired','{}',NOW())")->execute([$a['report_id']]);
        $pdo->commit();
    }catch(Throwable $e){ if($pdo->inTransaction()) $pdo->rollBack(); }
}
echo "Proceso completado\n";
