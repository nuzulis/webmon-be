<?php
defined('BASEPATH') OR exit('No direct script access allowed');
ini_set('memory_limit', '512M');
/**
 * @property CI_Input         $input
 * @property CI_Output        $output
 * @property CI_Session       $session
 * @property Kwitansi_model   $Kwitansi_model
 * @property Excel_handler    $excel_handler
 */
class Kwitansi extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Kwitansi_model');
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

        $data = $this->Kwitansi_model->getAll($filters);

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
        $data = $this->Kwitansi_model->getFullData($filters);

        if (empty($data)) {
            return $this->json([
                'success' => false,
                'message' => 'Data Kwitansi tidak ditemukan'
            ], 404);
        }

        $headers = [
            'No.', 'UPT', 'Satpel', 'Karantina', 
            'Nomor Kwitansi', 'Tanggal Kwitansi', 'Jenis Permohonan', 
            'Nama Wajib Bayar', 'Tipe Bayar', 'Total PNBP', 'Kode Billing', 
            'NTPN', 'NTB', 'Tanggal Billing', 'Tanggal Setor', 'Bank'
        ];

        $exportData = [];
        $no = 1;
        
        foreach ($data as $item) {
            $exportData[] = [
                $no++,
                $item['nama_upt'],
                $item['nama_satpel'],
                $item['jenis_karantina'],
                $item['nomor'],
                $item['tanggal'],
                $item['jenis_permohonan'],
                $item['nama_wajib_bayar'],
                $item['tipe_bayar'],
                $item['total_pnbp'],
                "'" . $item['kode_bill'],
                $item['ntpn'],
                $item['ntb'],
                $item['date_bill'],
                $item['date_setor'],
                $item['bank']
            ];
        }

        $labelBerdasarkan = match ($filters['berdasarkan']) {
            'D' => 'Tgl Dokumen',
            'K', 'Q' => 'Tgl Kuitansi',
            'B' => 'Tgl Bayar',
            default => 'Tgl Setor'
        };

        $title = "LAPORAN PNBP (BERDASARKAN " . strtoupper($labelBerdasarkan) . ")";
        $reportInfo = $this->buildReportHeader($title, $filters, $data);

        $this->logActivity("EXPORT EXCEL: KWITANSI PNBP PERIODE {$filters['start_date']}");

        return $this->excel_handler->download("Kwitansi_PNBP", $headers, $exportData, $reportInfo);
    }
}