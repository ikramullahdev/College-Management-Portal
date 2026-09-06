<?php
function currentStudent(PDO $pdo, int $userId): ?array {
    $q=$pdo->prepare('SELECT st.*,u.name,u.email,p.level,p.name program_name,p.specialization FROM students st JOIN users u ON u.id=st.user_id JOIN programs p ON p.id=st.program_id WHERE st.user_id=?');
    $q->execute([$userId]); return $q->fetch() ?: null;
}
function documentTypes(): array {
    return ['photo'=>'Photograph','cnic_bform'=>'CNIC / B-Form','matric_certificate'=>'Matric Certificate','inter_certificate'=>'Intermediate Certificate','domicile'=>'Domicile','previous_result'=>'Previous Result Card','other'=>'Other'];
}
function documentSummary(PDO $pdo,int $studentId): array {
    $q=$pdo->prepare('SELECT document_type,status,COUNT(*) c FROM student_documents WHERE student_id=? GROUP BY document_type,status');$q->execute([$studentId]);
    $out=[]; foreach($q as $r){$out[$r['document_type']][$r['status']]=(int)$r['c'];} return $out;
}
