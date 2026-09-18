<?php

declare(strict_types=1);

// Generates samples/برمجة/fake_year_2_lab_grades.xlsx with random lab grades
// for the current بكلوريوس class-2 students, in the lab-grades format the
// lab import route expects (headers: اسم الطالب | درجة المختبر).
//
// Run: php backend/test/generate_fake_lab_grades.php

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/helpers/helpers.php';

use Cenusis\Db\Db;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$pdo = Db::pdo();

// subject 1 = برمجة (بكلوريوس, class 2)
$maxLabGrade = (int)($pdo->query("SELECT max_lab_grade FROM subjects WHERE id = 1")->fetchColumn() ?: 20);

$students = [];
foreach ($pdo->query(
    "SELECT student_name FROM students WHERE degree = 'بكلوريوس' AND class = 2 ORDER BY student_name"
) as $r) {
    $students[] = $r['student_name'];
}

$rows = [['اسم الطالب', 'درجة المختبر']];
foreach ($students as $name) {
    // mostly valid grades; every 4th student gets an over-limit grade so
    // the over-limit rejection can be tested too
    $rows[] = [$name, random_int(1, 4) === 4 ? $maxLabGrade + random_int(1, 5) : random_int(1, $maxLabGrade)];
}

$ss = new Spreadsheet();
$ws = $ss->getActiveSheet();
$ws->setTitle('Grades');
$ws->fromArray($rows, null, 'A1');
$ws->getColumnDimension('A')->setWidth(30);

$out = __DIR__ . '/../../samples/برمجة/fake_year_2_lab_grades.xlsx';
(new Xlsx($ss))->save($out);
echo "saved: $out (max_lab_grade = $maxLabGrade)\n";
foreach (array_slice($rows, 1) as $r) {
    echo $r[0], ' -> ', $r[1], ($r[1] > $maxLabGrade ? '  (over-limit on purpose)' : ''), "\n";
}
