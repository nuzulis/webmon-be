<?php
defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * @property CI_Input   $input
 * @property CI_Output  $output
 * @property CI_Config  $config
 * @property Nnc_model  $Nnc_model
 * @property Excel_handler $excel_handler
 */
class Nnc extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Nnc_model');
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
        'lingkup'    => strtoupper(trim($this->input->get('lingkup', TRUE))), 
        'start_date' => $this->input->get('start_date', TRUE),
        'end_date'   => $this->input->get('end_date', TRUE),
        'search'     => $this->input->get('search', true),
    ];

    if (!empty($filters['karantina']) && !in_array($filters['karantina'], ['H','I','T'], true)) {
        return $this->json(['success' => false, 'message' => 'Jenis karantina tidak valid']);
    }

    $page    = max((int) $this->input->get('page'), 1);
    $perPage = 10;
    $offset  = ($page - 1) * $perPage;
    $ids = $this->Nnc_model->getIds($filters, $perPage, $offset);
    $rows = [];
    if (!empty($ids)) {
        $rows = $this->Nnc_model->getByIds($ids, $filters['karantina']);
        $labels = [
            1 => 'Prohibited goods: ',
            2 => 'Problem with documentation (specify): ',
            3 => 'The goods were infected/infested/contaminated with pests (specify): ',
            4 => 'The goods do not comply with food safety (specify): ',
            5 => 'The goods do not comply with other SPS (specify): '
        ];

        foreach ($rows as &$r) { 
            $messages = [];
            for ($i = 1; $i <= 5; $i++) {
                $fld = "specify" . $i;
                if (!empty($r[$fld])) {
                    $messages[] = "<strong>" . $labels[$i] . "</strong> " . htmlspecialchars($r[$fld]);
                }
            }
            $r['nnc_reason'] = !empty($messages) ? implode('<br>', $messages) : '-';
            $r['consignment'] = "The " . ($r['consignment'] ?? '') . " lot was: " . ($r['information'] ?? '');
        }
        unset($r); 
    }

    $total = $this->Nnc_model->countAll($filters);

    return $this->json([
        'success' => true,
        'data'    => $rows,
        'meta'    => [
            'page'       => $page,
            'per_page'   => $perPage,
            'total'      => $total,
            'total_page' => (int) ceil($total / $perPage),
        ]
    ], 200);
}


    public function export_excel() 
{
    $filters = [
        'upt'        => $this->input->get('upt', TRUE),
        'karantina'  => strtoupper(trim($this->input->get('karantina', TRUE))),
        'lingkup'    => strtoupper(trim($this->input->get('lingkup', TRUE))),
        'start_date' => $this->input->get('start_date', TRUE),
        'end_date'   => $this->input->get('end_date', TRUE),
        'search'     => $this->input->get('search', true),
    ];

    $rows = $this->Nnc_model->getExportData($filters);
    if (empty($rows)) die("Data tidak ditemukan.");

    $headers = [
        'No', 'No. NNC', 'Tgl NNC', 'UPT/Satpel', 'Kepada', 'Pengirim', 'Penerima', 
        'Asal', 'Tujuan', 'Komoditas', 'HS Code', 'Volume', 'Satuan', 'Nature of Non-Compliance', 'Petugas'
    ];

    $exportData = [];
    $no = 0;
    $lastAju = null;

    $labels = [
        1 => 'Prohibited goods: ',
        2 => 'Problem with documentation (specify): ',
        3 => 'The goods were infected/infested/contaminated with pests (specify): ',
        4 => 'The goods do not comply with food safety (specify): ',
        5 => 'The goods do not comply with other SPS (specify): '
    ];

    foreach ($rows as $r) {
        $isSame = ($r['no_aju'] === $lastAju);
        if (!$isSame) { $no++; }

        $messages = [];
        foreach (range(1, 5) as $i) {
            if (!empty($r["specify$i"])) {
                $messages[] = $labels[$i] . $r["specify$i"];
            }
        }
        $nnc_reason = implode(" | ", $messages);

        $exportData[] = [
            $isSame ? '' : $no,
            $isSame ? 'Idem' : $r['nomor_penolakan'],
            $isSame ? 'Idem' : $r['tgl_penolakan'],
            $isSame ? 'Idem' : ($r['upt'] . ' - ' . $r['nama_satpel']),
            $isSame ? 'Idem' : $r['kepada'],
            $isSame ? 'Idem' : $r['nama_pengirim'],
            $isSame ? 'Idem' : $r['nama_penerima'],
            $isSame ? 'Idem' : ($r['asal'] . ' - ' . $r['kota_asal']),
            $isSame ? 'Idem' : ($r['tujuan'] . ' - ' . $r['kota_tujuan']),
            $r['komoditas'],
            $r['kode_hs'],
            $r['volume'],
            $r['satuan'],
            $isSame ? 'Idem' : $nnc_reason,
            $isSame ? 'Idem' : $r['petugas']
        ];

        $lastAju = $r['no_aju'];
    }

    $title = "LAPORAN NNC";
    $reportInfo = $this->buildReportHeader($title, $filters, $rows);

    if (ob_get_length()) ob_end_clean();
    $this->load->library('excel_handler');
    return $this->excel_handler->download("Laporan_NNC", $headers, $exportData, $reportInfo);
}
}
