<?php
defined('BASEPATH') OR exit('No direct script access allowed');
ini_set('memory_limit', '512M');
/**
 * @property CI_Input        $input
 * @property Penahanan_model $Penahanan_model
 * @property Excel_handler   $excel_handler
 */
class Penahanan extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Penahanan_model');
        $this->load->library('Excel_handler');
    }

    private function buildFilter(): array
    {
        return [
            'upt'        => $this->input->get('upt', TRUE),
            'karantina'  => strtoupper($this->input->get('karantina', TRUE) ?? 'H'),
            'start_date' => $this->input->get('start_date', TRUE),
            'end_date'   => $this->input->get('end_date', TRUE),
        ];
    }

    public function index()
    {
        $filter = $this->buildFilter();

        if (!in_array($filter['karantina'], ['H', 'I', 'T'], true)) {
            return $this->json(['success' => false, 'message' => 'Parameter karantina tidak valid'], 400);
        }

        $data = $this->Penahanan_model->getAll($filter);

        return $this->json(['success' => true, 'data' => $data]);
    }

    public function export_excel()
    {
        $filter = $this->buildFilter();

        if (empty($filter['start_date'])) {
            return $this->json(['success' => false, 'message' => 'Export wajib menggunakan filter tanggal'], 400);
        }

        $rows = $this->Penahanan_model->getFullData($filter);

        if (empty($rows)) {
            return $this->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }

        $headers = [
            'No.', 'No P5', 'Tgl P5', 'Satpel', 'Pengirim', 'Penerima',
            'Asal', 'Tujuan', 'Komoditas', 'Volume', 'Satuan',
            'Alasan Penahanan', 'Petugas',
        ];

        $exportData = [];
        $no     = 1;
        $lastId = null;

        foreach ($rows as $r) {
            $isIdem = ($r['id'] === $lastId);
            $exportData[] = [
                $isIdem ? '' : $no++,
                $r['no_p5']        ?? '',
                $r['tgl_p5']       ?? '',
                $r['upt']          ?? '',
                $r['nama_pengirim'] ?? '',
                $r['nama_penerima'] ?? '',
                $r['asal']         ?? '',
                $r['tujuan']       ?? '',
                $r['komoditas']    ?? '-',
                (float) ($r['volume'] ?? 0),
                $r['satuan']       ?? '-',
                $r['alasan_string'] ?? '',
                $r['petugas']      ?? '',
            ];
            $lastId = $r['id'];
        }

        $title      = "LAPORAN TINDAKAN PENAHANAN (" . ($filter['karantina'] ?: 'ALL') . ")";
        $reportInfo = $this->buildReportHeader($title, $filter, $rows);

        $this->logActivity("EXPORT EXCEL: Penahanan {$filter['karantina']}");

        if (ob_get_length()) ob_end_clean();

        return $this->excel_handler->download(
            "Laporan_Penahanan_" . date('Ymd'),
            $headers,
            $exportData,
            $reportInfo
        );
    }
}
