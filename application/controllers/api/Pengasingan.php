<?php
defined('BASEPATH') OR exit('No direct script access allowed');

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

    public function index()
    {
        $filters = [
            'upt'        => $this->input->get('upt', TRUE),
            'karantina'  => strtoupper($this->input->get('karantina', TRUE)),
            'lingkup'    => strtoupper($this->input->get('lingkup', TRUE)),
            'start_date' => $this->input->get('start_date', TRUE),
            'end_date'   => $this->input->get('end_date', TRUE),
            'search'     => $this->input->get('search', TRUE),
            'sort_by'    => $this->input->get('sort_by', TRUE),
            'sort_order' => $this->input->get('sort_order', TRUE),
        ];

        $page    = max((int) $this->input->get('page'), 1);
        $perPage = (int) $this->input->get('per_page') ?: 10;
        $offset  = ($page - 1) * $perPage;
        $ids   = $this->Pengasingan_model->getIds($filters, $perPage, $offset);
        $rows  = $this->Pengasingan_model->getByIds($ids);
        $total = $this->Pengasingan_model->countAll($filters);

        return $this->json([
            'success' => true,
            'data'    => $rows,
            'meta'    => [
                'page'       => $page,
                'per_page'   => $perPage,
                'total'      => $total,
                'total_page' => (int) ceil($total / $perPage)
            ]
        ], 200);
    }

    public function export_excel()
    {
        $filters = [
            'upt'        => $this->input->get('upt', TRUE),
            'karantina'  => strtoupper($this->input->get('karantina', TRUE)),
            'lingkup'    => strtoupper($this->input->get('lingkup', TRUE)),
            'start_date' => $this->input->get('start_date', TRUE),
            'end_date'   => $this->input->get('end_date', TRUE),
            'search'     => $this->input->get('search', TRUE),
        ];
        $rows = $this->Pengasingan_model->getFullData($filters);

        if (empty($rows)) {
            return $this->json([
                'success' => false,
                'message' => 'Data tidak ditemukan'
            ], 404);
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
                $isIdem ? 'Idem' : $r['upt'] . ' - ' . $r['satpel'],
                $isIdem ? 'Idem' : $r['tempat'],
                $isIdem ? 'Idem' : $r['mulai'],
                $isIdem ? 'Idem' : $r['selesai'],
                $isIdem ? 'Idem' : $r['komoditas'],
                $isIdem ? 'Idem' : number_format($r['jumlah'], 3, ",", "."),
                $isIdem ? 'Idem' : $r['satuan'],
                $isIdem ? 'Idem' : $r['targets'],
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

        $title = "LAPORAN PENGASINGAN DAN PENGAMATAN " . $filters['karantina'];
        $reportInfo = $this->buildReportHeader($title, $filters, $rows);

        $this->logActivity("EXPORT EXCEL: Pengasingan {$filters['karantina']}");

        return $this->excel_handler->download("Laporan_Pengasingan", $headers, $exportData, $reportInfo);
    }
}