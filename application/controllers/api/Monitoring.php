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
    
    public function index()
{
    $page    = (int) $this->input->get('page') ?: 1;
    $perPage = (int) $this->input->get('per_page') ?: 10;
    $offset  = ($page - 1) * $perPage;

    $filter = [
        'upt'        => $this->input->get('upt'),
        'lingkup'    => $this->input->get('lingkup'),
        'karantina'  => $this->input->get('karantina'),
        'start_date' => $this->input->get('start_date'),
        'end_date'   => $this->input->get('end_date'),
        'search'     => $this->input->get('search'),
    ];

    $data  = $this->Monitoring_model->getList($filter, $perPage, $offset);
    $total = $this->Monitoring_model->countAll($filter);

    $this->output
        ->set_content_type('application/json')
        ->set_output(json_encode([
            'data' => $data,
            'meta' => [
                'page'       => $page,
                'per_page'   => $perPage,
                'total'      => $total,
                'total_page' => ceil($total / $perPage),
            ],
        ]));
}


    public function export_excel()
{
    $karantinaRaw = strtoupper($this->input->get('karantina', true));
    $map = ['H'=>'H','I'=>'I','T'=>'T','KH'=>'H','KI'=>'I','KT'=>'T'];

    $filter = [
        'upt'        => $this->input->get('upt', true),
        'lingkup'    => $this->input->get('lingkup', true),
        'karantina'  => $map[$karantinaRaw] ?? null,
        'start_date' => $this->input->get('start_date', true),
        'end_date'   => $this->input->get('end_date', true),
        'search'     => $this->input->get('search'),
    ];

    if (!$filter['karantina']) {
        return $this->_error('Jenis karantina tidak valid');
    }
    $rows = $this->Monitoring_model->getExportData($filter);

    $headers = [
        'Nomor Aju', 'No.K.1.1', 'Satpel', 'Pengirim', 'Penerima',
        'Tgl Permohonan', 'Tgl Periksa Fisik', 'Tgl Lepas',
        'Status', 'SLA', 'Komoditas', 'Nama Tercetak',
        'volumeP1','volumeP2','volumeP3','volumeP4',
        'volumeP5','volumeP6','volumeP7','volumeP8','Satuan'
    ];

    $exportData = [];
    $lastAju = null;
    $no = 0;

    foreach ($rows as $r) {
        $isSame = ($r['no_aju'] === $lastAju);
        if (!$isSame) {
        $no++;
    }
        
        $exportData[] = [
            $isSame ? '' : $no,
            $isSame ? 'Idem' : ($r['no_aju'] ?? '-'),
            $isSame ? 'Idem' : ($r['no_dok'] ?? '-'),
            $isSame ? 'Idem' : ($r['upt_full'] ?? '-'),
            $isSame ? 'Idem' : ($r['nama_pengirim'] ?? '-'),
            $isSame ? 'Idem' : ($r['nama_penerima'] ?? '-'),
            $isSame ? 'Idem' : ($r['tgl_dok_permohonan'] ?? '-'),
            $isSame ? 'Idem' : ($r['tgl_periksa'] ?? '-'),
            $isSame ? 'Idem' : ($r['tanggal_lepas'] ?? '-'),
            $isSame ? 'Idem' : ($r['status'] ?? 'Proses'),
            $isSame ? 'Idem' : ($r['sla'] ?? '-'),
            $r['komoditas'] ?? '-',
            $r['nama_umum_tercetak'] ?? '-',
            $r['p1'] ?? 0, $r['p2'] ?? 0, $r['p3'] ?? 0, $r['p4'] ?? 0,
            $r['p5'] ?? 0, $r['p6'] ?? 0, $r['p7'] ?? 0, $r['p8'] ?? 0,
            $r['satuan'] ?? '-'
        ];

        $lastAju = $r['no_aju'];
    }

    $this->logActivity("EXPORT EXCEL MONITORING: " . $filter['karantina']);
    if (ob_get_length()) ob_end_clean();

    return $this->excel_handler->download(
        "Monitoring_Operasional_" . date('Ymd_His'),
        $headers,
        $exportData,
        $this->buildReportHeader("MONITORING SLA OPERASIONAL", $filter, $rows)
    );
}


    private function _error($msg) {
        return $this->output->set_status_header(400)
            ->set_content_type('application/json')
            ->set_output(json_encode(['success' => false, 'message' => $msg]));
    }
}