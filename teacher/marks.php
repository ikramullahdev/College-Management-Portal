<?php
session_start();

require '../config/database.php';
require '../lib_security.php';
require '../lib_phase8.php';
require '../lib_results.php';

if (
    !isset($_SESSION['user']) ||
    $_SESSION['user']['role'] !== 'teacher'
) {
    header('Location: ../login.php');
    exit;
}

$userId = (int)$_SESSION['user']['id'];

$assignmentId = (int)(
    $_GET['assignment_id']
    ?? $_POST['assignment_id']
    ?? 0
);

$assignment = getTeacherAssignment(
    $pdo,
    $userId,
    $assignmentId
);

if (!$assignment) {
    exit('Assignment not found or you are not authorized for this class.');
}

$subjectId = (int)$assignment['subject_id'];
$term = $assignment['term'];
$programId = (int)$assignment['program_id'];
$sectionId = (int)($assignment['section_id'] ?? 0);

/*
|--------------------------------------------------------------------------
| Assessment
|--------------------------------------------------------------------------
*/

$assessment = trim(
    $_POST['assessment']
    ?? $_GET['assessment']
    ?? 'Quiz'
);

$allowed = [
    'Quiz',
    'Assignment',
    'Midterm',
    'Final'
];

if (!in_array($assessment, $allowed, true)) {
    $assessment = 'Quiz';
}

/*
|--------------------------------------------------------------------------
| Total Marks
|--------------------------------------------------------------------------
*/

$total = (float)($_POST['total'] ?? 100);

if ($total <= 0) {
    $total = 100;
}

$msg = '';
$msgType = 'info';

/*
|--------------------------------------------------------------------------
| Save Marks
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['obtained'])
) {
    verifyCsrf();

    $pdo->beginTransaction();

    try {

        foreach ($_POST['obtained'] as $eid => $obtained) {

            $eid = (int)$eid;

            /*
            | Verify enrollment belongs to this teacher assignment
            */

            $sql = '
                SELECT
                    e.id AS enrollment_id,
                    st.id AS student_id,
                    st.semester_or_year
                FROM enrollments e
                JOIN students st
                    ON st.id = e.student_id
                WHERE
                    e.id = ?
                    AND e.subject_id = ?
                    AND st.program_id = ?
                    AND st.semester_or_year = ?
            ';

            $args = [
                $eid,
                $subjectId,
                $programId,
                $term
            ];

            if ($sectionId) {
                $sql .= ' AND st.section_id = ?';
                $args[] = $sectionId;
            }

            $sql .= ' LIMIT 1';

            $q = $pdo->prepare($sql);
            $q->execute($args);

            $stu = $q->fetch(PDO::FETCH_ASSOC);

            if (!$stu) {
                continue;
            }

            /*
            | Locked result cannot be edited
            */

            if (
                resultStatus(
                    $pdo,
                    (int)$stu['student_id'],
                    $term
                ) === 'locked'
            ) {
                continue;
            }

            /*
            | Keep obtained marks between 0 and total
            */

            $obt = max(
                0,
                min((float)$obtained, $total)
            );

            /*
            | Check whether this assessment already exists
            */

            $q = $pdo->prepare('
                SELECT id
                FROM marks
                WHERE enrollment_id = ?
                AND assessment = ?
                LIMIT 1
            ');

            $q->execute([
                $eid,
                $assessment
            ]);

            $id = $q->fetchColumn();

            /*
            | Update existing marks
            */

            if ($id) {

                $q = $pdo->prepare('
                    UPDATE marks
                    SET obtained = ?, total = ?
                    WHERE id = ?
                ');

                $q->execute([
                    $obt,
                    $total,
                    $id
                ]);

            }

            /*
            | Insert new marks
            */

            else {

                $q = $pdo->prepare('
                    INSERT INTO marks
                    (
                        enrollment_id,
                        assessment,
                        obtained,
                        total
                    )
                    VALUES (?, ?, ?, ?)
                ');

                $q->execute([
                    $eid,
                    $assessment,
                    $obt,
                    $total
                ]);
            }
        }

        $pdo->commit();

        $msg =
            'Marks saved successfully for '
            . $assignment['level']
            . ' — '
            . $assignment['program_name']
            . ' — '
            . $term
            . '.';

        $msgType = 'success';

    } catch (Throwable $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $msg = 'Unable to save marks. Please try again.';
        $msgType = 'danger';
    }
}

/*
|--------------------------------------------------------------------------
| Students
|--------------------------------------------------------------------------
*/

$sql = '
    SELECT
        e.id AS enrollment_id,
        st.id AS student_id,
        st.semester_or_year,
        u.name,
        st.roll_no
    FROM enrollments e
    JOIN students st
        ON st.id = e.student_id
    JOIN users u
        ON u.id = st.user_id
    WHERE
        e.subject_id = ?
        AND st.program_id = ?
        AND st.semester_or_year = ?
';

$args = [
    $subjectId,
    $programId,
    $term
];

if ($sectionId) {
    $sql .= ' AND st.section_id = ?';
    $args[] = $sectionId;
}

$sql .= ' ORDER BY st.roll_no';

$st = $pdo->prepare($sql);
$st->execute($args);

$students = $st->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Enter Marks';

require '../includes/header.php';
?>

<div class="container py-4">

    <a href="index.php" class="btn btn-link ps-0">
        ← Teacher Dashboard
    </a>

    <div class="mb-3">

        <h3 class="fw-bold mb-1">
            Marks — <?= htmlspecialchars($assignment['subject_name']) ?>
        </h3>

        <div class="text-muted">
            <?= htmlspecialchars(
                $assignment['level']
                . ' — '
                . $assignment['program_name']
                . ' — '
                . $term
                . ' — '
                . ($assignment['section_name'] ?: 'All Sections')
            ) ?>
        </div>

    </div>

    <?php if ($msg): ?>

        <div class="alert alert-<?= htmlspecialchars($msgType) ?>">
            <?= htmlspecialchars($msg) ?>
        </div>

    <?php endif; ?>


    <div class="card shadow-sm border-0">

        <div class="card-body">

            <form method="post">

                <?= csrfField() ?>

                <input
                    type="hidden"
                    name="assignment_id"
                    value="<?= $assignmentId ?>"
                >


                <!-- Assessment + Total Marks -->

                <div class="row g-3 mb-3">

                    <div class="col-md-3">

                        <label class="form-label">
                            Assessment
                        </label>

                        <select
                            name="assessment"
                            id="assessmentSelect"
                            class="form-select"
                        >

                            <option
                                value="Quiz"
                                <?= $assessment === 'Quiz' ? 'selected' : '' ?>
                            >
                                Quiz
                            </option>

                            <option
                                value="Assignment"
                                <?= $assessment === 'Assignment' ? 'selected' : '' ?>
                            >
                                Assignment
                            </option>

                            <option
                                value="Midterm"
                                <?= $assessment === 'Midterm' ? 'selected' : '' ?>
                            >
                                Midterm
                            </option>

                            <option
                                value="Final"
                                <?= $assessment === 'Final' ? 'selected' : '' ?>
                            >
                                Final
                            </option>

                        </select>

                    </div>


                    <div class="col-md-2">

                        <label class="form-label">
                            Total Marks
                        </label>

                        <input
                            type="number"
                            step="0.01"
                            min="1"
                            name="total"
                            value="<?= htmlspecialchars($total) ?>"
                            class="form-control"
                            required
                        >

                    </div>

                </div>


                <!-- Students -->

                <div class="table-responsive">

                    <table class="table align-middle">

                        <thead>

                            <tr>

                                <th>Roll No</th>

                                <th>Student</th>

                                <th>Term</th>

                                <th>Status</th>

                                <th style="width:180px">
                                    Obtained
                                </th>

                            </tr>

                        </thead>


                        <tbody>

                        <?php foreach ($students as $r): ?>

                            <?php

                            $locked =
                                resultStatus(
                                    $pdo,
                                    (int)$r['student_id'],
                                    $term
                                ) === 'locked';

                            $mq = $pdo->prepare('
                                SELECT obtained, total
                                FROM marks
                                WHERE enrollment_id = ?
                                AND assessment = ?
                                LIMIT 1
                            ');

                            $mq->execute([
                                (int)$r['enrollment_id'],
                                $assessment
                            ]);

                            $ex = $mq->fetch(PDO::FETCH_ASSOC);

                            $val = $ex['obtained'] ?? '';

                            $rowTotal = isset($ex['total'])
                                ? (float)$ex['total']
                                : $total;

                            ?>

                            <tr>

                                <td>
                                    <?= htmlspecialchars($r['roll_no']) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($r['name']) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($term) ?>
                                </td>

                                <td>

                                    <?php if ($locked): ?>

                                        <span class="badge text-bg-danger">
                                            Locked
                                        </span>

                                    <?php else: ?>

                                        <span class="badge text-bg-success">
                                            Editable
                                        </span>

                                    <?php endif; ?>

                                </td>

                                <td>

                                    <input
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        max="<?= htmlspecialchars($rowTotal) ?>"
                                        name="obtained[<?= $r['enrollment_id'] ?>]"
                                        value="<?= htmlspecialchars($val) ?>"
                                        class="form-control"
                                        <?= $locked ? 'disabled' : 'required' ?>
                                    >

                                </td>

                            </tr>

                        <?php endforeach; ?>


                        <?php if (!$students): ?>

                            <tr>

                                <td
                                    colspan="5"
                                    class="text-muted"
                                >
                                    No students are enrolled in this
                                    subject for this class/term.
                                </td>

                            </tr>

                        <?php endif; ?>

                        </tbody>

                    </table>

                </div>


                <!-- Dynamic Save Button -->

                <?php if ($students): ?>

                    <button
                        type="submit"
                        class="btn btn-primary"
                        id="saveMarksBtn"
                    >
                        Save <?= htmlspecialchars($assessment) ?> Marks
                    </button>

                <?php endif; ?>

            </form>

        </div>

    </div>

</div>


<!-- Dynamic Assessment Button -->

<script>

document.addEventListener('DOMContentLoaded', function () {

    const assessmentSelect =
        document.getElementById('assessmentSelect');

    const saveMarksBtn =
        document.getElementById('saveMarksBtn');

    if (!assessmentSelect || !saveMarksBtn) {
        return;
    }

    function updateButton() {

        const selected =
            assessmentSelect.value;

        saveMarksBtn.textContent =
            'Save ' + selected + ' Marks';
    }

    assessmentSelect.addEventListener(
        'change',
        updateButton
    );

    updateButton();

});

</script>


<?php require '../includes/footer.php'; ?>
