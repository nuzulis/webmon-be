<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * @property CI_Input   $input
 * @property Nnc_model  $Nnc_model
 * @property Excel_handler $excel_handler
 */
class Nnc extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Nnc_model');
        $this->load->library('excel_handler');
    }

    public function index()
    {
        $filter = [
            'upt'        => $this->input->get('upt', TRUE),
            'karantina'  => $this->input->get('karantina', TRUE),
            'lingkup'    => $this->input->get('lingkup', TRUE),
            'start_date' => $this->input->get('start_date', TRUE),
            'end_date'   => $this->input->get('end_date', TRUE),
            'search'     => $this->input->get('search', TRUE),
            'sort_by'    => $this->input->get('sort_by', TRUE),
            'sort_order' => $this->input->get('sort_order', TRUE),
        ];

        $page    = max((int) $this->input->get('page'), 1);
        $perPage = (int) $this->input->get('per_page') ?: 10;
        $offset  = ($page - 1) * $perPage;
        $ids = $this->Nnc_model->getIds($filter, $perPage, $offset);
        $data = $this->Nnc_model->getByIds($ids);
        $total = $this->Nnc_model->countAll($filter);

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
        $filter = [
            'upt'        => $this->input->get('upt', TRUE),
            'karantina'  => $this->input->get('karantina', TRUE),
            'lingkup'    => $this->input->get('lingkup', TRUE),
            'start_date' => $this->input->get('start_date', TRUE),
            'end_date'   => $this->input->get('end_date', TRUE),
            'search'     => $this->input->get('search', TRUE),
        ];
        $rows = $this->Nnc_model->getFullData($filter);

        if (empty($rows)) {
            return $this->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }

        $headers = [
            'No', 'No. NNC', 'Tgl NNC', 'UPT/Satpel', 'Kepada', 'Pengirim', 'Penerima', 
            'Asal', 'Tujuan', 'Komoditas', 'HS Code', 'Volume', 'Satuan', 'Nature of Non-Compliance', 'Petugas'
        ];

        $exportData = [];
        $no = 0;
        $lastAju = null;

        foreach ($rows as $r) {
            $isSame = ($r['no_aju'] === $lastAju);
            if (!$isSame) { $no++; }

            $exportData[] = [
                $isSame ? '' : $no,
                $isSame ? 'Idem' : ($r['nomor_penolakan'] ?? '-'),
                $isSame ? 'Idem' : ($r['tgl_penolakan'] ?? '-'),
                $isSame ? 'Idem' : ($r['upt_full'] ?? '-'),
                $isSame ? 'Idem' : ($r['kepada'] ?? '-'),
                $isSame ? 'Idem' : ($r['nama_pengirim'] ?? '-'),
                $isSame ? 'Idem' : ($r['nama_penerima'] ?? '-'),
                $isSame ? 'Idem' : ($r['asal'] . ' - ' . $r['kota_asal']),
                $isSame ? 'Idem' : ($r['tujuan'] . ' - ' . $r['kota_tujuan']),
                $r['komoditas'] ?? '-',
                $r['kode_hs'] ?? '-',
                $r['volume'] ?? 0,
                $r['satuan'] ?? '-',
                $isSame ? 'Idem' : ($r['nnc_reason_text'] ?? '-'),
                $isSame ? 'Idem' : ($r['petugas'] ?? '-')
            ];

            $lastAju = $r['no_aju'];
        }

        $title = "LAPORAN NNC";
        $reportInfo = $this->buildReportHeader($title, $filter, $rows);

        $this->logActivity("EXPORT EXCEL NNC");

        if (ob_get_length()) ob_end_clean();
        
        return $this->excel_handler->download(
            "Laporan_NNC_" . date('Ymd_His'), 
            $headers, 
            $exportData, 
            $reportInfo
        );
    }
}