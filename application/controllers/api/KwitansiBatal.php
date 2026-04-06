<?php
defined('BASEPATH') OR exit('No direct script access allowed');
ini_set('memory_limit', '512M');
/**
 * @property CI_Input             $input
 * @property CI_Session           $session
 * @property KwitansiBatal_model  $KwitansiBatal_model
 * @property Excel_handler        $excel_handler
 */
class KwitansiBatal extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('KwitansiBatal_model');
        $this->load->library('session');
        $this->load->library('excel_handler');
    }

    public function index()
    {
        $filters = [
            'karantina'   => $this->input->get('karantina', TRUE),
            'permohonan'  => $this->input->get('permohonan', TRUE),
            'upt'         => $this->input->get('upt', TRUE) ?: 'all',
            'start_date'  => $this->input->get('start_date', TRUE),
            'end_date'    => $this->input->get('end_date', TRUE),
            'berdasarkan' => $this->input->get('berdasarkan', TRUE),
        ];

        $this->applyScope($filters);

        $data = $this->KwitansiBatal_model->getAll($filters);

        return $this->json(['success' => true, 'data' => $data], 200);
    }

    public function export_excel()
    {
        $filters = [
            'karantina'   => $this->input->get('karantina', TRUE),
            'permohonan'  => $this->input->get('permohonan', TRUE),
            'upt'         => $this->input->get('upt', TRUE) ?: 'all',
            'start_date'  => $this->input->get('start_date', TRUE),
            'end_date'    => $this->input->get('end_date', TRUE),
            'berdasarkan' => $this->input->get('berdasarkan', TRUE),
        ];
        $rows = $this->KwitansiBatal_model->getFullData($filters);

        if (empty($rows)) {
            return $this->json([
                'success' => false,
                'message' => 'Data tidak ditemukan'
            ], 404);
        }
        $headers = [
            'No.', 
            'UPT', 
            'Satpel', 
            'Pos Pelayanan', 
            'Karantina', 
            'Nomor Kwitansi', 
            'Tanggal Kwitansi', 
            'Jenis Permohonan', 
            'Nama Wajib Bayar', 
            'Tipe Bayar', 
            'Total PNBP', 
            'Kode Billing', 
            'NTPN', 
            'NTB', 
            'Dibuat Tanggal', 
            'Alasan Batal', 
            'Tanggal Batal'
        ];

        $exportData = [];
        $no = 1;
        
        foreach ($rows as $r) {
            $exportData[] = [
                $no++,
                $r['upt'] ?? '-',
                $r['satpel'] ?? '-',
                $r['pos_pelayanan'] ?? '-',
                $r['jenis_karantina'] ?? '-',
                $r['nomor'] ?? '-',
                $r['tanggal'] ?? '-',
                $r['jenis_permohonan'] ?? '-',
                $r['wajib_bayar'] ?? '-',
                $r['tipe_bayar'] ?? '-',
                (float) ($r['total_pnbp'] ?? 0),
                "'" . ($r['kode_bill'] ?? '-'),
                $r['ntpn'] ?? '-',
                $r['ntb'] ?? '-',
                $r['created_at'] ?? '-',
                $r['alasan_hapus'] ?? '-',
                $r['deleted_at'] ?? '-'
            ];
        }

        $title = "LAPORAN KUITANSI BATAL";
        $reportInfo = $this->buildReportHeader($title, $filters, $rows);
        $reportInfo['sub_judul'] = "Biro Umum dan Keuangan";
        
        $this->logActivity("EXPORT EXCEL: Kuitansi Batal Periode " . $filters['start_date']);
        
        return $this->excel_handler->download("Kuitansi_Batal", $headers, $exportData, $reportInfo);
    }
}