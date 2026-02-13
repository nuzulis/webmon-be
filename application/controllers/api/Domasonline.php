<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * @property CI_Input          $input
 * @property CI_Output         $output
 * @property CI_Config         $config
 * @property Domasonline_model $Domasonline_model
 * @property Excel_handler     $excel_handler
 */
class Domasonline extends MY_Controller 
{
    public function __construct() {
        parent::__construct();
        $this->load->model('Domasonline_model');
        $this->load->library('excel_handler'); 
    }

    public function index() {
        $filters = [
            'upt'        => $this->input->get('upt', TRUE),
            'karantina'  => strtolower($this->input->get('karantina', TRUE) ?: 'kh'),
            'start_date' => $this->input->get('start_date', TRUE),
            'end_date'   => $this->input->get('end_date', TRUE),
            'search'     => $this->input->get('search', TRUE),
            'sort_by'    => $this->input->get('sort_by', TRUE),
            'sort_order' => $this->input->get('sort_order', TRUE),
        ];

        $page    = max((int)$this->input->get('page'), 1);
        $perPage = (int)$this->input->get('per_page') ?: 10;
        $offset  = ($page - 1) * $perPage;
        $ids   = $this->Domasonline_model->getIds($filters, $perPage, $offset);
        $data  = $this->Domasonline_model->getByIds($ids);
        $total = $this->Domasonline_model->countAll($filters);

        return $this->json([
            'success' => true,
            'data'    => $data,
            'meta'    => [
                'page'       => $page,
                'per_page'   => $perPage,
                'total'      => $total,
                'total_page' => (int) ceil($total / $perPage)
            ]
        ]);
    }

    public function export_excel()
    {
        $filters = [
            'upt'        => $this->input->get('upt', TRUE),
            'karantina'  => strtolower($this->input->get('karantina', TRUE) ?: 'kh'),
            'start_date' => $this->input->get('start_date', TRUE),
            'end_date'   => $this->input->get('end_date', TRUE),
            'search'     => $this->input->get('search', TRUE),
        ];

        $rows = $this->Domasonline_model->getFullData($filters);

        $headers = [
            'No.', 'Status', 'No. Sertifikat (Asal)', 'Tgl Lepas', 'UPT Asal', 'UPT Tujuan (Bongkar)',
            'Nama Pengirim', 'Nama Penerima', 'Komoditas', 'Volume', 'Satuan', 
            'No. Permohonan Masuk (DM)', 'Tgl Diterima'
        ];

        $exportData = [];
        $no = 1;
        $lastId = null;

        foreach ($rows as $r) {
            $isIdem = ($r['id'] === $lastId);
            
            $exportData[] = [
                $isIdem ? '' : $no++, 
                $isIdem ? 'Idem' : $r['status_penerimaan'],
                $isIdem ? 'Idem' : $r['nkt'],
                $isIdem ? 'Idem' : $r['tanggal_lepas'],
                $isIdem ? 'Idem' : $r['upt_asal'] . ' - ' . $r['satpel_asal'],
                $isIdem ? 'Idem' : $r['upt_tujuan'] . ' - ' . $r['satpel_tujuan'],
                $isIdem ? 'Idem' : $r['nama_pengirim'],
                $isIdem ? 'Idem' : $r['nama_penerima'],
                $r['komoditas'],
                $r['volume'],
                $r['satuan'],
                $isIdem ? 'Idem' : ($r['no_dok_dm'] ?? '-'),
                $isIdem ? 'Idem' : ($r['tgl_dok_dm'] ?? '-')
            ];
            $lastId = $r['id'];
        }

        $title = "LAPORAN MONITORING DOMASONLINE (" . strtoupper($filters['karantina']) . ")";
        $reportInfo = $this->buildReportHeader($title, $filters, $rows);
        
        $this->load->library('excel_handler');
        $this->logActivity("DOWNLOAD EXCEL: Domasonline Periode {$filters['start_date']} s/d {$filters['end_date']}");
        
        return $this->excel_handler->download("Monitoring_Domasonline", $headers, $exportData, $reportInfo);
    }
}