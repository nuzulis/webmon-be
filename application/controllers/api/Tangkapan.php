<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * @property CI_Input          $input
 * @property CI_Output         $output  
 * @property CI_Config         $config
 * @property Tangkapan_model   $Tangkapan_model
 * @property Excel_handler     $excel_handler
 */
class Tangkapan extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Tangkapan_model');
        $this->load->helper('jwt');
        $this->load->library('excel_handler');
    }

    public function index()
    {
        $auth = $this->input->get_request_header('Authorization', true);
        if (!$auth || !preg_match('/Bearer\s+(\S+)/', $auth, $m)) {
            return $this->json(401);
        }
        $sortBy    = $this->input->get('sort_by', true) ?: 'tgl_p3';
        $sortOrder = strtoupper($this->input->get('sort_order', true)) === 'ASC' ? 'ASC' : 'DESC';
        
        $filters = [
            'upt'        => $this->input->get('upt'),
            'karantina'  => strtoupper($this->input->get('karantina')),
            'permohonan' => strtoupper($this->input->get('permohonan')),
            'start_date' => $this->input->get('start_date'),
            'end_date'   => $this->input->get('end_date'),
            'search'     => $this->input->get('search', true),
            'sort_by'    => $sortBy,
            'sort_order' => $sortOrder,
        ];

        $page    = max((int)$this->input->get('page'), 1);
        $perPage = max((int)$this->input->get('per_page'), 10);
        $offset  = ($page - 1) * $perPage;
        
        $ids = $this->Tangkapan_model->getIds($filters, $perPage, $offset);
        $rows = $ids
            ? $this->Tangkapan_model->getByIds($ids, $filters['karantina'], $sortBy, $sortOrder)
            : [];
        $total = $this->Tangkapan_model->countAll($filters);
        

        return $this->json([
            'success' => true,
            'data'    => $rows,
            'meta'    => [
                'page'       => $page,
                'per_page'   => $perPage,
                'total'      => $total,
                'total_page' => ceil($total / $perPage),
            ],
        ], 200);
    }
    
    public function export_excel()
    {
        $filters = [
            'upt'        => $this->input->get('upt'),
            'karantina'  => strtoupper($this->input->get('karantina')),
            'permohonan' => strtoupper($this->input->get('permohonan')),
            'start_date' => $this->input->get('start_date'),
            'end_date'   => $this->input->get('end_date'),
            'search'     => $this->input->get('search', true),
        ];

        $ids = $this->Tangkapan_model->getIds($filters, 5000, 0);
        $rows = $ids
            ? $this->Tangkapan_model->getExportByIds($ids, $filters['karantina'])
            : [];

        $headers = [
            'No', 'No. P3 (Tangkapan)', 'Tanggal P3', 'UPT', 'Satpel',
            'Lokasi', 'Keterangan Lokasi', 'Asal', 'Tujuan',
            'Pengirim', 'Penerima', 'Komoditas', 'Volume', 'Satuan',
            'Alasan Tahan', 'Petugas Pelaksana', 'Rekomendasi'
        ];

        $exportData = [];
        $no = 1;
        $lastNoP3 = null;

        foreach ($rows as $r) {
            $isIdem = ($r['no_p3'] === $lastNoP3);

            $exportData[] = [
                $isIdem ? '' : $no++,
                $isIdem ? 'Idem' : ($r['no_p3'] ?? '-'),
                $r['tgl_p3'] ?? '-',
                $r['upt'] ?? '-',
                $r['satpel'] ?? '-',
                $r['lokasi_label'] ?? '-',
                $r['ket_lokasi'] ?? '-',
                $r['asal'] ?? '-',
                $r['tujuan'] ?? '-',
                $r['pengirim'] ?? '-',
                $r['penerima'] ?? '-',
                $r['komoditas'] ?? '-',
                $r['volume'] ?? '-',
                $r['satuan'] ?? '-',
                $r['alasan_tahan'] ?? '-',
                $r['petugas_pelaksana'] ?? '-',
                $r['rekomendasi'] ?? '-',
            ];

            $lastNoP3 = $r['no_p3'];
        }

        $title = "LAPORAN TANGKAPAN - " . ($filters['karantina'] ?: 'ALL');
        $reportInfo = $this->buildReportHeader($title, $filters);

        return $this->excel_handler->download(
            "Laporan_Tangkapan",
            $headers,
            $exportData,
            $reportInfo
        );
    }
}