<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * @property CI_Input           $input
 * @property PeriksaAdmin_model $PeriksaAdmin_model
 * @property Excel_handler      $excel_handler
 */
class PeriksaAdmin extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('PeriksaAdmin_model');
        $this->load->library('excel_handler');
    }

    public function index()
    {
        $filters = [
            'upt'        => $this->input->get('upt', TRUE),
            'karantina'  => $this->input->get('karantina', TRUE),
            'permohonan' => $this->input->get('permohonan', TRUE),
            'lingkup'    => $this->input->get('lingkup', TRUE),
            'start_date' => $this->input->get('start_date', TRUE),
            'end_date'   => $this->input->get('end_date', TRUE),
            'search'     => $this->input->get('search', TRUE),
            'sort_by'    => $this->input->get('sort_by', TRUE),
            'sort_order' => $this->input->get('sort_order', TRUE),
        ];

        $page    = max((int) $this->input->get('page'), 1);
        $perPage = (int) $this->input->get('per_page') ?: 10;
        $offset  = ($page - 1) * $perPage;
        $ids   = $this->PeriksaAdmin_model->getIds($filters, $perPage, $offset);
        $data  = $this->PeriksaAdmin_model->getByIds($ids);
        $total = $this->PeriksaAdmin_model->countAll($filters);

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
            'karantina'  => $this->input->get('karantina', TRUE),
            'permohonan' => $this->input->get('permohonan', TRUE),
            'start_date' => $this->input->get('start_date', TRUE),
            'end_date'   => $this->input->get('end_date', TRUE),
            'search'     => $this->input->get('search', TRUE),
        ];
        $rows = $this->PeriksaAdmin_model->getFullData($filters);

        if (empty($rows)) {
            return $this->json([
                'success' => false,
                'message' => 'Data tidak ditemukan'
            ], 404);
        }

        $headers = [
            'No.', 'No. Aju', 'No. Dokumen', 'Tgl Dokumen',
            'No. P1/P1A', 'Tgl P1/P1A',
            'UPT', 'Satpel',
            'Pengirim', 'Penerima',
            'Negara Asal', 'Kota Asal',
            'Negara Tujuan', 'Kota Tujuan',
            'Nama Tercetak', 'HS Code', 'Volume', 'Satuan'
        ];

        $exportData = [];
        $no = 1;
        $lastId = null;

        foreach ($rows as $r) {
            $isIdem = ($r['id'] === $lastId);

            $exportData[] = [
                $isIdem ? '' : $no++,
                $isIdem ? 'Idem' : ($r['no_aju'] ?? ''),
                $isIdem ? 'Idem' : ($r['no_dok_permohonan'] ?? ''),
                $isIdem ? 'Idem' : ($r['tgl_dok_permohonan'] ?? ''),
                $isIdem ? 'Idem' : ($r['no_p1a'] ?? ''),
                $isIdem ? 'Idem' : ($r['tgl_p1a'] ?? ''),
                $isIdem ? 'Idem' : ($r['upt'] ?? ''),
                $isIdem ? 'Idem' : ($r['nama_satpel'] ?? ''),
                $isIdem ? 'Idem' : ($r['nama_pengirim'] ?? ''),
                $isIdem ? 'Idem' : ($r['nama_penerima'] ?? ''),
                $isIdem ? 'Idem' : ($r['asal'] ?? ''),
                $isIdem ? 'Idem' : ($r['kota_asal'] ?? ''),
                $isIdem ? 'Idem' : ($r['tujuan'] ?? ''),
                $isIdem ? 'Idem' : ($r['kota_tujuan'] ?? ''),
                $r['tercetak'] ?? '',
                $r['hs'] ?? '',
                $r['volume'] ?? '',
                $r['satuan'] ?? ''
            ];

            $lastId = $r['id'];
        }

        $title = "LAPORAN PEMERIKSAAN ADMINISTRASI";
        $reportInfo = $this->buildReportHeader($title, $filters, $rows);

        $this->logActivity("EXPORT EXCEL: Pemeriksaan Administrasi");

        return $this->excel_handler->download(
            "Laporan_Periksa_Admin_" . date('Ymd'),
            $headers,
            $exportData,
            $reportInfo
        );
    }
}