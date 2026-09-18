<?php

declare(strict_types=1);

// Generates samples/fake_year_2_grades.xlsx with random grades for the
// current بكلوريوس class-2 students, in the flat format the grades
// import route expects (headers: اسم الطالب | امتحان).
//
// Run: php backend/test/generate_fake_grades.php

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/helpers/helpers.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$students = [
    'ريم سالم حمادي نعيم',
    'سعيد حميد سلطان عواد',
    'سلمى حسني شاكر عاطف',
    'سلمى مراد حاتم فواز',
    'طارق فؤاد حسين علي',
    'عبدالعزيز حمدان نايف عايد',
    'عبدالله عمر خليفة سلطان',
    'فهد ناصر تركي متعب',
    'لينا زياد باسم طارق',
    'نور الدين بشير توفيق كمال',
    'هند نادر مجدي حازم',
    'ياسر نبيل عادل سمير',
];

// Subject 1 (برمجة, بكلوريوس class 2, نظام 1): امتحان max 60 with a
// 20-point lab cap on the same field -> the importer enforces grade <= 40.
$rows = [['اسم الطالب', 'امتحان']];
foreach ($students as $name) {
    // mostly passing grades, sprinkle a few low/failing ones
    $grade = random_int(0, 5) === 0 ? random_int(0, 12) : random_int(20, 40);
    $rows[] = [$name, $grade];
}

$ss = new Spreadsheet();
$ws = $ss->getActiveSheet();
$ws->setTitle('Grades');
$ws->fromArray($rows, null, 'A1');
$ws->getColumnDimension('A')->setWidth(30);

$out = __DIR__ . '/../../samples/fake_year_2_grades.xlsx';
(new Xlsx($ss))->save($out);
echo "saved: $out\n";
foreach (array_slice($rows, 1) as $r) {
    echo $r[0], ' -> ', $r[1], "\n";
}
