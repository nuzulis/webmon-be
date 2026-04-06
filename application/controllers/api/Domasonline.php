<?php
defined('BASEPATH') OR exit('No direct script access allowed');
ini_set('memory_limit', '512M');
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
        ];

        $data = $this->Domasonline_model->getAll($filters);

        return $this->json([
            'success' => true,
            'data'    => $data,
        ]);
    }

    public function export_excel()
    {
        $filters = [
            'upt'        => $this->input->get('upt', TRUE),
            'karantina'  => strtolower($this->input->get('karantina', TRUE) ?: 'kh'),
            'start_date' => $this->input->get('start_date', TRUE),
            'end_date'   => $this->input->get('end_date', TRUE),
        ];

        $rows = $this->Domasonline_model->getFullData($filters);

        $headers = [
            'No.', 'Status', 'No. Sertifikat (Asal)', 'Tgl Lepas', 'UPT Asal', 'UPT Tujuan (Bongkar)',
            'Nama Pemohon', 'Nama Pengirim', 'Alamat Pemohon', 'Alamat Pengirim', 'Kota Asal',
            'Nama Penerima', 'Alamat Penerima', 'Kota Tujuan',
            'Komoditas', 'Volume', 'Satuan',
            'No. Permohonan Masuk (DM)', 'Tgl Diterima'
        ];

        $exportData = [];
        $no = 1;
        $lastId = null;

        foreach ($rows as $r) {
            $isIdem = ($r['id'] === $lastId);
            $volumeAngka = isset($r['volume']) ? (float) $r['volume'] : 0;
            $exportData[] = [
                $isIdem ? '' : $no++,
                $r['status_penerimaan'],
                $r['nkt'],
                $r['tanggal_lepas'],
                $r['upt_asal'] . ' - ' . $r['satpel_asal'],
                $r['upt_tujuan'] . ' - ' . $r['satpel_tujuan'],
                $r['nama_pemohon'],
                $r['nama_pengirim'],
                $r['alamat_pemohon'] ?? '',
                $r['alamat_pengirim'] ?? '',
                $r['kota_asal'] ?? '',
                $r['nama_penerima'],
                $r['alamat_penerima'] ?? '',
                $r['kota_tujuan'] ?? '',
                $r['komoditas'],
                $volumeAngka,
                $r['satuan'],
                ($r['no_dok_dm'] ?? '-'),
                ($r['tgl_dok_dm'] ?? '-')
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