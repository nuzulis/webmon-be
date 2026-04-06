<?php
defined('BASEPATH') OR exit('No direct script access allowed');
ini_set('memory_limit', '512M');
/**
 * @property CI_Input           $input
 * @property Pengasingan_model  $Pengasingan_model
 * @property Excel_handler      $excel_handler
 */
class Pengasingan extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Pengasingan_model');
        $this->load->library('excel_handler');
    }

    private function buildFilter(): array
    {
        return [
            'upt'        => $this->input->get('upt', TRUE),
            'karantina'  => strtoupper($this->input->get('karantina', TRUE) ?? 'H'),
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

        $data = $this->Pengasingan_model->getAll($filter);

        return $this->json(['success' => true, 'data' => $data]);
    }

    public function export_excel()
    {
        $filter = $this->buildFilter();
        $rows   = $this->Pengasingan_model->getFullData($filter);

        if (empty($rows)) {
            return $this->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }

        $headers = [
            'No.', 'UPT', 'Nama Tempat', 'Tgl Mulai', 'Tgl Selesai',
            'Komoditas', 'Jumlah', 'Satuan', 'Target',
            'No. Dokumen', 'Tgl Dokumen', 'Pengamatan Ke-', 'Tgl Pengamatan',
            'Gejala', 'Rekomendasi', 'Rekomendasi Lanjut', 'Kondisi',
            'Petugas 1', 'Petugas 2', 'Penginput', 'Tgl Input'
        ];

        $exportData = [];
        $lastId = null;
        $no = 1;

        foreach ($rows as $r) {
            $isIdem = ($r['id'] === $lastId);

            $kondisi = [];
            if ($r['bus'] > 0) $kondisi[] = "busuk " . $r['bus'];
            if ($r['rus'] > 0) $kondisi[] = "rusak " . $r['rus'];
            if ($r['dead'] > 0) $kondisi[] = "mati " . $r['dead'];
            $hasil_kondisi = implode(", ", $kondisi);

            $exportData[] = [
                $isIdem ? '' : $no++,
                $r['upt'] . ' - ' . $r['satpel'],
                $r['tempat'],
                $r['mulai'],
                $r['selesai'],
                $r['komoditas'],
                number_format($r['jumlah'], 3, ",", "."),
                $r['satuan'],
                $r['targets'],
                $r['nomor_ngasmat'],
                $r['tgl_tk2'],
                $r['pengamatan'],
                $r['tgl_ngasmat'],
                $r['tanda'],
                $r['rekom'],
                $r['rekom_lanjut'],
                $hasil_kondisi,
                $r['ttd'],
                $r['ttd1'],
                $r['inputer'],
                $r['tgl_input']
            ];

            $lastId = $r['id'];
        }

        $title      = "LAPORAN PENGASINGAN DAN PENGAMATAN " . $filter['karantina'];
        $reportInfo = $this->buildReportHeader($title, $filter, $rows);

        $this->logActivity("EXPORT EXCEL: Pengasingan {$filter['karantina']}");

        if (ob_get_length()) ob_end_clean();

        return $this->excel_handler->download("Laporan_Pengasingan", $headers, $exportData, $reportInfo);
    }
}
