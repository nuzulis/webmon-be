<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * @property CI_Input         $input
 * @property PriorNotice_model $PriorNotice_model
 * @property Excel_handler    $excel_handler
 */
class PriorNotice extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('PriorNotice_model');
        $this->load->library('excel_handler');
    }

    public function index()
    {
        $filters = [
            'upt'        => $this->input->get('upt', TRUE),
            'karantina'  => $this->input->get('karantina', TRUE),
            'start_date' => $this->input->get('start_date', TRUE),
            'end_date'   => $this->input->get('end_date', TRUE),
            'search'     => $this->input->get('search', TRUE),
            'sort_by'    => $this->input->get('sort_by', TRUE),
            'sort_order' => $this->input->get('sort_order', TRUE),
        ];

        $page     = max((int) $this->input->get('page', TRUE), 1);
        $per_page = (int) $this->input->get('per_page', TRUE) ?: 10;
        $result = $this->PriorNotice_model->fetch($filters);
        
        if (!$result['success']) {
            return $this->json([
                'success' => false,
                'message' => $result['message']
            ], 400);
        }
        $allData = $this->PriorNotice_model->filterAndSort($result['data'], $filters);
        $total      = count($allData);
        $offset     = ($page - 1) * $per_page;
        $slicedData = array_slice($allData, $offset, $per_page);

        return $this->json([
            'success' => true,
            'data'    => $this->format($slicedData),
            'meta'    => [
                'total'      => $total,
                'page'       => $page,
                'per_page'   => $per_page,
                'total_page' => (int) ceil($total / $per_page)
            ]
        ], 200);
    }

    private function format(array $rows): array
    {
        return array_map(function ($r) {
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
            'upt'        => $this->input->get('upt', TRUE),
            'karantina'  => $this->input->get('karantina', TRUE),
            'start_date' => $this->input->get('start_date', TRUE),
            'end_date'   => $this->input->get('end_date', TRUE),
            'search'     => $this->input->get('search', TRUE),
        ];

        $result = $this->PriorNotice_model->fetch($filters);
        
        if (!$result['success']) {
            return $this->json([
                'success' => false,
                'message' => $result['message']
            ], 400);
        }
        $rows = $this->PriorNotice_model->filterAndSort($result['data'], $filters);

        if (empty($rows)) {
            return $this->json([
                'success' => false,
                'message' => 'Data tidak ditemukan'
            ], 404);
        }

        $headers = [
            'No.', 'No. Prior Notice', 'Tgl Dokumen', 'Pemohon', 
            'Eksportir', 'Negara Asal', 'Importir', 
            'Komoditas', 'Volume', 'Satuan',
            'Alat Angkut (Voyage)', 'Tujuan', 'ETA (Tgl Tiba)'
        ];

        $exportData = [];
        $no = 1;
        $lastDocNbr = null;

        foreach ($rows as $r) {
            $isIdem = ($r['docnbr'] === $lastDocNbr && !empty($r['docnbr']));

            $exportData[] = [
                $isIdem ? '' : $no++,                     
                $isIdem ? 'Idem' : ($r['docnbr'] ?? '-'),
                $isIdem ? 'Idem' : ($r['tgl_doc'] ?? '-'),
                $isIdem ? 'Idem' : ($r['name'] ?? '-'),
                $isIdem ? 'Idem' : ($r['company'] ?? '-'),
                $isIdem ? 'Idem' : ($r['neg_origin'] ?? '-'),
                $isIdem ? 'Idem' : ($r['company_imp'] ?? '-'),
                $r['komoditas'] ?? '-',
                $r['volume'] ?? '0',
                $r['sat_komoditas'] ?? '-',
                $isIdem ? 'Idem' : ($r['novoyage'] ?? '-'),
                $isIdem ? 'Idem' : (($r['pelabuhan_tujuan'] ?? '') . ', ' . ($r['kota_tuju'] ?? '')),
                $isIdem ? 'Idem' : ($r['tgl_tiba'] ?? '-')
            ];

            $lastDocNbr = $r['docnbr'];
        }

        $title = "LAPORAN PRIOR NOTICE - " . strtoupper($filters['karantina'] ?? 'ALL');
        $reportInfo = $this->buildReportHeader($title, $filters, $rows);

        $this->logActivity("EXPORT EXCEL: Prior Notice {$filters['karantina']}");

        return $this->excel_handler->download("Laporan_Prior_Notice", $headers, $exportData, $reportInfo);
    }
}