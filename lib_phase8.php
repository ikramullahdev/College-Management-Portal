<?php

function teacherId(PDO $pdo,int $userId): int {
  $q=$pdo->prepare('SELECT id FROM teachers WHERE user_id=?');
  $q->execute([$userId]);
  return (int)$q->fetchColumn();
}

function teacherCanSubject(PDO $pdo,int $userId,int $subjectId): bool {
  $tid=teacherId($pdo,$userId);
  if(!$tid)return false;

  $q=$pdo->prepare(
    'SELECT 1 FROM teacher_subjects WHERE teacher_id=? AND subject_id=?'
  );
  $q->execute([$tid,$subjectId]);

  return (bool)$q->fetchColumn();
}

function teacherCanAssignment(PDO $pdo,int $userId,int $assignmentId): bool {
  $tid=teacherId($pdo,$userId);
  if(!$tid || !$assignmentId)return false;

  $q=$pdo->prepare(
    'SELECT 1 FROM teacher_assignments WHERE id=? AND teacher_id=? AND active=1'
  );
  $q->execute([$assignmentId,$tid]);

  return (bool)$q->fetchColumn();
}

function getTeacherAssignment(PDO $pdo,int $userId,int $assignmentId): ?array {
  $tid=teacherId($pdo,$userId);
  if(!$tid || !$assignmentId)return null;

  $q=$pdo->prepare("
    SELECT
      ta.*,
      t.department,
      s.code,
      s.name subject_name,
      s.credit_hours,
      p.level,
      p.name program_name,
      p.specialization,
      sec.name section_name
    FROM teacher_assignments ta
    JOIN teachers t ON t.id=ta.teacher_id
    JOIN subjects s ON s.id=ta.subject_id
    JOIN programs p ON p.id=ta.program_id
    LEFT JOIN sections sec ON sec.id=ta.section_id
    WHERE ta.id=?
      AND ta.teacher_id=?
      AND ta.active=1
    LIMIT 1
  ");

  $q->execute([$assignmentId,$tid]);

  $r=$q->fetch(PDO::FETCH_ASSOC);

  return $r?:null;
}

function resultStatus(PDO $pdo,int $studentId,string $term): string {
  $q=$pdo->prepare(
    'SELECT status FROM result_status WHERE student_id=? AND term=?'
  );
  $q->execute([$studentId,$term]);

  return $q->fetchColumn() ?: 'draft';
}

function attendanceSummary(PDO $pdo,int $studentId,string $term=''): array {

  $sql="
    SELECT
      s.id,
      s.code,
      s.name,
      COUNT(a.id) total,
      SUM(a.status='present') present,
      SUM(a.status='absent') absent,
      SUM(a.status='leave') leave_count
    FROM enrollments e
    JOIN subjects s ON s.id=e.subject_id
    LEFT JOIN attendance a ON a.enrollment_id=e.id
    WHERE e.student_id=?
  ";

  $args=[$studentId];

  if($term!==''){
    $sql.="
      AND (
        s.semester=?
        OR s.semester IS NULL
        OR s.semester=''
      )
    ";

    $args[]=$term;
  }

  $sql.='
    GROUP BY s.id,s.code,s.name
    ORDER BY s.code,s.name
  ';

  $q=$pdo->prepare($sql);
  $q->execute($args);

  $rows=$q->fetchAll();

  foreach($rows as &$r){

    $r['present']=(int)$r['present'];
    $r['absent']=(int)$r['absent'];

    // IMPORTANT: SQL alias is leave_count
    $r['leave']=(int)$r['leave_count'];

    $r['total']=(int)$r['total'];

    $r['percentage']=$r['total']
      ? ($r['present']/$r['total']*100)
      : 0;
  }

  unset($r);

  return $rows;
}

function allStudentTerms(PDO $pdo,int $studentId): array {

  $q=$pdo->prepare("
    SELECT DISTINCT s.semester
    FROM enrollments e
    JOIN subjects s ON s.id=e.subject_id
    WHERE e.student_id=?
      AND s.semester IS NOT NULL
      AND s.semester<>''
    ORDER BY FIELD(
      s.semester,
      'Part-I',
      'Part-II',
      '1st Year',
      '2nd Year',
      '1st Semester',
      '2nd Semester',
      '3rd Semester',
      '4th Semester',
      '5th Semester',
      '6th Semester',
      '7th Semester',
      '8th Semester'
    )
  ");

  $q->execute([$studentId]);

  return $q->fetchAll(PDO::FETCH_COLUMN);
}
