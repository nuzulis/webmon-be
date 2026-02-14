<?php
defined('BASEPATH') OR exit('No direct script access allowed');

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

    public function index()
    {
        $filters = [
            'upt'        => $this->input->get('upt', TRUE),
            'karantina'  => strtoupper(trim($this->input->get('karantina', TRUE))),
            'lingkup'    => $this->input->get('lingkup', TRUE),
            'start_date' => $this->input->get('start_date', TRUE),
            'end_date'   => $this->input->get('end_date', TRUE),
            'search'     => $this->input->get('search', TRUE),
            'sort_by'    => $this->input->get('sort_by', TRUE),
            'sort_order' => $this->input->get('sort_order', TRUE),
        ];

        if (!empty($filters['karantina']) && !in_array($filters['karantina'], ['H','I','T'], true)) {
            return $this->json([
                'success' => false,
                'message' => 'Karantina tidak valid'
            ], 400);
        }

        $page    = max((int) $this->input->get('page'), 1);
        $perPage = (int) $this->input->get('per_page') ?: 10;
        $offset  = ($page - 1) * $perPage;
        $ids   = $this->Pemusnahan_model->getIds($filters, $perPage, $offset);
        $rows  = $this->Pemusnahan_model->getByIds($ids);
        $total = $this->Pemusnahan_model->countAll($filters);

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
            'karantina'  => strtoupper($this->input->get('karantina', TRUE)),
            'start_date' => $this->input->get('start_date', TRUE),
            'end_date'   => $this->input->get('end_date', TRUE),
            'search'     => $this->input->get('search', TRUE),
        ];
        $rows = $this->Pemusnahan_model->getFullData($filters);

        if (empty($rows)) {
            return $this->json([
                'success' => false,
                'message' => 'Data kosong'
            ], 404);
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
                $isIdem ? 'Idem' : (isset($r['tssm_id']) ? 'SSM' : 'PTK'),
                $isIdem ? 'Idem' : $r['nomor'],
                $isIdem ? 'Idem' : $r['tgl_p7'],
                $isIdem ? 'Idem' : $r['nama_upt'] . ' - ' . $r['nama_satpel'],
                $isIdem ? 'Idem' : $r['nama_pengirim'],
                $isIdem ? 'Idem' : $r['nama_penerima'],
                $isIdem ? 'Idem' : $r['negara_asal'] . ' - ' . $r['kota_kab_asal'],
                $isIdem ? 'Idem' : $r['negara_tujuan'] . ' - ' . $r['kota_kab_tujuan'],
                $isIdem ? 'Idem' : $r['alasan_string'],
                $isIdem ? 'Idem' : $r['tempat'],
                $isIdem ? 'Idem' : $r['metode'],
                $isIdem ? 'Idem' : $r['petugas'],
                $r['komoditas'],
                $r['tercetak'],
                $r['hs'],
                $r['volume'],
                $r['p7'],
                $r['satuan']
            ];
            $lastId = $r['id'];
        }

        $title = "LAPORAN PEMUSNAHAN " . $filters['karantina'];
        $reportInfo = $this->buildReportHeader($title, $filters, $rows);

        $this->logActivity("EXPORT EXCEL: Pemusnahan {$filters['karantina']}");

        return $this->excel_handler->download("Laporan_Pemusnahan", $headers, $exportData, $reportInfo);
    }
}