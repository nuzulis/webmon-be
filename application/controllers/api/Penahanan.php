<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
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
        $limit  = (int) ($this->input->get('per_page') ?? 10);
        $page   = (int) ($this->input->get('page') ?? 1);
        $offset = ($page - 1) * $limit;

        $filters = [
            'upt'        => $this->input->get('upt'),
            'karantina'  => strtoupper($this->input->get('karantina') ?? 'H'),
            'permohonan' => strtoupper($this->input->get('permohonan')),
            'start_date' => $this->input->get('start_date'),
            'end_date'   => $this->input->get('end_date'),
            'search'     => $this->input->get('search', true),
        ];
        $rows = $this->Penahanan_model->getList($filters, $limit, $offset);
        
        $total = $this->Penahanan_model->countAll($filters);
        $totalPage = (int) ceil($total / max(1, $limit));

        return $this->json([
            'success' => true,
            'data'    => $rows,
            'meta'    => [
                'page'       => $page,
                'per_page'   => $limit,
                'total'      => $total,
                'total_page' => $totalPage,
            ],
        ]);
    }

    public function export_excel()
    {
        $filters = [
            'upt'        => $this->input->get('upt'),
            'karantina'  => strtoupper($this->input->get('karantina') ?? 'H'),
            'permohonan' => strtoupper($this->input->get('permohonan')),
            'start_date' => $this->input->get('start_date'),
            'end_date'   => $this->input->get('end_date'),
            'search'     => $this->input->get('search', true),
        ];

        if (empty($filters['start_date'])) {
            return $this->json_error('Export wajib menggunakan filter tanggal');
        }
        $rows = $this->Penahanan_model->getExportByFilter($filters);

        if (!$rows) {
            return $this->json_error('Data tidak ditemukan');
        }

        $headers = [
            'No', 'No P5', 'Tgl P5', 'Satpel', 'Pengirim', 'Penerima',
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
                $isIdem ? '' : ($r['tgl_p5'] ?? ''),
                $isIdem ? '' : ($r['upt'] ?? ''),
                $isIdem ? '' : ($r['nama_pengirim'] ?? ''),
                $isIdem ? '' : ($r['nama_penerima'] ?? ''),
                $isIdem ? '' : ($r['asal'] ?? ''),
                $isIdem ? '' : ($r['tujuan'] ?? ''),
                $r['komoditas'] ?? '-',
                $r['volume'] ?? '-',
                $r['satuan'] ?? '-',
                $isIdem ? '' : ($r['alasan_string'] ?? ''),
                $isIdem ? '' : ($r['petugas'] ?? ''),
            ];

            $lastId = $r['id'];
        }

        $title = "LAPORAN TINDAKAN PENAHANAN (" . ($filters['karantina'] ?: 'ALL') . ")";
        $reportInfo = $this->buildReportHeader($title, $filters);

        return $this->excel_handler->download(
            "Laporan_Penahanan_" . date('Ymd'),
            $headers,
            $exportData,
            $reportInfo
        );
    }

    private function json_error(string $message, int $code = 400)
    {
        return $this->output
            ->set_status_header($code)
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'success' => false,
                'message' => $message
            ]));
    }
}