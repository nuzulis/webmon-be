<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * @property CI_Input        $input
 * @property Penugasan_model $Penugasan_model
 * @property Excel_handler   $excel_handler
 */
class Penugasan extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Penugasan_model');
        $this->load->library('excel_handler');
    }

    public function index()
    {
        $filters = [
            'upt'        => $this->input->get('upt', TRUE),
            'karantina'  => strtoupper(trim($this->input->get('karantina', TRUE))),
            'start_date' => $this->input->get('start_date', TRUE),
            'end_date'   => $this->input->get('end_date', TRUE),
            'petugas'    => $this->input->get('petugas', TRUE),
            'search'     => $this->input->get('search', TRUE),
            'sort_by'    => $this->input->get('sort_by', TRUE),
            'sort_order' => $this->input->get('sort_order', TRUE),
        ];

        $page    = max((int) $this->input->get('page'), 1);
        $perPage = (int) $this->input->get('per_page') ?: 10;
        $offset  = ($page - 1) * $perPage;
        $ids   = $this->Penugasan_model->getIds($filters, $perPage, $offset);
        $data  = $this->Penugasan_model->getByIds($ids);
        $total = $this->Penugasan_model->countAll($filters);

        return $this->json([
            'success' => true,
            'data'    => $data,
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
            'karantina'  => strtoupper(trim($this->input->get('karantina', TRUE))),
            'petugas'    => $this->input->get('petugas', TRUE),
            'start_date' => $this->input->get('start_date', TRUE),
            'end_date'   => $this->input->get('end_date', TRUE),
            'search'     => $this->input->get('search', TRUE),
        ];
        $rows = $this->Penugasan_model->getFullData($filters);

        if (empty($rows)) {
            return $this->json([
                'success' => false,
                'message' => 'Data tidak ditemukan'
            ], 404);
        }

        $headers = [
            'No.', 'Nomor Surtug', 'Tgl Surtug',
            'No Permohonan', 'Tgl Permohonan',
            'UPT', 'Satpel',
            'Nama Petugas', 'NIP Petugas',
            'Jenis Tugas',
            'Negara Asal', 'Daerah Asal',
            'Negara Tujuan', 'Daerah Tujuan',
            'Komoditas', 'Nama Tercetak', 'HS Code',
            'Vol P1', 'Vol P2', 'Vol P3', 'Vol P4',
            'Vol P5', 'Vol P6', 'Vol P7', 'Vol P8',
            'Satuan'
        ];

        $exportData = [];
        $no = 1;
        $lastSurtug = null;

        foreach ($rows as $r) {
            $isIdem = ($r['nomor_surtug'] === $lastSurtug);

            $exportData[] = [
                $isIdem ? '' : $no++,
                $isIdem ? 'Idem' : $r['nomor_surtug'],
                $isIdem ? 'Idem' : $r['tgl_surtug'],
                $isIdem ? 'Idem' : $r['no_dok_permohonan'],
                $isIdem ? 'Idem' : $r['tgl_dok_permohonan'],
                $isIdem ? 'Idem' : $r['upt'],
                $isIdem ? 'Idem' : $r['satpel'],
                $r['nama_petugas'],
                $r['nip_petugas'],
                $r['jenis_tugas'],
                $r['negara_asal'], $r['daerah_asal'],
                $r['negara_tujuan'], $r['daerah_tujuan'],
                $r['nama_komoditas'], 
                $r['nama_umum_tercetak'], 
                $r['kode_hs'],
                $r['volumeP1'], $r['volumeP2'], $r['volumeP3'], $r['volumeP4'],
                $r['volumeP5'], $r['volumeP6'], $r['volumeP7'], $r['volumeP8'],
                $r['nama_satuan']
            ];

            $lastSurtug = $r['nomor_surtug'];
        }

        $title = "LAPORAN PENUGASAN PETUGAS KARANTINA";
        $reportInfo = $this->buildReportHeader($title, $filters, $rows);

        $this->logActivity("EXPORT EXCEL: Penugasan");

        return $this->excel_handler->download(
            "Laporan_Penugasan_" . date('Ymd'),
            $headers,
            $exportData,
            $reportInfo
        );
    }
}