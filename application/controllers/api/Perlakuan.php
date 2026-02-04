<?php
defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * @property CI_Input          $input
 * @property CI_Output         $output
 * @property CI_Config         $config
 * @property Perlakuan_model   $Perlakuan_model
 * @property Excel_handler     $excel_handler
 */
class Perlakuan extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Perlakuan_model');
        $this->load->helper(['jwt']);
        $this->load->library('excel_handler');
    }

    public function index()
    {
       
        $auth = $this->input->get_request_header('Authorization', TRUE);
        if (!$auth || !preg_match('/Bearer\s+(\S+)/', $auth, $m)) {
            return $this->json(401);
        }
        

        $filters = [
            'upt'        => $this->input->get('upt'),
            'karantina'  => strtoupper($this->input->get('karantina')),
            'permohonan' => strtoupper($this->input->get('permohonan')),
            'start_date' => $this->input->get('start_date'),
            'end_date'   => $this->input->get('end_date'),
            'search'     => $this->input->get('search', true),
        ];

        $page    = max((int) $this->input->get('page'), 1);
        $perPage = ((int) $this->input->get('per_page') === 10) ? 10 : 10;
        $offset  = ($page - 1) * $perPage;

        $ids = $this->Perlakuan_model
            ->getIds($filters, $perPage, $offset);


        $rows = [];
        if ($ids) {
            $rows = $this->Perlakuan_model
                ->getByIds($ids, $filters['karantina']);
        }

        $total = $this->Perlakuan_model->countAll($filters);

        return $this->json([
            'success' => true,
            'data'    => $rows,
            'meta'    => [
                'page'       => $page,
                'per_page'   => $perPage,
                'total'      => $total,
                'total_page' => ceil($total / $perPage)
            ]
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

        $ids = $this->Perlakuan_model->getIds($filters, 5000, 0);
        
        $rows = [];
        if ($ids) {
            $rows = $this->Perlakuan_model
                ->getByIdsForExcel($ids, $filters['karantina']);

        }

        
        $headers = [
            'No', 'No. P4', 'Tgl P4', 'Satpel', 
            'Tempat Perlakuan', 'Pengirim', 'Penerima', 
            'Komoditas', 'Volume', 'Satuan',
            'Alasan', 'Metode', 'Tipe Perlakuan', 'Mulai', 'Selesai', 
            'Rekomendasi', 'Operator'
        ];

        $exportData = [];
        $no = 1;
        $lastId = null;

        foreach ($rows as $r) {
            $isIdem = ($r['id'] === $lastId);

            $exportData[] = [
                $isIdem ? '' : $no++,
                $isIdem ? 'Idem' : $r['no_p4'],
                $isIdem ? '' : $r['tgl_p4'],
                $isIdem ? '' : $r['upt'] . ' - ' . $r['nama_satpel'],
                $isIdem ? '' : $r['lokasi_perlakuan'],
                $isIdem ? '' : $r['nama_pengirim'],
                $isIdem ? '' : $r['nama_penerima'],
                $r['komoditas'],
                $r['volume'],
                $r['satuan'],
                $isIdem ? 'Idem' : $r['alasan_perlakuan'],
                $isIdem ? 'Idem' : $r['metode'],
                $isIdem ? '' : $r['tipe'],
                $isIdem ? '' : $r['mulai'],
                $isIdem ? '' : $r['selesai'],
                $isIdem ? '' : $r['rekom'],
                $isIdem ? '' : $r['nama_operator']
            ];

            $lastId = $r['id'];
        }

        $title = "LAPORAN TINDAKAN PERLAKUAN (" . ($filters['karantina'] ?: 'ALL') . ")";
        $reportInfo = $this->buildReportHeader($title, $filters);

        return $this->excel_handler->download("Laporan_Perlakuan", $headers, $exportData, $reportInfo);
    }

}
