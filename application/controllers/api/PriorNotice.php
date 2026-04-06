<?php
defined('BASEPATH') OR exit('No direct script access allowed');
ini_set('memory_limit', '512M');
/**
 * @property CI_Input          $input
 * @property PriorNotice_model $PriorNotice_model
 * @property Excel_handler     $excel_handler
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
        ];

        $result = $this->PriorNotice_model->fetch($filters);

        if (!$result['success']) {
            return $this->json([
                'success' => false,
                'message' => $result['message']
            ], 400);
        }

        return $this->json([
            'success' => true,
            'data'    => $this->format($result['data']),
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
        ];

        $result = $this->PriorNotice_model->fetch($filters);

        if (!$result['success']) {
            return $this->json([
                'success' => false,
                'message' => $result['message']
            ], 400);
        }

        $rows = $result['data'];

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
        $no         = 1;
        $lastDocNbr = null;

        foreach ($rows as $r) {
            $isIdem = ($r['docnbr'] === $lastDocNbr && !empty($r['docnbr']));

            $exportData[] = [
                $isIdem ? '' : $no++,
                ($r['docnbr'] ?? '-'),
                ($r['tgl_doc'] ?? '-'),
                ($r['name'] ?? '-'),
                ($r['company'] ?? '-'),
                ($r['neg_origin'] ?? '-'),
                ($r['company_imp'] ?? '-'),
                $r['komoditas'] ?? '-',
                (float) ($r['volume'] ?? 0),
                $r['sat_komoditas'] ?? '-',
                ($r['novoyage'] ?? '-'),
                (($r['pelabuhan_tujuan'] ?? '') . ', ' . ($r['kota_tuju'] ?? '')),
                ($r['tgl_tiba'] ?? '-')
            ];

            $lastDocNbr = $r['docnbr'];
        }

        $title      = "LAPORAN PRIOR NOTICE - " . strtoupper($filters['karantina'] ?? 'ALL');
        $reportInfo = $this->buildReportHeader($title, $filters, $rows);

        $this->logActivity("EXPORT EXCEL: Prior Notice {$filters['karantina']}");

        return $this->excel_handler->download("Laporan_Prior_Notice", $headers, $exportData, $reportInfo);
    }
}
