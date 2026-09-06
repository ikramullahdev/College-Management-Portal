<?php
function gradeForPercent(PDO $pdo, float $percent): array {
    $q=$pdo->prepare('SELECT letter_grade,grade_point FROM grade_scales WHERE ? BETWEEN min_percent AND max_percent ORDER BY min_percent DESC LIMIT 1');
    $q->execute([$percent]);
    return $q->fetch() ?: ['letter_grade'=>'F','grade_point'=>0.00];
}
function studentResult(PDO $pdo, int $studentId, ?string $term=null): array {
    $where='WHERE e.student_id=?'; $params=[$studentId];
    if($term!==null && $term!==''){ $where.=" AND (s.semester=? OR s.semester IS NULL OR s.semester='')"; $params[]=$term; }
    $sql="SELECT e.id enrollment_id,s.id subject_id,s.code,s.name,s.credit_hours,s.semester,
      COALESCE(SUM(m.obtained),0) obtained,COALESCE(SUM(m.total),0) total
      FROM enrollments e JOIN subjects s ON s.id=e.subject_id
      LEFT JOIN marks m ON m.enrollment_id=e.id
      $where GROUP BY e.id,s.id,s.code,s.name,s.credit_hours,s.semester ORDER BY s.semester,s.code,s.name";
    $q=$pdo->prepare($sql);$q->execute($params);$rows=$q->fetchAll();
    foreach($rows as &$r){
      $r['percent']=$r['total']>0?($r['obtained']/$r['total'])*100:0;
      $g=gradeForPercent($pdo,(float)$r['percent']); $r['letter_grade']=$g['letter_grade']; $r['grade_point']=(float)$g['grade_point'];
      $r['quality_points']=(float)$r['credit_hours']*$r['grade_point'];
    }
    unset($r); $credits=0;$quality=0;
    foreach($rows as $r){$credits+=(float)$r['credit_hours'];$quality+=(float)$r['quality_points'];}
    return ['rows'=>$rows,'credits'=>$credits,'quality'=>$quality,'gpa'=>$credits>0?$quality/$credits:0];
}
function studentTerms(PDO $pdo, int $studentId): array {
    $q=$pdo->prepare("SELECT DISTINCT s.semester FROM enrollments e JOIN subjects s ON s.id=e.subject_id WHERE e.student_id=? AND s.semester IS NOT NULL AND s.semester<>'' ORDER BY s.semester");
    $q->execute([$studentId]); return $q->fetchAll(PDO::FETCH_COLUMN);
}
function studentOverall(PDO $pdo, int $studentId): array { return studentResult($pdo,$studentId,null); }
