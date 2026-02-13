<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * @property CI_Input   $input
 * @property CI_Output  $output
 * @property CI_Config  $config
 * @property CI_DB_query_builder $db
 * @property Transaksi_model $Transaksi_model
 * @property Excel_handler    $excel_handler
 * 
 */
class Transaksi extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Transaksi_model');
        $this->load->helper(['jwt']);
        $this->load->library('excel_handler');
    }

    public function index()
    {
        $auth = $this->input->get_request_header('Authorization', TRUE);
        if (!$auth || !preg_match('/Bearer\s+(\S+)/', $auth, $m)) {
            return $this->json(401);
        }
        
        $sortBy    = $this->input->get('sort_by', true) ?: 'tgl_dok';
        $sortOrder = strtoupper($this->input->get('sort_order', true)) === 'ASC' ? 'ASC' : 'DESC';

        
       $filters = [
            'upt'        => $this->input->get('upt', TRUE),
            'karantina'  => strtoupper(trim($this->input->get('karantina', TRUE))),
            'permohonan' => strtoupper(trim($this->input->get('permohonan', TRUE))),
            'start_date' => $this->input->get('start_date', TRUE) ?: date('Y-m-d'),
            'end_date'   => $this->input->get('end_date', TRUE) ?: date('Y-m-d'),
            'search'     => $this->input->get('search', true),
            'sort_by'    => $sortBy,
            'sort_order' => $sortOrder,
        ];

        if (!empty($filters['karantina']) &&
            !in_array($filters['karantina'], ['H','I','T'], true)
        ) {
            return $this->json(400);
        }

       
        $page    = max((int) $this->input->get('page'), 1);
        $perPage = max((int) $this->input->get('per_page'), 10);
        $offset  = ($page - 1) * $perPage;

        $ids = $this->Transaksi_model->getIds($filters, $perPage, $offset);
        $rows = empty($ids)
            ? []
            : $this->Transaksi_model->getByIds($ids, $filters['karantina'], $sortBy, $sortOrder);
        $total = $this->Transaksi_model->countAll($filters);

        return $this->json([
            'success' => true,
            'data'    => $rows,
            'meta'    => [
                'page'       => $page,
                'per_page'   => $perPage,
                'total'      => $total,
                'total_page' => (int) ceil($total / $perPage),
            ]
        ],200);
    }

    public function export_excel()
{
    $today = date('Y-m-d');
    
    $rawKarantina = strtoupper(trim($this->input->get('karantina', TRUE)));
    $karantina = (strlen($rawKarantina) > 1) ? substr($rawKarantina, -1) : $rawKarantina;

    $filters = [
        'upt'        => $this->input->get('upt', TRUE),
        'karantina'  => $karantina,
        'permohonan' => strtoupper(trim($this->input->get('permohonan', TRUE))),
        'start_date' => $today . ' 00:00:00',
        'end_date'   => $today . ' 23:59:59'
    ];
   $ids = $this->Transaksi_model->getIds($filters, 5000, 0);
    $rows = $ids ? $this->Transaksi_model->getByIds($ids, $filters['karantina']) : [];


    $headers = [
        'No', 'Sumber', 'No. Aju', 'Tgl Aju', 'No. Dokumen', 'Tgl Dokumen',
        'UPT', 'Satpel', 'Pengirim', 'Penerima', 'Asal', 'Tujuan',
        'Tempat Periksa', 'Tgl Periksa', 'Komoditas', 'HS Code', 'Volume', 'Satuan'
    ];

    
    $exportData = [];
    $no = 1;
    $lastAju = null;

    foreach ($rows as $r) {
        $isIdem = ($r['no_aju'] === $lastAju);

        $exportData[] = [
            $isIdem ? '' : $no++, 
            $isIdem ? 'Idem' : ($r['sumber'] ?? '-'),
            $isIdem ? 'Idem' : ($r['no_aju'] ?? '-'),
            $r['tgl_aju'] ?? '-',
            $isIdem ? 'Idem' : ($r['no_dok'] ?? '-'),
            $r['tgl_dok'] ?? '-',
            $r['upt'] ?? '-',
            $r['satpel'] ?? '-',
            $r['pengirim'] ?? '-',
            $r['penerima'] ?? '-',
            $r['asal_kota'] ?? '-',
            $r['tujuan_kota'] ?? '-',
            $r['tempat_periksa'] ?? '-',
            $r['tgl_periksa'] ?? '-',
            str_replace('<br>', "\n", $r['komoditas'] ?? '-'),
            str_replace('<br>', "\n", $r['hs'] ?? '-'),
            str_replace('<br>', "\n", $r['volume'] ?? '-'),
            str_replace('<br>', "\n", $r['satuan'] ?? '-')
        ];

        $lastAju = $r['no_aju'];
    }

    $title = "LAPORAN TRANSAKSI HARI INI (" . date('d F Y') . ") - " . ($filters['karantina'] ?: 'ALL');
    $reportInfo = $this->buildReportHeader($title, $filters);

    return $this->excel_handler->download("Transaksi_Hari_Ini_" . date('Ymd'), $headers, $exportData, $reportInfo);
}

 
}