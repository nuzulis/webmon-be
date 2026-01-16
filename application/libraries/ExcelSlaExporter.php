<?php
defined('BASEPATH') OR exit('No direct script access allowed');

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

class ExcelSlaExporter
{
    protected Spreadsheet $spreadsheet;
    protected $sheet;
    protected int $row = 1;

    public function __construct()
    {
        $this->spreadsheet = new Spreadsheet();
        $this->sheet = $this->spreadsheet->getActiveSheet();
        $this->sheet->setTitle('Monitoring SLA');
    }

    /**
     * Header laporan
     */
    public function setHeader(array $meta)
    {
        $this->sheet->mergeCells('A1:O1');
        $this->sheet->setCellValue('A1', 'LAPORAN MONITORING SLA');

        $this->sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $this->row = 3;

        foreach ($meta as $label => $value) {
            $this->sheet->setCellValue("A{$this->row}", $label);
            $this->sheet->setCellValue("C{$this->row}", $value);
            $this->row++;
        }

        $this->row += 1;
    }

    /**
     * Header tabel
     */
    public function setTableHeader()
    {
        $headers = [
            'No Aju', 'Dokumen', 'Satpel', 'Pengirim', 'Penerima',
            'Tgl Permohonan', 'Tgl Periksa', 'Tgl Lepas',
            'Status', 'SLA',
            'Komoditas', 'Nama Tercetak',
            'P1', 'P2', 'Satuan'
        ];

        $col = 'A';
        foreach ($headers as $h) {
            $this->sheet->setCellValue($col . $this->row, $h);
            $col++;
        }

        $this->sheet->getStyle("A{$this->row}:{$col}{$this->row}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'E9EEF3']
            ],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN]
            ]
        ]);

        $this->row++;
    }

    /**
     * Tambah data row
     */
    public function addRow(array $data)
    {
        $sla = hitung_sla($data['tgl_periksa'], $data['tgl_lepas']);

        $values = [
            $data['no_aju'],
            $data['no_dok'],
            $data['satpel'],
            $data['pengirim'],
            $data['penerima'],
            $data['tgl_permohonan'],
            $data['tgl_periksa'],
            $data['tgl_lepas'],
            $data['status'],
            $sla['label'] ?? '-',
            $data['komoditas'],
            $data['nama_umum'],
            $data['p1'],
            $data['p2'],
            $data['satuan']
        ];

        $col = 'A';
        foreach ($values as $val) {
            $this->sheet->setCellValue($col . $this->row, $val);
            $col++;
        }

        $this->row++;
    }

    /**
     * Simpan file
     */
    public function save(string $path)
    {
        foreach (range('A', 'O') as $col) {
            $this->sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new Xlsx($this->spreadsheet);
        $writer->save($path);
    }
}
