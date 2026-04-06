<?php
defined('BASEPATH') OR exit('No direct script access allowed');
ini_set('memory_limit', '512M');
/**
 * @property CI_Input            $input
 * @property PeriksaFisik_model  $PeriksaFisik_model
 * @property Excel_handler       $excel_handler
 */
class PeriksaFisik extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('PeriksaFisik_model');
        $this->load->library('excel_handler');
    }

    private function buildFilter(): array
    {
        return [
            'upt'        => $this->input->get('upt', TRUE),
            'karantina'  => strtoupper(trim($this->input->get('karantina', TRUE) ?? '')),
            'lingkup'    => $this->input->get('lingkup', TRUE) ?: $this->input->get('permohonan', TRUE),
            'start_date' => $this->input->get('start_date', TRUE),
            'end_date'   => $this->input->get('end_date', TRUE),
        ];
    }

    public function index()
    {
        $filter = $this->buildFilter();

        if (!in_array($filter['karantina'], ['H', 'I', 'T'], true)) {
            return $this->json(['success' => false, 'message' => 'Parameter karantina tidak valid'], 400);
        }

        $data = $this->PeriksaFisik_model->getAll($filter);

        return $this->json(['success' => true, 'data' => $data]);
    }

    public function export_excel()
    {
        $filter = $this->buildFilter();
        $rows   = $this->PeriksaFisik_model->getFullData($filter);

        if (empty($rows)) {
            return $this->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }

        $headers = [
            'No.', 'No. Aju', 'No. Dokumen', 'Tgl Dokumen', 'No. P1B', 'Tgl P1B',
            'UPT', 'Satpel', 'Pengirim', 'Penerima',
            'Asal (Negara - Kota)', 'Tujuan (Negara - Kota)',
            'Komoditas', 'HS Code', 'Volume', 'Satuan'
        ];

        $exportData = [];
        $no     = 1;
        $lastId = null;

        foreach ($rows as $r) {
            $isIdem     = ($r['id'] === $lastId);
            $asalFull   = trim(($r['asal'] ?? '') . ' - ' . ($r['kota_asal'] ?? ''), ' -');
            $tujuanFull = trim(($r['tujuan'] ?? '') . ' - ' . ($r['kota_tujuan'] ?? ''), ' -');

            $exportData[] = [
                $isIdem ? '' : $no++,
                $r['no_aju']             ?? '-',
                $r['no_dok_permohonan']  ?? '-',
                $r['tgl_dok_permohonan'] ?? '-',
                $r['no_p1b']             ?? '-',
                $r['tgl_p1b']            ?? '-',
                $r['upt']                ?? '-',
                $r['nama_satpel']        ?? '-',
                $r['nama_pengirim']      ?? '-',
                $r['nama_penerima']      ?? '-',
                $asalFull  ?: '-',
                $tujuanFull ?: '-',
                $r['nama_umum_tercetak'] ?? '-',
                $r['hs']                 ?? '-',
                (float) ($r['volume']    ?? 0),
                $r['satuan']             ?? '-',
            ];

            $lastId = $r['id'];
        }

        $title      = "LAPORAN PEMERIKSAAN FISIK & KESEHATAN (" . ($filter['karantina'] ?: 'ALL') . ")";
        $reportInfo = $this->buildReportHeader($title, $filter, $rows);

        $this->logActivity("EXPORT EXCEL: Pemeriksaan Fisik {$filter['karantina']}");

        if (ob_get_length()) ob_end_clean();

        return $this->excel_handler->download(
            "Laporan_PeriksaFisik_" . date('Ymd'),
            $headers,
            $exportData,
            $reportInfo
        );
    }
}
