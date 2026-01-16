<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * @property Monitoring_model $Monitoring_model
 * @property Excel_handler      $excel_handler
 */
class Monitoring extends MY_Controller {

    public function __construct() {
        parent::__construct();
        $this->load->model('Monitoring_model');
        $this->load->library('excel_handler');
    }
    public function index() {
        // 1. Ambil & Normalisasi Filter
        $karantinaRaw = strtoupper($this->input->get('karantina', true));
        $map = ['H'=>'H','I'=>'I','T'=>'T','KH'=>'H','KI'=>'I','KT'=>'T'];
        
        $filters = [
            'upt_id'     => $this->input->get('upt', true),
            'karantina'  => $map[$karantinaRaw] ?? null,
            'permohonan' => $this->input->get('permohonan', true),
            'start_date' => $this->input->get('start_date', true),
            'end_date'   => $this->input->get('end_date', true),
        ];

        // 2. Validasi Dasar
        if (!$filters['karantina']) return $this->_error('Jenis karantina tidak valid');
        if (!$filters['start_date'] || !$filters['end_date']) return $this->_error('Tanggal wajib diisi');

        // 3. Security Scope (Otomatis membatasi akses UPT)
        $this->applyScope($filters);

        // 4. Pagination
       // --- PAGINATION STANDAR ---
        $page   = max((int) $this->input->get('page'), 1);
        $limit  = (int) $this->input->get('limit');
        $limit  = ($limit > 0) ? min($limit, 100) : 50; // Default 50, Maks 100
        $offset = ($page - 1) * $limit;

        // 5. Eksekusi menggunakan getList (Bridge di BaseModelStrict)
        $data  = $this->Monitoring_model->getList($filters, $limit, $offset);
        $total = $this->Monitoring_model->countAll($filters);

        return $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'success'      => true,
                'recordsTotal' => $total,
                'page'         => $page,
                'data'         => $data
            ]));
    }

    public function export_excel() {
    $karantinaRaw = strtoupper($this->input->get('karantina', true));
    $map = ['H'=>'H','I'=>'I','T'=>'T','KH'=>'H','KI'=>'I','KT'=>'T'];
    
    $filters = [
        'upt_id'     => $this->input->get('upt', true),
        'karantina'  => $map[$karantinaRaw] ?? null,
        'permohonan' => $this->input->get('permohonan', true),
        'start_date' => $this->input->get('start_date', true),
        'end_date'   => $this->input->get('end_date', true),
    ];

    if (!$filters['karantina']) return $this->_error('Jenis karantina tidak valid');

    // Ambil data tanpa limit untuk export
    $ids = $this->Monitoring_model->getIds($filters, 5000, 0); 
    $rows = $this->Monitoring_model->getByIds($ids);

    $headers = [
        'Nomor Aju', 'No.K.1.1', 'Satpel', 'Pengirim', 'Penerima', 
        'Tgl Permohonan', 'Tgl Periksa Fisik', 'Tgl Lepas', 'Status', 
        'SLA', 'Komoditas', 'Nama Tercetak', 'volumeP1', 'volumeP2', 
        'volumeP3', 'volumeP4', 'volumeP5', 'volumeP6', 'volumeP7', 
        'volumeP8', 'Satuan'
    ];

    $exportData = [];
    foreach ($rows as $r) {
        $exportData[] = [
            $r['no_aju'],
            $r['no_dok'],
            $r['nama_satpel'],
            $r['nama_pengirim'],
            $r['nama_penerima'],
            $r['tgl_dok_permohonan'],
            $r['tgl_periksa'],
            $r['tanggal_lepas'],
            $r['status'],
            $r['sla'],
            $r['komoditas'],
            $r['nama_umum_tercetak'],
            $r['p1'], $r['p2'], $r['p3'], $r['p4'], 
            $r['p5'], $r['p6'], $r['p7'], $r['p8'],
            $r['satuan']
        ];
    }

        $title = "MONITORING SLA " . strtoupper($filters['karantina']);

        // Gunakan $rows (data hasil query) untuk mendapatkan nama UPT
        $reportInfo = $this->buildReportHeader($title, $filters, $rows);

        // 5. Load Library & Export
        $this->load->library('excel_handler');
        $this->logActivity("EXPORT EXCEL: Monitoring Operasional Periode " . $filters['start_date']);

        return $this->excel_handler->download("Monitoring_Operasional", $headers, $exportData, $reportInfo);
        }

    private function _error($msg) {
        return $this->output->set_status_header(400)
            ->set_content_type('application/json')
            ->set_output(json_encode(['success' => false, 'message' => $msg]));
    }
}