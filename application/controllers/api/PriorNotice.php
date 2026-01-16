<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * @property CI_Input $input
 * @property CI_Output $output
 * @property PriorNotice_model $PriorNotice_model
 * @property Excel_handler $excel_handler
 */
class PriorNotice extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('PriorNotice_model');
        $this->load->helper('jwt');
        $this->load->library('excel_handler');
    }

    public function index()
    {
        // Sekarang $this->PriorNotice_model tidak akan bergaris merah lagi
        $filters = [
            'upt'        => $this->input->get('upt', true),
            'karantina'  => $this->input->get('karantina', true),
            'start_date' => $this->input->get('start_date', true),
            'end_date'   => $this->input->get('end_date', true),
        ];

        $result = $this->PriorNotice_model->fetch($filters);
        
        if (!$result['success']) {
            return $this->json(400, $result);
        }

        return $this->json(200, [
            'success' => true,
            'data'    => $this->format($result['data'])
        ]);
    }

    private function format(array $rows): array
    {
        return array_map(function ($r) {
            // Gabungkan string untuk tampilan tabel yang ringkas
            $komoditasFull = trim(($r['komoditas'] ?? '') . ' ' . ($r['volume'] ?? '') . ' ' . ($r['sat_komoditas'] ?? ''));
            $tujuanFull    = trim(($r['pelabuhan_tujuan'] ?? '') . ', ' . ($r['kota_tuju'] ?? ''));

            return [
                'docnbr'      => $r['docnbr'] ?? '-',
                'applicant'   => $r['name'] ?? '-',
                'exporter'    => $r['company'] ?? '-',
                'origin'      => $r['neg_origin'] ?? '-',
                'importer'    => $r['company_imp'] ?? '-',
                'commodity'   => $komoditasFull ?: '-',
                'voyage'      => $r['novoyage'] ?? '-',
                'destination' => $tujuanFull ?: '-',
                'eta'         => $r['tgl_tiba'] ?? '-',
                'doc_date'    => $r['tgl_doc'] ?? '-',
                // Link Dokumen
                'links' => [
                    'pdf'  => !empty($r['docnbr']) ? 'https://api3.karantinaindonesia.go.id/rest-prior/printPdf/doc/' . base64_encode($r['docnbr']) : null,
                    'pchc' => !empty($r['filePathPCHC']) ? 'https://api3.karantinaindonesia.go.id/rest-prior/' . $r['filePathPCHC'] : null,
                    'dok'  => !empty($r['filePathDok']) ? 'https://api3.karantinaindonesia.go.id/rest-prior/' . $r['filePathDok'] : null,
                ]
            ];
        }, $rows);
    }

    public function export_excel()
{
    $filters = [
        'upt'        => $this->input->get('upt', true),
        'karantina'  => $this->input->get('karantina', true),
        'start_date' => $this->input->get('start_date', true),
        'end_date'   => $this->input->get('end_date', true),
    ];

    $result = $this->PriorNotice_model->fetch($filters);
    
    if (!$result['success']) {
        die($result['message']);
    }

    $rows = $result['data'];

    /* =============================
     * 1. DEFINE HEADERS
     * ============================= */
    $headers = [
        'No', 'No. Prior Notice', 'Tgl Dokumen', 'Pemohon', 
        'Eksportir', 'Negara Asal', 'Importir', 
        'Komoditas', 'Volume', 'Satuan',
        'Alat Angkut (Voyage)', 'Tujuan', 'ETA (Tgl Tiba)'
    ];

    /* =============================
     * 2. MAPPING DATA (IDEM LOGIC)
     * ============================= */
    $exportData = [];
    $no = 1;
    $lastDocNbr = null;

    foreach ($rows as $r) {
        // Logika IDEM berdasarkan Nomor Dokumen (docnbr)
        $isIdem = ($r['docnbr'] === $lastDocNbr && !empty($r['docnbr']));

        $exportData[] = [
            $isIdem ? '' : $no++,                       // No Urut
            $isIdem ? 'Idem' : ($r['docnbr'] ?? '-'),   // No Prior Notice
            $isIdem ? '' : ($r['tgl_doc'] ?? '-'),
            $isIdem ? '' : ($r['name'] ?? '-'),
            $isIdem ? '' : ($r['company'] ?? '-'),
            $isIdem ? '' : ($r['neg_origin'] ?? '-'),
            $isIdem ? '' : ($r['company_imp'] ?? '-'),
            // Data Komoditas (Selalu Muncul per baris)
            $r['komoditas'] ?? '-',
            $r['volume'] ?? '0',
            $r['sat_komoditas'] ?? '-',
            // Data Logistik
            $isIdem ? '' : ($r['novoyage'] ?? '-'),
            $isIdem ? '' : (($r['pelabuhan_tujuan'] ?? '') . ', ' . ($r['kota_tuju'] ?? '')),
            $isIdem ? '' : ($r['tgl_tiba'] ?? '-')
        ];

        $lastDocNbr = $r['docnbr'];
    }


    $title = "LAPORAN PRIOR NOTICE - " . strtoupper($filters['karantina'] ?? 'ALL');
    $reportInfo = $this->buildReportHeader($title, $filters);
    return $this->excel_handler->download("Laporan_Prior_Notice", $headers, $exportData, $reportInfo);
}

    private function json($status, $data)
    {
        return $this->output
            ->set_status_header($status)
            ->set_content_type('application/json', 'utf-8')
            ->set_output(json_encode($data, JSON_UNESCAPED_UNICODE));
    }
}