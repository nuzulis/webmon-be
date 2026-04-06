<?php
defined('BASEPATH') OR exit('No direct script access allowed');
ini_set('memory_limit', '512M');
/**
 * @property CI_Input              $input
 * @property PeriksaLapangan_model $PeriksaLapangan_model
 * @property Excel_handler         $excel_handler
 */
class PeriksaLapangan extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('PeriksaLapangan_model');
        $this->load->library('excel_handler');
    }

    private function buildFilter(): array
    {
        return [
            'upt'        => $this->input->get('upt', TRUE),
            'karantina'  => strtoupper(trim($this->input->get('karantina', TRUE) ?? '')),
            'lingkup'    => $this->input->get('lingkup', TRUE),
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

        $data = $this->PeriksaLapangan_model->getAll($filter);

        return $this->json(['success' => true, 'data' => $data]);
    }

    public function detail($id)
    {
        $data = $this->PeriksaLapangan_model->getDetailFinal($id);
        if (!$data) {
            return $this->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }
        return $this->json(['success' => true, 'data' => $data]);
    }

    public function export_excel()
    {
        $filter = $this->buildFilter();
        $this->applyScope($filter);

        $rows = $this->PeriksaLapangan_model->getFullData($filter);

        if (empty($rows)) {
            return $this->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }

        $headers = [
            'No', 'UPT / Satpel', 'No Permohonan', 'Tgl Permohonan',
            'No Surtug', 'Tgl Surtug', 'Petugas', 'Komoditas', 'Target', 'Metode',
            'Mulai', 'Selesai', 'Durasi', 'Status', 'Keterangan Log'
        ];

        $exportData = [];
        $no     = 1;
        $lastId = null;

        foreach ($rows as $r) {
            $isIdem = ($r['id'] === $lastId);

            $exportData[] = [
                $isIdem ? '' : $no++,
                ($r['upt_nama'] ?? '-') . ' / ' . ($r['nama_satpel'] ?? '-'),
                $r['no_dok_permohonan']  ?? '',
                $r['tgl_dok_permohonan'] ?? '',
                $r['no_surtug']          ?? '',
                $r['tgl_surtug']         ?? '',
                ($r['nama_petugas'] ?? '') . ' (' . ($r['nip_petugas'] ?? '') . ')',
                $r['nama_komoditas']     ?? '-',
                $r['target']             ?? '',
                $r['metode']             ?? '',
                $r['mulai']              ?? '',
                $r['selesai']            ?? '',
                ($r['durasi_menit'] ?? 0) . ' Menit',
                !empty($r['selesai']) ? 'SELESAI' : 'PROSES',
                $r['keterangan_log']     ?? '',
            ];

            $lastId = $r['id'];
        }

        $title      = "LAPORAN PEMERIKSAAN LAPANGAN (OFFICER)";
        $reportInfo = $this->buildReportHeader($title, $filter, $rows);

        $this->logActivity("EXPORT EXCEL: Periksa Lapangan {$filter['karantina']}");

        if (ob_get_length()) ob_end_clean();

        return $this->excel_handler->download(
            "Laporan_PeriksaLapangan_" . date('Ymd_His'),
            $headers,
            $exportData,
            $reportInfo
        );
    }
}
