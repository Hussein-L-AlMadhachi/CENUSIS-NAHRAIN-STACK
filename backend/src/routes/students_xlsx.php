<?php

declare(strict_types=1);

/**
 * Port of backend/src/routes/students_xlsx.ts.
 *
 * Non-RPC Express routes (multer memory upload + ExcelJS) become PHP routes
 * registered on Cenusis\App:
 *
 *   POST /api/students/import                (multipart: file)
 *   POST /api/grades/import/:subjectid       (multipart: file)
 *   POST /api/lab/grades/import/:subjectid   (multipart: file)
 *   GET  /api/grades/export
 *   GET  /api/absence-alerts/export
 *
 * Uploads are read from $_FILES (the multipart body, not php://input) and
 * parsed with PhpSpreadsheet (IOFactory::load on the uploaded temp file).
 */

use Cenusis\App;
use Cenusis\Auth\Auth;
use Cenusis\Db\Db;
use function Cenusis\Access\subject_owned_by_teacher;
use function Cenusis\Access\teacher_for_user;
use function Cenusis\Helpers\normalize_arabic;
use function Cenusis\Helpers\translateHeaders;
use function Cenusis\Helpers\loose_validate_params;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;

require_once __DIR__ . '/../helpers/xlsx_codec.php';
require_once __DIR__ . '/../Access/Ownership.php';

/** The XLSX content type used by every download route. */
const XLSX_CONTENT_TYPE = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

// ---------------------------------------------------------------------------
// small PhpSpreadsheet helpers (ExcelJS coordinates are 1-based numbers)
// ---------------------------------------------------------------------------

/** Column index (1-based) -> letter, e.g. 1 -> 'A'. */
function xlsx_col(int $col): string
{
    return Coordinate::stringFromColumnIndex($col);
}

/** Cell reference, e.g. (3, 2) -> 'C2'. */
function xlsx_cell(int $col, int $row): string
{
    return xlsx_col($col) . $row;
}

/** Range reference, e.g. (2, 1, 4, 1) -> 'B1:D1'. */
function xlsx_range(int $col1, int $row1, int $col2, int $row2): string
{
    return xlsx_cell($col1, $row1) . ':' . xlsx_cell($col2, $row2);
}

/** ExcelJS-style thin border style array. */
function xlsx_thin_border(): array
{
    return ['allBorders' => ['borderStyle' => Border::BORDER_THIN]];
}

/**
 * Encode a PhpSpreadsheet workbook to xlsx bytes.
 */
function xlsx_to_string(Spreadsheet $workbook): string
{
    $writer = new XlsxWriter($workbook);
    $stream = fopen('php://temp', 'r+');
    $writer->save($stream);
    rewind($stream);
    $buffer = (string)stream_get_contents($stream);
    fclose($stream);
    $workbook->disconnectWorksheets();
    return $buffer;
}

/**
 * JS-style Number() cast used by the ported handlers: null/''/'x'/'abc'
 * behave like JS NaN-free cases (Number(null) === 0, Number('abc') === NaN).
 *
 * @param mixed $value
 */
function xlsx_number($value): float
{
    if (is_bool($value)) {
        return $value ? 1.0 : 0.0;
    }
    if (is_numeric($value)) {
        return (float)$value;
    }
    if ($value === null || $value === '' || is_string($value)) {
        // Number(null) === 0, Number('') === 0, Number('abc') === NaN
        return $value === null || $value === '' ? 0.0 : NAN;
    }
    return NAN;
}

// ---------------------------------------------------------------------------
// routes
// ---------------------------------------------------------------------------

/**
 * Build and send a template workbook for an upload route: a header row plus
 * optional pre-filled rows (e.g. enrolled student names). The user downloads
 * it, fills in the values and uploads it back.
 */
function xlsx_send_template(Cenusis\Rpc\Response $res, array $headers, array $rows, string $filename): void
{
    $workbook = new Spreadsheet();
    $worksheet = $workbook->getActiveSheet();
    $worksheet->setTitle('Template');

    $worksheet->fromArray([$headers], null, 'A1');
    $rowNumber = 2;
    foreach ($rows as $row) {
        $worksheet->fromArray([$row], null, 'A' . $rowNumber);
        $rowNumber++;
    }

    $worksheet->getColumnDimension('A')->setWidth(25);
    foreach (range(2, max(1, count($headers))) as $col) {
        $worksheet->getColumnDimension(xlsx_col($col))->setWidth(15);
    }

    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $res->send(xlsx_to_string($workbook), XLSX_CONTENT_TYPE);
}

/**
 * Grading field names for a subject (from its grading system), as the
 * grades import expects as columns.
 *
 * @return array{names: array<int, string>, max_grades: array<string, float>}
 */
function grades_field_names_for_subject(int $subject_id): array
{
    $subjects = subjects_fetch($subject_id);
    $existingSubject = $subjects[0] ?? null;
    if ($existingSubject === null) {
        throw new Exception('Invalid subject id');
    }

    $names = [];
    $maxGrades = [];
    $grading_system_id = $existingSubject['grading_system_id'];
    if (!empty($grading_system_id)) {
        $grading_systems = grading_systems_fetch((int)$grading_system_id);
        $gs = $grading_systems[0] ?? null;
        if ($gs !== null && is_array($gs['fields'])) {
            foreach ($gs['fields'] as $field) {
                $names[] = (string)$field['field_name'];
                $maxGrades[(string)$field['field_name']] = xlsx_number($field['max_grade']);
            }
        }
    }

    return ['names' => $names, 'max_grades' => $maxGrades];
}

function registerStudentsXlsxRoutes(App $app): void
{
    // POST /api/students/import -------------------------------------------
    $app->post('/api/students/import', static function (array $req, Cenusis\Rpc\Response $res): void {
        try {
            $admin_auth = Auth::isValidAdminNoRPC($req);
            $superadmin_auth = Auth::isValidSuperadminNoRPC($req);

            if ($admin_auth === null && $superadmin_auth === null) {
                $res->status(401)->json([
                    'success' => false,
                    'error' => 'unauthorized',
                ]);
                return;
            }

            $uploaded = $_FILES['file'] ?? null;
            if (!is_array($uploaded)
                || ($uploaded['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
                || !is_uploaded_file($uploaded['tmp_name'])
            ) {
                $res->status(400)->json([
                    'success' => false,
                    'error' => 'No file uploaded',
                ]);
                return;
            }

            $students_js_obj = decodeXlsx($uploaded['tmp_name'], [
                'الاسم', 'الطالب', 'سنة التسجيل', 'الدرجة العلمية', 'المرحلة',
            ]);

            $loaded_students_record = translateHeaders($students_js_obj, [
                'الاسم' => 'student_name',
                'الطالب' => 'student_name',
                'الدرجة العلمية' => 'degree',
                'المرحلة' => 'class',
            ]);

            Db::transaction(static function () use ($loaded_students_record): void {
                // Clear existing data before importing
                Db::execute('DELETE FROM absented');
                Db::execute('DELETE FROM attendance_record');
                Db::execute('DELETE FROM studying');
                Db::execute('DELETE FROM students');

                foreach ($loaded_students_record as $student) {
                    $importName = (string)($student['student_name'] ?? '');
                    if ($importName === '') { // TS: `if (!student?.student_name)`
                        continue;
                    }

                    $name = (string)$student['student_name'];
                    $degree = (string)($student['degree'] ?? '');
                    $class_number = (int)($student['class'] ?? 0) ?: 1;

                    Db::execute(
                        'INSERT INTO students (student_name, student_normalized_name, degree, class)
                        VALUES (?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE
                            student_name = VALUES(student_name),
                            degree = VALUES(degree),
                            class = VALUES(class)',
                        [$name, normalize_arabic($name), $degree, $class_number]
                    );
                }

                // Establish studying relationships between students and subjects
                Db::execute(
                    "INSERT INTO studying (teacher, student, subject, studying_year, hours_missed, grade_fields, is_submitted)
                    SELECT
                        sub.teacher,
                        stu.id AS student,
                        sub.id AS subject,
                        YEAR(CURRENT_DATE) AS studying_year,
                        0 AS hours_missed,
                        '[]' AS grade_fields,
                        FALSE AS is_submitted
                    FROM subjects sub
                    JOIN students stu ON sub.class = stu.class AND sub.degree = stu.degree
                    AND NOT EXISTS (
                        SELECT 1
                        FROM studying existing
                        WHERE existing.student = stu.id
                        AND existing.subject = sub.id
                        AND existing.studying_year = YEAR(CURRENT_DATE)
                    )"
                );
            });

            $res->json(['success' => true]);
        } catch (Throwable $error) {
            error_log((string)$error);
            $res->status(500)->send('Error generating Excel file');
        }
    });

    // GET /api/students/template ------------------------------------------
    $app->get('/api/students/template', static function (array $req, Cenusis\Rpc\Response $res): void {
        try {
            $admin_auth = Auth::isValidAdminNoRPC($req);
            $superadmin_auth = Auth::isValidSuperadminNoRPC($req);

            if ($admin_auth === null && $superadmin_auth === null) {
                $res->status(401)->json([
                    'success' => false,
                    'error' => 'unauthorized',
                ]);
                return;
            }

            xlsx_send_template(
                $res,
                ['الاسم', 'الدرجة العلمية', 'المرحلة'],
                [],
                'students_template.xlsx'
            );
        } catch (Throwable $error) {
            error_log((string)$error);
            $res->status(500)->send('Error generating Excel file');
        }
    });

    // POST /api/grades/import/:subjectid ----------------------------------
    $app->post('/api/grades/import/:subjectid', static function (array $req, Cenusis\Rpc\Response $res): void {
        $subject_id = $req['params']['subjectid'] ?? null;

        if (!$subject_id) {
            $res->status(400)->json([
                'success' => false,
                'error' => 'No subject id provided',
            ]);
            return;
        }

        $subject_id = (int)$subject_id;
        try {
            $existingSubjects = subjects_fetch($subject_id);
            $existingSubject = $existingSubjects[0] ?? null;
        } catch (Throwable $e) {
            error_log('Error processing subject ' . $subject_id . ': ' . (string)$e);
            $res->status(400)->json(['success' => false, 'error' => 'Invalid subject id']);
            return;
        }

        if ($existingSubject === null) {
            $res->status(400)->json(['success' => false, 'error' => 'Invalid subject id']);
            return;
        }

        try {
            $admin_auth = Auth::isValidAdminNoRPC($req);
            $superadmin_auth = Auth::isValidSuperadminNoRPC($req);
            $teacher_auth = Auth::isValidTeacherNoRPC($req);

            if ($admin_auth === null && $superadmin_auth === null && $teacher_auth === null) {
                $res->status(401)->json([
                    'success' => false,
                    'error' => 'unauthorized',
                ]);
                return;
            }

            // IDOR guard: a teacher may only touch subjects they are
            // whitelisted on (main teacher or lab teacher).
            if ($admin_auth === null && $superadmin_auth === null) {
                $teacher = teacher_for_user((int)$teacher_auth['user_id']);
                if ($teacher === null
                    || !subject_owned_by_teacher($existingSubject, (int)$teacher['id'])
                ) {
                    $res->status(401)->json([
                        'success' => false,
                        'error' => 'unauthorized',
                    ]);
                    return;
                }
            }

            $uploaded = $_FILES['file'] ?? null;
            if (!is_array($uploaded)
                || ($uploaded['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
                || !is_uploaded_file($uploaded['tmp_name'])
            ) {
                $res->status(400)->json([
                    'success' => false,
                    'error' => 'No file uploaded',
                ]);
                return;
            }

            // Fetch the subject to get the grading system
            $grading_system_id = $existingSubject['grading_system_id'];

            $fieldNames = [];
            $fieldMaxGrades = [];
            if (!empty($grading_system_id)) {
                $grading_systems = grading_systems_fetch((int)$grading_system_id);
                $gs = $grading_systems[0] ?? null;
                if ($gs !== null && is_array($gs['fields'])) {
                    $fields = $gs['fields'];
                    foreach ($fields as $field) {
                        $fieldNames[] = (string)$field['field_name'];
                        $fieldMaxGrades[(string)$field['field_name']] = xlsx_number($field['max_grade']);
                    }
                }
            }

            $students_js_obj = decodeXlsx(
                $uploaded['tmp_name'],
                array_merge(['اسم الطالب'], $fieldNames)
            );

            // Every grading field must actually exist in the file. A missing
            // column would otherwise be silently treated as grade 0 for every
            // student (wiping existing grades) and reported as success.
            $presentKeys = [];
            foreach ($students_js_obj as $row) {
                foreach ($row as $key => $ignored) {
                    $presentKeys[$key] = true;
                }
            }

            if ($students_js_obj !== []) {
                $missingHeaders = [];
                foreach ($fieldNames as $fieldName) {
                    if (!isset($presentKeys[$fieldName])) {
                        $missingHeaders[] = $fieldName;
                    }
                }
                if ($missingHeaders !== [] || !isset($presentKeys['اسم الطالب'])) {
                    $res->status(400)->json([
                        'success' => true,
                        'err' => 'النظام لم يتمكن من قراءة الحقول بالملف. تأكد من وجود الحقول في هذه الحقول في السطر الأول من الملف: ('
                            . implode(',', $fieldNames) . ',اسم الطالب)',
                    ]);
                    return;
                }
            }

            $headerTranslations = [
                'اسم الطالب' => 'student_name',
            ];
            foreach ($fieldNames as $fieldName) {
                $headerTranslations[$fieldName] = $fieldName;
            }

            $labGradeField = is_string($existingSubject['lab_grade_field'] ?? null)
                ? trim((string)$existingSubject['lab_grade_field'])
                : '';
            $maxLabGrade = xlsx_number($existingSubject['max_lab_grade'] ?? 0);

            $loaded_students_record_array = translateHeaders($students_js_obj, $headerTranslations);

            foreach ($loaded_students_record_array as $row) {
                try {
                    $studentName = trim((string)($row['student_name'] ?? ''));
                    if ($studentName === '') {
                        continue;
                    }

                    $existingStudent = students_find_by_name(normalize_arabic($studentName) ?: '');

                    if ($existingStudent === null) {
                        $err_msg = 'النظام لم يتمكن من قراءة الحقول بالملف. تأكد من وجود الحقول في هذه الحقول في السطر الأول من الملف: ('
                            . implode(',', $fieldNames) . ',اسم الطالب)';
                        throw new Exception($err_msg);
                    }

                    $grade_fields = [];
                    foreach ($fieldNames as $name) {
                        $grade = xlsx_number($row[$name] ?? 0);
                        $maxGrade = $fieldMaxGrades[$name] ?? null;
                        if ($labGradeField !== ''
                            && $name === $labGradeField
                            && $maxGrade !== null
                            && !is_nan($maxLabGrade)
                        ) {
                            $maxGrade = $maxGrade - $maxLabGrade;
                        }
                        if ($maxGrade !== null && !is_nan($maxGrade) && $grade > $maxGrade) {
                            throw new Exception(
                                'درجة الحقل (' . $name . ') للطالب (' . $studentName
                                . ') تتجاوز الدرجة الكاملة و هي من (' . $maxGrade . ')'
                            );
                        }
                        $grade_fields[] = ['name' => $name, 'grade' => $grade];
                    }

                    studying_set_grade_fields(
                        $grade_fields,
                        (int)$existingSubject['id'],
                        (int)$existingStudent['id']
                    );
                } catch (Throwable $e) {
                    error_log('Error processing student ' . ($row['student_name'] ?? '') . ': ' . (string)$e);
                    $res->status(400)->json(['success' => true, 'err' => (string)$e->getMessage()]);
                    return;
                }
            }

            $res->json(['success' => true]);
        } catch (Throwable $error) {
            error_log((string)$error);
            $res->status(500)->send('Error extracting Excel file');
        }
    });

    // POST /api/lab/grades/import/:subjectid ------------------------------
    $app->post('/api/lab/grades/import/:subjectid', static function (array $req, Cenusis\Rpc\Response $res): void {
        $subject_id = $req['params']['subjectid'] ?? null;

        if (!$subject_id) {
            $res->status(400)->json([
                'success' => false,
                'error' => 'No subject id provided',
            ]);
            return;
        }

        $subject_id = (int)$subject_id;
        try {
            $existingSubjects = subjects_fetch($subject_id);
            $existingSubject = $existingSubjects[0] ?? null;
        } catch (Throwable $e) {
            error_log('Error processing subject ' . $subject_id . ': ' . (string)$e);
            $res->status(400)->json(['success' => false, 'error' => 'Invalid subject id']);
            return;
        }

        if ($existingSubject === null) {
            $res->status(400)->json(['success' => false, 'error' => 'Invalid subject id']);
            return;
        }

        try {
            $admin_auth = Auth::isValidAdminNoRPC($req);
            $superadmin_auth = Auth::isValidSuperadminNoRPC($req);
            $teacher_auth = Auth::isValidTeacherNoRPC($req);

            if ($admin_auth === null && $superadmin_auth === null && $teacher_auth === null) {
                $res->status(401)->json([
                    'success' => false,
                    'error' => 'unauthorized',
                ]);
                return;
            }

            // IDOR guard: a teacher may only touch subjects they are
            // whitelisted on (main teacher or lab teacher).
            if ($admin_auth === null && $superadmin_auth === null) {
                $teacher = teacher_for_user((int)$teacher_auth['user_id']);
                if ($teacher === null
                    || !subject_owned_by_teacher($existingSubject, (int)$teacher['id'])
                ) {
                    $res->status(401)->json([
                        'success' => false,
                        'error' => 'unauthorized',
                    ]);
                    return;
                }
            }

            $uploaded = $_FILES['file'] ?? null;
            if (!is_array($uploaded)
                || ($uploaded['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
                || !is_uploaded_file($uploaded['tmp_name'])
            ) {
                $res->status(400)->json([
                    'success' => false,
                    'error' => 'No file uploaded',
                ]);
                return;
            }

            $students_js_obj = decodeXlsx($uploaded['tmp_name'], [
                'اسم الطالب',
                'درجة المختبر',
            ]);

            // The lab-grade column must actually exist in the file. A missing
            // column would otherwise be silently treated as grade 0 for every
            // student (wiping existing lab grades) and reported as success.
            $presentKeys = [];
            foreach ($students_js_obj as $row) {
                foreach ($row as $key => $ignored) {
                    $presentKeys[$key] = true;
                }
            }

            if (!isset($presentKeys['درجة المختبر']) && $students_js_obj !== []) {
                $res->status(400)->json([
                    'success' => true,
                    'err' => 'النظام لم يتمكن من قراءة الحقول بالملف. تأكد من وجود هذه الحقول في السطر الأول من الملف: (اسم الطالب,درجة المختبر)',
                ]);
                return;
            }

            $headerTranslations = [
                'اسم الطالب' => 'student_name',
                'درجة المختبر' => 'lab_grade',
            ];

            $maxLabGrade = xlsx_number($existingSubject['max_lab_grade'] ?? 0);

            $loaded_students_record_array = translateHeaders($students_js_obj, $headerTranslations);

            foreach ($loaded_students_record_array as $row) {
                try {
                    $studentName = trim((string)($row['student_name'] ?? ''));
                    if ($studentName === '') {
                        continue;
                    }

                    $existingStudent = students_find_by_name(normalize_arabic($studentName) ?: '');

                    if ($existingStudent === null) {
                        $err_msg = 'النظام لم يتمكن من قراءة الحقول بالملف. تأكد من وجود هذه الحقول في السطر الأول من الملف: (اسم الطالب,درجة المختبر)';
                        throw new Exception($err_msg);
                    }

                    $lab_grade = xlsx_number($row['lab_grade'] ?? 0);
                    if (is_nan($lab_grade)) {
                        $lab_grade = 0; // TS: `Number(...) || 0`
                    }
                    if (!is_nan($maxLabGrade) && $lab_grade > $maxLabGrade) {
                        throw new Exception(
                            'درجة المختبر للطالب (' . $studentName . ') تتجاوز الدرجة الكاملة لدرجة المختبر و هي من ' . $maxLabGrade
                        );
                    }

                    Db::execute(
                        'UPDATE studying
                        SET lab_grade = ?
                        WHERE subject = ? AND student = ?',
                        [$lab_grade, (int)$existingSubject['id'], (int)$existingStudent['id']]
                    );
                } catch (Throwable $e) {
                    error_log('Error processing student ' . ($row['student_name'] ?? '') . ': ' . (string)$e);
                    $res->status(400)->json(['success' => true, 'err' => (string)$e->getMessage()]);
                    return;
                }
            }

            $res->json(['success' => true]);
        } catch (Throwable $error) {
            error_log((string)$error);
            $res->status(500)->send('Error extracting Excel file');
        }
    });

    // GET /api/grades/template/:subjectid ---------------------------------
    $app->get('/api/grades/template/:subjectid', static function (array $req, Cenusis\Rpc\Response $res): void {
        $subject_id = $req['params']['subjectid'] ?? null;

        if (!$subject_id) {
            $res->status(400)->json([
                'success' => false,
                'error' => 'No subject id provided',
            ]);
            return;
        }

        try {
            $admin_auth = Auth::isValidAdminNoRPC($req);
            $superadmin_auth = Auth::isValidSuperadminNoRPC($req);
            $teacher_auth = Auth::isValidTeacherNoRPC($req);

            if ($admin_auth === null && $superadmin_auth === null && $teacher_auth === null) {
                $res->status(401)->json([
                    'success' => false,
                    'error' => 'unauthorized',
                ]);
                return;
            }

            $subject_id = (int)$subject_id;

            $subjects = subjects_fetch($subject_id);
            $existingSubject = $subjects[0] ?? null;
            if ($existingSubject === null) {
                throw new Exception('Invalid subject id');
            }

            // IDOR guard: a teacher may only touch subjects they are
            // whitelisted on (main teacher or lab teacher).
            if ($admin_auth === null && $superadmin_auth === null) {
                $teacher = teacher_for_user((int)$teacher_auth['user_id']);
                if ($teacher === null
                    || !subject_owned_by_teacher($existingSubject, (int)$teacher['id'])
                ) {
                    $res->status(401)->json([
                        'success' => false,
                        'error' => 'unauthorized',
                    ]);
                    return;
                }
            }

            $fields = grades_field_names_for_subject($subject_id);

            // pre-fill the template with the enrolled students' names
            $rows = [];
            foreach (studying_find_by_subject($subject_id) as $enrollment) {
                $row = ['اسم الطالب' => $enrollment['student_name']];
                foreach ($fields['names'] as $fieldName) {
                    $row[$fieldName] = null;
                }
                $rows[] = $row;
            }

            xlsx_send_template(
                $res,
                array_merge(['اسم الطالب'], $fields['names']),
                $rows,
                'grades_template.xlsx'
            );
        } catch (Throwable $error) {
            error_log((string)$error);
            $res->status(500)->send('Error generating Excel file');
        }
    });

    // GET /api/lab/grades/template/:subjectid -----------------------------
    $app->get('/api/lab/grades/template/:subjectid', static function (array $req, Cenusis\Rpc\Response $res): void {
        $subject_id = $req['params']['subjectid'] ?? null;

        if (!$subject_id) {
            $res->status(400)->json([
                'success' => false,
                'error' => 'No subject id provided',
            ]);
            return;
        }

        try {
            $admin_auth = Auth::isValidAdminNoRPC($req);
            $superadmin_auth = Auth::isValidSuperadminNoRPC($req);
            $teacher_auth = Auth::isValidTeacherNoRPC($req);

            if ($admin_auth === null && $superadmin_auth === null && $teacher_auth === null) {
                $res->status(401)->json([
                    'success' => false,
                    'error' => 'unauthorized',
                ]);
                return;
            }

            $subject_id = (int)$subject_id;

            $subjects = subjects_fetch($subject_id);
            $existingSubject = $subjects[0] ?? null;
            if ($existingSubject === null) {
                throw new Exception('Invalid subject id');
            }

            // IDOR guard: a teacher may only touch subjects they are
            // whitelisted on (main teacher or lab teacher).
            if ($admin_auth === null && $superadmin_auth === null) {
                $teacher = teacher_for_user((int)$teacher_auth['user_id']);
                if ($teacher === null
                    || !subject_owned_by_teacher($existingSubject, (int)$teacher['id'])
                ) {
                    $res->status(401)->json([
                        'success' => false,
                        'error' => 'unauthorized',
                    ]);
                    return;
                }
            }

            // pre-fill the template with the enrolled students' names
            $rows = [];
            foreach (studying_find_by_subject($subject_id) as $enrollment) {
                $rows[] = ['اسم الطالب' => $enrollment['student_name'], 'درجة المختبر' => null];
            }

            xlsx_send_template(
                $res,
                ['اسم الطالب', 'درجة المختبر'],
                $rows,
                'lab_grades_template.xlsx'
            );
        } catch (Throwable $error) {
            error_log((string)$error);
            $res->status(500)->send('Error generating Excel file');
        }
    });

    // GET /api/grades/export ----------------------------------------------
    $app->get('/api/grades/export', static function (array $req, Cenusis\Rpc\Response $res): void {
        try {
            $superadmin_auth = Auth::isValidSuperadminNoRPC($req);

            if ($superadmin_auth === null) {
                $res->status(401)->json([
                    'success' => false,
                    'error' => 'unauthorized',
                ]);
                return;
            }

            $degree = isset($req['query']['degree']) ? (string)$req['query']['degree'] : null;
            $classRaw = $req['query']['class'] ?? null;
            $class_number = is_numeric($classRaw) ? (int)$classRaw : null;
            $grading_system = isset($req['query']['grading_system']) ? (string)$req['query']['grading_system'] : null;

            if (!$degree || $class_number === null || !$grading_system) {
                $res->status(400)->json([
                    'success' => false,
                    'error' => 'Missing or invalid degree, class, or grading_system parameter',
                ]);
                return;
            }

            $subjectsList = subjects_filter_by_class_degree_grading_system($degree, $class_number, $grading_system);

            if ($subjectsList === []) {
                $res->status(404)->json([
                    'success' => false,
                    'error' => 'No subjects found for the specified class and degree',
                ]);
                return;
            }

            $gradesData = studying_get_grades_by_class_degree($degree, $class_number, $grading_system);

            /**
             * subjectFieldsMap: subjectId -> {subject_name, fields, field_min_grades, lab_grade_field}
             *
             * @var array<int, array<string, mixed>> $subjectFieldsMap
             */
            $subjectFieldsMap = [];

            foreach ($subjectsList as $subject) {
                $fieldNames = [];
                $fieldMinGrades = [];
                if (!empty($subject['grading_system_id'])) {
                    $grading_systems = grading_systems_fetch((int)$subject['grading_system_id']);
                    $gs = $grading_systems[0] ?? null;
                    if ($gs !== null && is_array($gs['fields'])) {
                        foreach ($gs['fields'] as $field) {
                            $fieldNames[] = (string)$field['field_name'];
                            $fieldMinGrades[(string)$field['field_name']] = xlsx_number($field['min_grade'] ?? 0);
                        }
                    }
                }
                $subjectFieldsMap[(int)$subject['id']] = [
                    'subject_name' => (string)$subject['subject_name'],
                    'fields' => $fieldNames,
                    'field_min_grades' => $fieldMinGrades,
                    'lab_grade_field' => isset($subject['lab_grade_field']) && is_string($subject['lab_grade_field'])
                        ? $subject['lab_grade_field']
                        : null,
                ];
            }

            /**
             * studentGradesMap: studentId -> {student_name, grades: subjectId -> {fieldName: grade}}
             *
             * @var array<int, array<string, mixed>> $studentGradesMap
             */
            $studentGradesMap = [];

            foreach ($gradesData as $row) {
                $studentId = (int)$row['student_id'];
                $subjectId = (int)$row['subject_id'];

                if (!isset($studentGradesMap[$studentId])) {
                    $studentGradesMap[$studentId] = [
                        'student_name' => (string)$row['student_name'],
                        'grades' => [],
                    ];
                }

                $gradeRecord = [];
                $gradeFields = is_string($row['grade_fields'])
                    ? json_decode($row['grade_fields'], true)
                    : $row['grade_fields'];
                if (!is_array($gradeFields)) {
                    $gradeFields = [];
                }
                foreach ($gradeFields as $field) {
                    $gradeRecord[(string)$field['name']] = $field['grade'];
                }

                $subjectInfo = $subjectFieldsMap[$subjectId] ?? null;
                $labGradeField = is_string($subjectInfo['lab_grade_field'] ?? null)
                    ? trim((string)$subjectInfo['lab_grade_field'])
                    : null;
                $hasValidLabGradeField = $labGradeField !== null
                    && $labGradeField !== ''
                    && is_array($subjectInfo)
                    && in_array($labGradeField, $subjectInfo['fields'], true)
                    && array_key_exists($labGradeField, $gradeRecord);

                if ($hasValidLabGradeField && $labGradeField !== null) {
                    $labGrade = xlsx_number($row['lab_grade'] ?? 0);
                    if (!is_nan($labGrade)) {
                        $gradeRecord[$labGradeField] = ($gradeRecord[$labGradeField] ?? 0) + $labGrade;
                    }
                }

                $studentGradesMap[$studentId]['grades'][$subjectId] = $gradeRecord;
            }

            $workbook = new Spreadsheet();
            $worksheet = $workbook->getActiveSheet();
            $worksheet->setTitle('Grades');

            /**
             * @var array<int, array{subjectId: int, fieldName: string}> $columnKeys
             */
            $columnKeys = [];
            $subjectHeaderRow = ['اسم الطالب'];
            $fieldHeaderRow = [''];
            /**
             * @var array<int, array{startCol: int, endCol: int}> $mergeRanges
             */
            $mergeRanges = [];
            /**
             * @var array<int, array{startCol: int, endCol: int}> $subjectColumnRanges
             */
            $subjectColumnRanges = [];

            $currentCol = 2;
            foreach ($subjectFieldsMap as $subjectId => $subjectInfo) {
                $fieldCount = count($subjectInfo['fields']);
                if ($fieldCount === 0) {
                    continue;
                }

                $subjectHeaderRow[] = $subjectInfo['subject_name'];
                for ($i = 1; $i < $fieldCount; $i++) {
                    $subjectHeaderRow[] = '';
                }

                foreach ($subjectInfo['fields'] as $fieldName) {
                    $fieldHeaderRow[] = $fieldName;
                    $columnKeys[] = ['subjectId' => $subjectId, 'fieldName' => $fieldName];
                }

                $startCol = $currentCol;
                $endCol = $currentCol + $fieldCount - 1;
                $subjectColumnRanges[$subjectId] = ['startCol' => $startCol, 'endCol' => $endCol];
                if ($fieldCount > 1) {
                    $mergeRanges[] = ['startCol' => $startCol, 'endCol' => $endCol];
                }
                $currentCol += $fieldCount;
            }

            $worksheet->fromArray([$subjectHeaderRow], null, 'A1');
            $worksheet->fromArray([$fieldHeaderRow], null, 'A2');

            $worksheet->mergeCells(xlsx_range(1, 1, 1, 2));
            foreach ($mergeRanges as $range) {
                $worksheet->mergeCells(xlsx_range($range['startCol'], 1, $range['endCol'], 1));
            }

            $thinBorder = xlsx_thin_border();

            $lastGradeCol = 1 + count($columnKeys);
            $headerStyle = [
                'font' => ['bold' => true],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ];
            // TS sets row1/row2 font + alignment on the whole row.
            $worksheet->getStyle(xlsx_range(1, 1, max(1, $lastGradeCol), 2))->applyFromArray($headerStyle);

            for ($col = 2; $col <= $lastGradeCol; $col++) {
                $gradeHeaderStyle = [
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['argb' => 'FFFFECB3'],
                    ],
                    'font' => ['bold' => true, 'color' => ['argb' => 'FF8B4513']],
                    'borders' => $thinBorder,
                ];
                $worksheet->getStyle(xlsx_cell($col, 1))->applyFromArray($gradeHeaderStyle);
                $worksheet->getStyle(xlsx_cell($col, 2))->applyFromArray($gradeHeaderStyle);
            }

            $studentNameHeaderStyle = [
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FFC8E6C9'],
                ],
                'font' => ['bold' => true, 'color' => ['argb' => 'FF1B5E20']],
                'borders' => $thinBorder,
            ];
            $worksheet->getStyle(xlsx_cell(1, 1))->applyFromArray($studentNameHeaderStyle);
            $worksheet->getStyle(xlsx_cell(1, 2))->applyFromArray($studentNameHeaderStyle);

            /**
             * per-column grade accumulators (avg / min / max summary rows).
             *
             * @var array<int, array<int, float>> $columnGrades
             */
            $columnGrades = array_fill(0, count($columnKeys), []);

            $currentRowNum = 3;
            foreach ($studentGradesMap as $studentData) {
                $rowData = [$studentData['student_name']];

                foreach ($columnKeys as $colIdx => $columnKey) {
                    $subjectGrades = $studentData['grades'][$columnKey['subjectId']] ?? null;
                    $grade = $subjectGrades[$columnKey['fieldName']] ?? null;
                    if (is_numeric($grade)) {
                        $rowData[] = $grade;
                        $columnGrades[$colIdx][] = (float)$grade;
                    } else {
                        $rowData[] = '';
                    }
                }

                $worksheet->fromArray([$rowData], null, 'A' . $currentRowNum);

                $studentNameCellStyle = [
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['argb' => 'FFC8E6C9'],
                    ],
                    'font' => ['color' => ['argb' => 'FF1B5E20']],
                    'borders' => $thinBorder,
                ];
                $worksheet->getStyle(xlsx_cell(1, $currentRowNum))->applyFromArray($studentNameCellStyle);

                $currentRowNum++;
            }

            // pass rate per subject (passing = every field grade >= its min grade)
            $passRateBySubject = [];
            foreach ($subjectFieldsMap as $subjectId => $subjectInfo) {
                if (count($subjectInfo['fields']) === 0) {
                    continue;
                }

                $totalStudents = 0;
                $passedStudents = 0;

                foreach ($studentGradesMap as $studentData) {
                    $subjectGrades = $studentData['grades'][$subjectId] ?? null;
                    if ($subjectGrades === null) {
                        continue;
                    }

                    $totalStudents += 1;

                    $passedAllFields = true;
                    foreach ($subjectInfo['fields'] as $fieldName) {
                        // TS: Number(subjectGrades[fieldName]) -> NaN when the
                        // field is missing, which fails the check below.
                        $grade = array_key_exists($fieldName, $subjectGrades)
                            ? xlsx_number($subjectGrades[$fieldName])
                            : NAN;
                        $minGrade = xlsx_number($subjectInfo['field_min_grades'][$fieldName] ?? 0);

                        if (is_nan($grade)) {
                            $passedAllFields = false;
                            break;
                        }

                        if ($grade < $minGrade) {
                            $passedAllFields = false;
                            break;
                        }
                    }

                    if ($passedAllFields) {
                        $passedStudents += 1;
                    }
                }

                if ($totalStudents === 0) {
                    $passRateBySubject[$subjectId] = '';
                } else {
                    $passRate = round(($passedStudents / $totalStudents) * 10000) / 100;
                    $passRateBySubject[$subjectId] = $passRate . '%';
                }
            }

            $avgRow = ['المعدل'];
            $minRow = ['الحد الأدنى'];
            $maxRow = ['الحد الأعلى'];
            foreach ($columnGrades as $grades) {
                if (count($grades) > 0) {
                    $avg = round((array_sum($grades) / count($grades)) * 100) / 100;
                    $avgRow[] = $avg;
                    $minRow[] = min($grades);
                    $maxRow[] = max($grades);
                } else {
                    $avgRow[] = '';
                    $minRow[] = '';
                    $maxRow[] = '';
                }
            }

            $passRateRow = ['نسبة النجاح'];
            foreach ($columnKeys as $columnKey) {
                $range = $subjectColumnRanges[$columnKey['subjectId']] ?? null;
                if ($range === null) {
                    $passRateRow[] = '';
                    continue;
                }

                $columnIndex = count($passRateRow) + 1;
                if ($columnIndex === $range['startCol']) {
                    $passRateRow[] = $passRateBySubject[$columnKey['subjectId']] ?? '';
                } else {
                    $passRateRow[] = '';
                }
            }

            $avgRowNum = $currentRowNum;
            $worksheet->fromArray([$avgRow], null, 'A' . $avgRowNum);
            $minRowNum = $avgRowNum + 1;
            $worksheet->fromArray([$minRow], null, 'A' . $minRowNum);
            $maxRowNum = $minRowNum + 1;
            $worksheet->fromArray([$maxRow], null, 'A' . $maxRowNum);
            $passRateRowNum = $maxRowNum + 1;
            $worksheet->fromArray([$passRateRow], null, 'A' . $passRateRowNum);

            foreach ($subjectColumnRanges as $range) {
                if ($range['endCol'] > $range['startCol']) {
                    $worksheet->mergeCells(
                        xlsx_range($range['startCol'], $passRateRowNum, $range['endCol'], $passRateRowNum)
                    );
                }
            }

            $summaryStyle = [
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FFE0E0E0'],
                ],
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFF6600']],
                'borders' => $thinBorder,
            ];
            foreach ([$avgRowNum, $minRowNum, $maxRowNum, $passRateRowNum] as $summaryRowNum) {
                if ($lastGradeCol >= 2) {
                    $worksheet->getStyle(xlsx_range(1, $summaryRowNum, $lastGradeCol, $summaryRowNum))
                        ->applyFromArray($summaryStyle);
                }
                $worksheet->getStyle(xlsx_cell(1, $summaryRowNum))->applyFromArray([
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);
            }

            for ($col = 1; $col <= $lastGradeCol; $col++) {
                $worksheet->getColumnDimension(xlsx_col($col))->setWidth(15);
            }
            $worksheet->getColumnDimension('A')->setWidth(25);

            $filename = 'grades_class' . $class_number . '_' . (int)round(microtime(true) * 1000) . '.xlsx';

            header('Content-Disposition: attachment; filename="' . $filename . '"');
            $res->send(xlsx_to_string($workbook), XLSX_CONTENT_TYPE);
        } catch (Throwable $error) {
            error_log((string)$error);
            $res->status(500)->json(['success' => false, 'error' => 'Error generating Excel file']);
        }
    });

    // GET /api/absence-alerts/export --------------------------------------
    $app->get('/api/absence-alerts/export', static function (array $req, Cenusis\Rpc\Response $res): void {
        try {
            $admin_auth = Auth::isValidAdminNoRPC($req);
            $superadmin_auth = Auth::isValidSuperadminNoRPC($req);

            if ($admin_auth === null && $superadmin_auth === null) {
                $res->status(401)->json([
                    'success' => false,
                    'error' => 'unauthorized',
                ]);
                return;
            }

            $degree = isset($req['query']['degree']) ? (string)$req['query']['degree'] : null;
            $classRaw = isset($req['query']['class']) ? (string)$req['query']['class'] : null;
            $gradingSystem = isset($req['query']['grading_system']) ? (string)$req['query']['grading_system'] : null;

            if (!$degree || !$gradingSystem) {
                $res->status(400)->json([
                    'success' => false,
                    'error' => 'Missing required degree or grading_system parameter',
                ]);
                return;
            }

            $classNumber = $classRaw !== null && $classRaw !== '' ? ($classRaw + 0) : null;
            if ($classRaw !== null && $classRaw !== '' && !is_numeric($classRaw)) {
                $res->status(400)->json([
                    'success' => false,
                    'error' => 'Invalid class parameter',
                ]);
                return;
            }

            $alerts = studying_fetch_absence_alerts(
                $degree,
                $classNumber !== null ? (int)$classNumber : null,
                $grading_system
            );

            /**
             * byStudentSubject: "student::subject" -> the row with the highest ratio.
             *
             * @var array<string, array<string, mixed>> $byStudentSubject
             */
            $byStudentSubject = [];

            foreach ($alerts as $row) {
                $studentName = (string)($row['student_name'] ?? '');
                $subjectName = (string)($row['subject_name'] ?? '');
                $key = $studentName . '::' . $subjectName;
                $nextRatio = xlsx_number($row['absence_ratio_percent'] ?? 0);
                $existing = $byStudentSubject[$key] ?? null;

                if ($existing === null || $nextRatio > $existing['absence_ratio_percent']) {
                    $byStudentSubject[$key] = [
                        'student_name' => $studentName,
                        'subject_name' => $subjectName,
                        'hours_missed' => xlsx_number($row['hours_missed'] ?? 0),
                        'total_hours' => xlsx_number($row['total_hours'] ?? 0),
                        'absence_ratio_percent' => $nextRatio,
                        'alert_level' => (string)($row['alert_level'] ?? ''),
                    ];
                }
            }

            $workbook = new Spreadsheet();
            $worksheet = $workbook->getActiveSheet();
            $worksheet->setTitle('Absence Alerts');

            $columns = [
                ['header' => 'الطالب', 'key' => 'student_name', 'width' => 30],
                ['header' => 'المادة', 'key' => 'subject_name', 'width' => 30],
                ['header' => 'عدد ساعات الغياب', 'key' => 'hours_missed', 'width' => 20],
                ['header' => 'عدد الساعات الكلية', 'key' => 'total_hours', 'width' => 20],
                ['header' => 'نسبة الغياب', 'key' => 'absence_ratio_percent', 'width' => 18],
                ['header' => 'مستوى التنبيه', 'key' => 'alert_level', 'width' => 20],
            ];

            $worksheet->fromArray([array_column($columns, 'header')], null, 'A1');

            $rowNumber = 2;
            foreach ($byStudentSubject as $row) {
                $row['absence_ratio_percent'] = number_format($row['absence_ratio_percent'], 2, '.', '') . '%';
                $worksheet->fromArray([
                    [
                        $row['student_name'],
                        $row['subject_name'],
                        $row['hours_missed'],
                        $row['total_hours'],
                        $row['absence_ratio_percent'],
                        $row['alert_level'],
                    ],
                ], null, 'A' . $rowNumber);
                $rowNumber++;
            }

            $thinBorder = xlsx_thin_border();

            $worksheet->getStyle('A1:' . xlsx_col(count($columns)) . '1')->applyFromArray([
                'font' => ['bold' => true],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ]);

            $highestRow = max($rowNumber - 1, 1);
            $worksheet->getStyle('A1:' . xlsx_col(count($columns)) . $highestRow)->applyFromArray([
                'borders' => $thinBorder,
            ]);
            if ($highestRow > 1) {
                $worksheet->getStyle('A2:' . xlsx_col(count($columns)) . $highestRow)->applyFromArray([
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);
            }

            foreach ($columns as $index => $column) {
                $worksheet->getColumnDimension(xlsx_col($index + 1))->setWidth($column['width']);
            }

            $classLabel = $classNumber !== null ? '_class' . $classNumber : '';
            $filename = 'absence_alerts' . $classLabel . '_' . (int)round(microtime(true) * 1000) . '.xlsx';

            header('Content-Disposition: attachment; filename="' . $filename . '"');
            $res->send(xlsx_to_string($workbook), XLSX_CONTENT_TYPE);
        } catch (Throwable $error) {
            error_log((string)$error);
            $res->status(500)->json(['success' => false, 'error' => 'Error generating absence alerts Excel file']);
        }
    });
}
