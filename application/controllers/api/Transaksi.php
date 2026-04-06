<?php
defined('BASEPATH') OR exit('No direct script access allowed');
ini_set('memory_limit', '512M');
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

        $filters = [
            'upt'        => $this->input->get('upt', TRUE),
            'karantina'  => strtoupper(trim($this->input->get('karantina', TRUE))),
            'lingkup'    => $this->input->get('lingkup', TRUE),
            'start_date' => $this->input->get('start_date', TRUE) ?: date('Y-m-d'),
            'end_date'   => $this->input->get('end_date', TRUE) ?: date('Y-m-d'),
        ];

        if (!empty($filters['karantina']) &&
            !in_array($filters['karantina'], ['H','I','T'], true)
        ) {
            return $this->json(400);
        }

        $rows = $this->Transaksi_model->getAll($filters);

        return $this->json([
            'success' => true,
            'data'    => $rows,
        ], 200);
    }

    public function export_excel()
    {
    $today = date('Y-m-d');
    $rawKarantina = strtoupper(trim($this->input->get('karantina', TRUE)));
    $karantina = (strlen($rawKarantina) > 1) ? substr($rawKarantina, -1) : $rawKarantina;

    $filters = [
        'upt'        => $this->input->get('upt', TRUE),
        'karantina'  => $karantina,
        'lingkup'    => $this->input->get('lingkup', TRUE),
        'start_date' => $this->input->get('start_date', TRUE) ?: $today,
        'end_date'   => $this->input->get('end_date', TRUE) ?: $today,
    ];

    $rows = $this->Transaksi_model->getAllForExcel($filters);

    if (empty($rows)) {
        return $this->json(['success' => false, 'message' => 'Data tidak ditemukan untuk diunduh'], 404);
    }

    $headers = [
        'No.', 'Sumber', 'No. Aju', 'Tgl Aju', 'No. Dokumen', 'Tgl Dokumen',
        'UPT', 'Satpel', 'Pengirim', 'Penerima', 'Asal', 'Tujuan',
        'Tempat Periksa', 'Tgl Periksa', 'Alamat Periksa', 'Komoditas', 'HS Code', 'Volume', 'Satuan'
    ];

    $exportData = [];
    $no = 1;
    $lastId = null;

    foreach ($rows as $r) {
        $isIdem = ($r['id'] === $lastId);

        $exportData[] = [
            $isIdem ? '' : $no++, 
            $r['sumber'] ?? '-',
            $r['no_aju'] ?? '-',
            $r['tgl_aju'] ?? '-',
            $r['no_dok'] ?? '-',
            $r['tgl_dok'] ?? '-',
            $r['upt'] ?? '-',
            $r['satpel'] ?? '-',
            $r['pengirim'] ?? '-',
            $r['penerima'] ?? '-',
            trim($r['asal_kota'] ?? ' - ', ' -'),
            trim($r['tujuan_kota'] ?? ' - ', ' -'),
            $r['tempat_periksa'] ?? '-',
            $r['tgl_periksa'] ?? '-',
            $r['alamat_periksa'] ?? '-',
            $r['komoditas'] ?? '-',
            $r['hs'] ?? '-',
            (float) ($r['volume'] ?? 0), 
            $r['satuan'] ?? '-'
        ];

        $lastId = $r['id'];
    }

    $title = "LAPORAN TRANSAKSI " . strtoupper($filters['lingkup'] ?? 'SEMUA'); 
    $reportInfo = $this->buildReportHeader($title, $filters);
    if (ob_get_length()) ob_end_clean();

    return $this->excel_handler->download(
        "Laporan_Transaksi_" . date('Ymd_His'), 
        $headers, 
        $exportData, 
        $reportInfo
    );
}

 
}