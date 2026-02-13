<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * @property CI_Input         $input
 * @property Monitoring_model $Monitoring_model
 * @property Excel_handler    $excel_handler
 */
class Monitoring extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Monitoring_model');
        $this->load->library('excel_handler');
    }
    
    public function index()
    {
        $filter = [
            'upt'        => $this->input->get('upt', TRUE),
            'lingkup'    => $this->input->get('lingkup', TRUE),
            'karantina'  => $this->input->get('karantina', TRUE),
            'start_date' => $this->input->get('start_date', TRUE),
            'end_date'   => $this->input->get('end_date', TRUE),
            'search'     => $this->input->get('search', TRUE),
            'sort_by'    => $this->input->get('sort_by', TRUE),
            'sort_order' => $this->input->get('sort_order', TRUE),
        ];

        $page    = max((int) $this->input->get('page'), 1);
        $perPage = (int) $this->input->get('per_page') ?: 10;
        $offset  = ($page - 1) * $perPage;
        $ids   = $this->Monitoring_model->getIds($filter, $perPage, $offset);
        $data  = $this->Monitoring_model->getByIds($ids);
        $total = $this->Monitoring_model->countAll($filter);

        return $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'success' => true,
                'data'    => $data,
                'meta'    => [
                    'page'       => $page,
                    'per_page'   => $perPage,
                    'total'      => $total,
                    'total_page' => (int) ceil($total / $perPage),
                ],
            ], JSON_UNESCAPED_UNICODE));
    }

    public function export_excel()
    {
        $karantinaRaw = strtoupper($this->input->get('karantina', TRUE));    
        $map = ['H'=>'H', 'I'=>'I', 'T'=>'T', 'KH'=>'H', 'KI'=>'I', 'KT'=>'T'];

        $filter = [
            'upt'        => $this->input->get('upt', TRUE),
            'lingkup'    => $this->input->get('lingkup', TRUE),
            'karantina' => $map[$karantinaRaw] ?? 'H',
            'start_date' => $this->input->get('start_date', TRUE),
            'end_date'   => $this->input->get('end_date', TRUE),
            'search'     => $this->input->get('search', TRUE),
        ];

        if (!$filter['karantina']) {
            return $this->json([
                'success' => false,
                'message' => 'Jenis karantina tidak valid'
            ], 400);
        }
        $rows = $this->Monitoring_model->getFullData($filter);

        if (empty($rows)) {
            return $this->json([
                'success' => false,
                'message' => 'Data tidak ditemukan'
            ], 404);
        }

        $headers = [
            'No.', 'Nomor Aju', 'No.K.1.1', 'Satpel', 'Pengirim', 'Penerima',
            'Tgl Permohonan', 'Tgl Periksa Fisik', 'Tgl Lepas',
            'Status', 'SLA', 'Komoditas', 'Nama Tercetak',
            'Vol P1', 'Vol P2', 'Vol P3', 'Vol P4',
            'Vol P5', 'Vol P6', 'Vol P7', 'Vol P8', 'Satuan'
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

        $title = "MONITORING SLA";
        $reportInfo = $this->buildReportHeader($title, $filter, $rows);

        $this->logActivity("EXPORT EXCEL MONITORING: " . $filter['karantina']);

        if (ob_get_length()) ob_end_clean();

        return $this->excel_handler->download(
            "Monitoring_Operasional_" . date('Ymd_His'),
            $headers,
            $exportData,
            $reportInfo
        );
    }
}