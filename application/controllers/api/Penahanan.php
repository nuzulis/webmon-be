<?php
defined('BASEPATH') OR exit('No direct script access allowed');

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

    public function index()
    {
        $filters = [
            'upt'        => $this->input->get('upt', TRUE),
            'karantina'  => strtoupper($this->input->get('karantina', TRUE) ?? 'H'),
            'permohonan' => strtoupper($this->input->get('permohonan', TRUE)),
            'start_date' => $this->input->get('start_date', TRUE),
            'end_date'   => $this->input->get('end_date', TRUE),
            'search'     => $this->input->get('search', TRUE),
            'sort_by'    => $this->input->get('sort_by', TRUE),
            'sort_order' => $this->input->get('sort_order', TRUE),
        ];

        $page    = max((int) $this->input->get('page'), 1);
        $perPage = (int) $this->input->get('per_page') ?: 10;
        $offset  = ($page - 1) * $perPage;
        $ids   = $this->Penahanan_model->getIds($filters, $perPage, $offset);
        $rows  = $this->Penahanan_model->getByIds($ids);
        $total = $this->Penahanan_model->countAll($filters);

        return $this->json([
            'success' => true,
            'data'    => $rows,
            'meta'    => [
                'page'       => $page,
                'per_page'   => $perPage,
                'total'      => $total,
                'total_page' => (int) ceil($total / $perPage),
            ],
        ]);
    }

    public function export_excel()
    {
        $filters = [
            'upt'        => $this->input->get('upt', TRUE),
            'karantina'  => strtoupper($this->input->get('karantina', TRUE) ?? 'H'),
            'permohonan' => strtoupper($this->input->get('permohonan', TRUE)),
            'start_date' => $this->input->get('start_date', TRUE),
            'end_date'   => $this->input->get('end_date', TRUE),
            'search'     => $this->input->get('search', TRUE),
        ];

        if (empty($filters['start_date'])) {
            return $this->json([
                'success' => false,
                'message' => 'Export wajib menggunakan filter tanggal'
            ], 400);
        }
        $rows = $this->Penahanan_model->getFullData($filters);

        if (empty($rows)) {
            return $this->json([
                'success' => false,
                'message' => 'Data tidak ditemukan'
            ], 404);
        }

        $headers = [
            'No.', 'No P5', 'Tgl P5', 'Satpel', 'Pengirim', 'Penerima',
            'Asal', 'Tujuan', 'Komoditas', 'Volume', 'Satuan',
            'Alasan Penahanan', 'Petugas'
        ];

        $exportData = [];
        $no = 1;
        $lastId = null;

        foreach ($rows as $r) {
            $isIdem = ($r['id'] === $lastId);

            $exportData[] = [
                $isIdem ? '' : $no++,
                $isIdem ? 'Idem' : ($r['no_p5'] ?? ''),
                $isIdem ? 'Idem' : ($r['tgl_p5'] ?? ''),
                $isIdem ? 'Idem' : ($r['upt'] ?? ''),
                $isIdem ? 'Idem' : ($r['nama_pengirim'] ?? ''),
                $isIdem ? 'Idem' : ($r['nama_penerima'] ?? ''),
                $isIdem ? 'Idem' : ($r['asal'] ?? ''),
                $isIdem ? 'Idem' : ($r['tujuan'] ?? ''),
                $r['komoditas'] ?? '-',
                $r['volume'] ?? '-',
                $r['satuan'] ?? '-',
                $isIdem ? 'Idem' : ($r['alasan_string'] ?? ''),
                $isIdem ? 'Idem' : ($r['petugas'] ?? ''),
            ];

            $lastId = $r['id'];
        }

        $title = "LAPORAN TINDAKAN PENAHANAN (" . ($filters['karantina'] ?: 'ALL') . ")";
        $reportInfo = $this->buildReportHeader($title, $filters, $rows);

        $this->logActivity("EXPORT EXCEL: Penahanan {$filters['karantina']}");

        return $this->excel_handler->download(
            "Laporan_Penahanan_" . date('Ymd'),
            $headers,
            $exportData,
            $reportInfo
        );
    }
}