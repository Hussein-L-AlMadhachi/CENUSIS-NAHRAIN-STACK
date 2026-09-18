<?php

declare(strict_types=1);

/**
 * Port of backend/src/helpers/xlsx_codec.ts, using PhpSpreadsheet
 * (phpoffice/phpspreadsheet) instead of ExcelJS.
 */

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Port of decodeXlsx: loads the first worksheet of an xlsx file and returns
 * one associative array per data row, keeping only the columns whose header
 * (row 1) matches $headers_to_extract.
 *
 * Mirrors the ExcelJS behavior: every data row is emitted (even when all of
 * its extracted cells are empty) as long as at least one header matched.
 *
 * @param string $file_path path to the xlsx file (e.g. an uploaded temp file)
 * @param array<int, string> $headers_to_extract
 * @return array<int, array<string, mixed>>
 */
function decodeXlsx(string $file_path, array $headers_to_extract): array
{
    // ExcelJS threw on invalid input; PhpSpreadsheet's IOFactory instead
    // silently falls back to other readers (it even parses plain text as
    // CSV-ish rows), which made broken uploads return success:true.
    // Guard the xlsx container format (a valid zip) first, mirroring the
    // original behavior of failing such uploads.
    $zip = new ZipArchive();
    if ($zip->open($file_path) !== true) {
        throw new Exception('Error extracting Excel file');
    }
    $zip->close();

    $spreadsheet = IOFactory::load($file_path);
    $worksheet = $spreadsheet->getWorksheetIterator()->current();
    $data = [];

    if (!$worksheet instanceof Worksheet) {
        $spreadsheet->disconnectWorksheets();
        return $data;
    }

    $highestColumnIndex = Coordinate::columnIndexFromString($worksheet->getHighestColumn());
    $highestRow = $worksheet->getHighestDataRow();

    // headerMap: 1-based column index => header value
    $headerMap = [];
    foreach (range(1, $highestColumnIndex) as $colNumber) {
        $headerVal = (string)$worksheet->getCell(
            Coordinate::stringFromColumnIndex($colNumber) . '1'
        )->getValue();
        if (in_array($headerVal, $headers_to_extract, true)) {
            $headerMap[$colNumber] = $headerVal;
        }
    }

    for ($rowNumber = 2; $rowNumber <= $highestRow; $rowNumber++) { // skip header row
        $rowData = [];
        $hasData = false;

        foreach ($headerMap as $colNumber => $headerName) {
            $rowData[$headerName] = $worksheet->getCell(
                Coordinate::stringFromColumnIndex($colNumber) . $rowNumber
            )->getValue();
            $hasData = true;
        }

        if ($hasData) {
            $data[] = $rowData;
        }
    }

    $spreadsheet->disconnectWorksheets();
    return $data;
}

/**
 * Port of encodeXlsx: builds an xlsx workbook with one row per record and
 * one column per header, returning the raw xlsx bytes.
 *
 * @param array<int, array<string, mixed>> $data
 * @param array<int, string> $headers_to_include
 */
function encodeXlsx(array $data, array $headers_to_include): string
{
    $workbook = new Spreadsheet();
    $worksheet = $workbook->getActiveSheet();
    $worksheet->setTitle('Sheet1');

    // Set up columns based on the headers provided (default width 20).
    $worksheet->fromArray([$headers_to_include], null, 'A1');
    foreach (array_keys($headers_to_include) as $index) {
        $worksheet->getColumnDimensionByColumn($index + 1)->setWidth(20);
    }

    // Add rows; filter each row to only include specified keys.
    $rowNumber = 2;
    foreach ($data as $item) {
        $filteredRow = [];
        foreach ($headers_to_include as $header) {
            $filteredRow[] = $item[$header] ?? null;
        }
        $worksheet->fromArray([$filteredRow], null, 'A' . $rowNumber);
        $rowNumber++;
    }

    $writer = IOFactory::createWriter($workbook, 'Xlsx');
    $stream = fopen('php://temp', 'r+');
    $writer->save($stream);
    rewind($stream);
    $buffer = (string)stream_get_contents($stream);
    fclose($stream);
    $workbook->disconnectWorksheets();

    return $buffer;
}
