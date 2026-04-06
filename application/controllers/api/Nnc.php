<?php
defined('BASEPATH') OR exit('No direct script access allowed');
ini_set('memory_limit', '512M');
/**
 * @property CI_Input      $input
 * @property Nnc_model     $Nnc_model
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

    private function buildFilter(): array
    {
        return [
            'upt'        => $this->input->get('upt', TRUE),
            'karantina'  => strtoupper($this->input->get('karantina', TRUE)),
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

        $data = $this->Nnc_model->getAll($filter);

        return $this->json(['success' => true, 'data' => $data]);
    }

    public function export_excel()
    {
        $filter = $this->buildFilter();

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
            $isIdem = ($r['no_aju'] === $lastAju);
            if (!$isIdem) { $no++; }
            $exportData[] = [
                $isIdem ? '' : $no,
                $r['nomor_penolakan'] ?? '-',
                $r['tgl_penolakan']   ?? '-',
                $r['upt_full']        ?? '-',
                $r['kepada']          ?? '-',
                $r['nama_pengirim']   ?? '-',
                $r['nama_penerima']   ?? '-',
                trim(($r['asal'] ?? '') . ' - ' . ($r['kota_asal'] ?? ''), ' -'),
                trim(($r['tujuan'] ?? '') . ' - ' . ($r['kota_tujuan'] ?? ''), ' -'),
                $r['komoditas'] ?? '-',
                $r['kode_hs']   ?? '-',
                (float) ($r['volume'] ?? 0),
                $r['satuan']     ?? '-',
                $r['nnc_reason'] ?? '-',
                $r['petugas']    ?? '-',
            ];
            $lastAju = $r['no_aju'];
        }

        $title      = "LAPORAN NNC";
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
