<?php
defined('BASEPATH') OR exit('No direct script access allowed');
ini_set('memory_limit', '512M');
/**
 * @property CI_Input        $input
 * @property Penolakan_model $Penolakan_model
 * @property Excel_handler   $excel_handler
 */
class Penolakan extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Penolakan_model');
        $this->load->library('excel_handler');
    }

    private function buildFilter(): array
    {
        return [
            'upt'        => $this->input->get('upt', TRUE),
            'karantina'  => strtoupper($this->input->get('karantina', TRUE) ?? 'H'),
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

        $data = $this->Penolakan_model->getAll($filter);

        return $this->json(['success' => true, 'data' => $data]);
    }

    public function export_excel()
    {
        $filter = $this->buildFilter();
        $rows   = $this->Penolakan_model->getFullData($filter);

        if (empty($rows)) {
            return $this->json(['success' => false, 'message' => 'Data kosong'], 404);
        }

        $headers = [
            'No.', 'No Dokumen', 'Tgl Dokumen', 'No P6', 'Tgl P6',
            'Satpel', 'Pengirim', 'Penerima', 'Asal - Kota', 'Tujuan - Kota',
            'Alasan Penolakan', 'Petugas', 'Komoditas', 'HS', 'Volume', 'Satuan'
        ];

        $data   = [];
        $no     = 1;
        $lastId = null;

        foreach ($rows as $r) {
            $idem = ($r['id'] === $lastId);

            $data[] = [
                $idem ? '' : $no++,
                $r['no_dok_permohonan']  ?? '',
                $r['tgl_dok_permohonan'] ?? '',
                $r['nomor_penolakan']    ?? '',
                $r['tgl_penolakan']      ?? '',
                ($r['upt'] ?? '') . ' - ' . ($r['nama_satpel'] ?? ''),
                $r['nama_pengirim']  ?? '',
                $r['nama_penerima']  ?? '',
                $r['asal'] . ' - ' . $r['kota_asal'],
                $r['tujuan'] . ' - ' . $r['kota_tujuan'],
                $r['alasan_string']  ?? '',
                $r['petugas']        ?? '',
                $r['komoditas']      ?? '',
                $r['hs']             ?? '',
                (float) ($r['volume'] ?? 0),
                $r['satuan']         ?? '',
            ];

            $lastId = $r['id'];
        }

        $title = "LAPORAN PENOLAKAN {$filter['karantina']}";
        $info  = $this->buildReportHeader($title, $filter, $rows);

        $this->logActivity("EXPORT EXCEL: Penolakan {$filter['karantina']}");

        if (ob_get_length()) ob_end_clean();

        return $this->excel_handler->download('Laporan_Penolakan', $headers, $data, $info);
    }
}
