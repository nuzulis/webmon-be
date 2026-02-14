<?php
defined('BASEPATH') OR exit('No direct script access allowed');

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

    public function index()
    {
        $filters = [
            'upt'        => $this->input->get('upt', TRUE),
            'karantina'  => strtoupper($this->input->get('karantina', TRUE)),
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
        $ids   = $this->Penolakan_model->getIds($filters, $perPage, $offset);
        $rows  = $this->Penolakan_model->getByIds($ids);
        $total = $this->Penolakan_model->countAll($filters);

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
            'lingkup'    => $this->input->get('lingkup', TRUE),
            'start_date' => $this->input->get('start_date', TRUE),
            'end_date'   => $this->input->get('end_date', TRUE),
            'search'     => $this->input->get('search', TRUE),
        ];
        $rows = $this->Penolakan_model->getFullData($filters);

        if (empty($rows)) {
            return $this->json([
                'success' => false,
                'message' => 'Data kosong'
            ], 404);
        }

        $headers = [
            'No.', 'No Dokumen', 'Tgl Dokumen', 'No P6', 'Tgl P6',
            'Satpel', 'Pengirim', 'Penerima', 'Alasan Penolakan',
            'Petugas', 'Komoditas', 'HS', 'Volume', 'Satuan'
        ];

        $data   = [];
        $no     = 1;
        $lastId = null;

        foreach ($rows as $r) {
            $idem = ($r['id'] === $lastId);

            $data[] = [
                $idem ? '' : $no++,
                $idem ? 'Idem' : ($r['no_dok_permohonan'] ?? ''),
                $idem ? 'Idem' : ($r['tgl_dok_permohonan'] ?? ''),
                $idem ? 'Idem' : ($r['nomor_penolakan'] ?? ''),
                $idem ? 'Idem' : ($r['tgl_penolakan'] ?? ''),
                $idem ? 'Idem' : (($r['upt'] ?? '') . ' - ' . ($r['nama_satpel'] ?? '')),
                $idem ? 'Idem' : ($r['nama_pengirim'] ?? ''),
                $idem ? 'Idem' : ($r['nama_penerima'] ?? ''),
                $idem ? 'Idem' : ($r['alasan_string'] ?? ''),
                $idem ? 'Idem' : ($r['petugas'] ?? ''),
                $r['komoditas'] ?? '',
                $r['hs']        ?? '',
                $r['volume']    ?? '',
                $r['satuan']    ?? '',
            ];

            $lastId = $r['id'];
        }

        $title = "LAPORAN PENOLAKAN {$filters['karantina']}";
        $info = $this->buildReportHeader($title, $filters, $rows);

        $this->logActivity("EXPORT EXCEL: Penolakan {$filters['karantina']}");

        return $this->excel_handler->download('Laporan_Penolakan', $headers, $data, $info);
    }
}