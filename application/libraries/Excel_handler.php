<?php
defined('BASEPATH') OR exit('No direct script access allowed');

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

class Excel_handler {

    public function download($filename, $headers, $data, $reportInfo = []) {
    if (ob_get_level() > 0) ob_end_clean();

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $currentRow = 1;
    foreach (['judul', 'upt', 'periode', 'pencetak', 'source', 'report_id'] as $key) {
        if (!empty($reportInfo[$key])) {
            $sheet->setCellValue('A' . $currentRow, $reportInfo[$key]);
            if ($key == 'judul') $sheet->getStyle('A' . $currentRow)->getFont()->setBold(true)->setSize(14);
            $currentRow++;
        }
    }
    
    $currentRow += 1;
    $startRowTable = $currentRow;
    $colChar = 'A';
    foreach ($headers as $h) {
        $sheet->setCellValue($colChar . $currentRow, $h);
        
        $sheet->getStyle($colChar . $currentRow)->applyFromArray([
            'font' => ['bold' => true],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER, 
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '90EE90'],
            ],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN]
            ]
        ]);
        $sheet->getColumnDimension($colChar)->setAutoSize(true);
        $colChar++;
    }
    $currentRow++;
    foreach ($data as $rowData) {
        $currentCol = 'A';
        foreach ($rowData as $cellValue) {
            $sheet->setCellValueExplicit(
                $currentCol . $currentRow, 
                (string)$cellValue, 
                \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
            );
            $currentCol++;
        }
        $currentRow++;
    }

    $highestRow = $sheet->getHighestRow();
    $highestCol = $sheet->getHighestColumn();
    $sheet->getStyle("A$startRowTable:$highestCol$highestRow")
          ->getBorders()->getAllBorders()
          ->setBorderStyle(Border::BORDER_THIN);

    $finalFilename = $filename . "_" . date('Ymd_His') . ".xlsx";
    if (ob_get_contents()) ob_clean();
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="'. $finalFilename .'"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}
}