<?php
defined('BASEPATH') OR exit('No direct script access allowed');
ini_set('memory_limit', '512M');
/**
 * @property CI_Input         $input
 * @property Pemusnahan_model $Pemusnahan_model
 * @property Excel_handler    $excel_handler
 */
class Pemusnahan extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Pemusnahan_model');
        $this->load->library('excel_handler');
    }

    private function buildFilter(): array
    {
        return [
            'upt'        => $this->input->get('upt', TRUE),
            'karantina'  => strtoupper(trim($this->input->get('karantina', TRUE))),
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

        $data = $this->Pemusnahan_model->getAll($filter);

        return $this->json(['success' => true, 'data' => $data]);
    }

    public function export_excel()
    {
        $filter = $this->buildFilter();
        $rows   = $this->Pemusnahan_model->getFullData($filter);

        if (empty($rows)) {
            return $this->json(['success' => false, 'message' => 'Data kosong'], 404);
        }

        $headers = [
            'No.', 'Pengajuan via', 'Nomor Dokumen', 'Tgl Dokumen', 'Satpel', 'Pengirim', 'Penerima',
            'Asal', 'Tujuan', 'Alasan Pemusnahan', 'Tempat Pemusnahan', 'Metode Pemusnahan',
            'Petugas', 'Komoditas', 'Nama Tercetak', 'Kode HS', 'Volume P0', 'Volume P7', 'Satuan'
        ];

        $exportData = [];
        $lastId = null;
        $no = 1;

        foreach ($rows as $r) {
            $isIdem = ($r['id'] === $lastId);
            $exportData[] = [
                $isIdem ? '' : $no++,
                isset($r['tssm_id']) ? 'SSM' : 'PTK',
                $r['nomor'],
                $r['tgl_p7'],
                $r['nama_upt'] . ' - ' . $r['nama_satpel'],
                $r['nama_pengirim'],
                $r['nama_penerima'],
                $r['negara_asal'] . ' - ' . $r['kota_kab_asal'],
                $r['negara_tujuan'] . ' - ' . $r['kota_kab_tujuan'],
                $r['alasan_string'],
                $r['tempat'],
                $r['metode'],
                $r['petugas'],
                $r['komoditas'],
                $r['tercetak'],
                $r['hs'],
                (float) ($r['volume'] ?? 0),
                (float) ($r['p7'] ?? 0),
                $r['satuan'],
            ];
            $lastId = $r['id'];
        }

        $title      = "LAPORAN PEMUSNAHAN " . $filter['karantina'];
        $reportInfo = $this->buildReportHeader($title, $filter, $rows);

        $this->logActivity("EXPORT EXCEL: Pemusnahan {$filter['karantina']}");

        if (ob_get_length()) ob_end_clean();

        return $this->excel_handler->download("Laporan_Pemusnahan", $headers, $exportData, $reportInfo);
    }
}
