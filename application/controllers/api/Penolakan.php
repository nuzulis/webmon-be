<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * @property Penolakan_model $Penolakan_model
 * @property Excel_handler  $excel_handler
 */
class Penolakan extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Penolakan_model');
        $this->load->helper(['jwt']);
        $this->load->library('excel_handler');
    }

    /* =====================================================
     * LIST DATA (PAGINATION)
     * ===================================================== */
    public function index()
    {
        /* ================= JWT ================= */
        $auth = $this->input->get_request_header('Authorization', TRUE);
        if (!$auth || !preg_match('/Bearer\s+(\S+)/', $auth)) {
            return $this->json(401);
        }

        /* ================= FILTER ================= */
        $filters = [
            'upt'        => $this->input->get('upt'),
            'karantina'  => strtoupper($this->input->get('karantina')),
            'permohonan' => strtoupper($this->input->get('permohonan')),
            'start_date' => $this->input->get('start_date'),
            'end_date'   => $this->input->get('end_date'),
            'search'     => $this->input->get('search', true),
        ];

        $page    = max((int) $this->input->get('page'), 1);
        $perPage = (int) $this->input->get('per_page') ?: 10;
        $offset  = ($page - 1) * $perPage;

        /* ================= DATA (1 QUERY CEPAT) ================= */
        $rows  = $this->Penolakan_model->getList($filters, $perPage, $offset);
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

    /* =====================================================
     * EXPORT EXCEL (DETAIL MODE – 1 BARIS = 1 KOMODITAS)
     * ===================================================== */
    public function export_excel()
    {
        $filters = [
            'upt'        => $this->input->get('upt'),
            'karantina'  => strtoupper($this->input->get('karantina')),
            'permohonan' => strtoupper($this->input->get('permohonan')),
            'start_date' => $this->input->get('start_date'),
            'end_date'   => $this->input->get('end_date'),
            'search'     => $this->input->get('search', true),
        ];

        /* ================= DATA ================= */
        $rows = $this->Penolakan_model->getExportByFilter($filters);
        if (!$rows) {
            return $this->json([
                'success' => false,
                'message' => 'Data kosong'
            ], 404);
        }

        /* ================= HEADER EXCEL ================= */
        $headers = [
            'No',
            'No Dokumen',
            'Tgl Dokumen',
            'No P6',
            'Tgl P6',
            'Satpel',
            'Pengirim',
            'Penerima',
            'Alasan Penolakan',
            'Petugas',
            'Komoditas',
            'HS',
            'Volume',
            'Satuan'
        ];

        /* ================= BUILD DATA (IDEM) ================= */
        $data   = [];
        $no     = 1;
        $lastId = null;

        foreach ($rows as $r) {
            $idem = ($r['id'] === $lastId);

            $data[] = [
                $idem ? '' : $no++,
                $idem ? 'Idem' : ($r['no_dok_permohonan'] ?? ''),
                $idem ? ''     : ($r['tgl_dok_permohonan'] ?? ''),
                $idem ? ''     : ($r['nomor_penolakan'] ?? ''),
                $idem ? ''     : ($r['tgl_penolakan'] ?? ''),
                $idem ? ''     : (($r['upt'] ?? '') . ' - ' . ($r['nama_satpel'] ?? '')),
                $idem ? ''     : ($r['nama_pengirim'] ?? ''),
                $idem ? ''     : ($r['nama_penerima'] ?? ''),
                $idem ? ''     : ($r['alasan_string'] ?? ''),
                $idem ? ''     : ($r['petugas'] ?? ''),
                $r['komoditas'] ?? '',
                $r['hs']        ?? '',
                $r['volume']    ?? '',
                $r['satuan']    ?? '',
            ];

            $lastId = $r['id'];
        }

        $info = $this->buildReportHeader(
            "LAPORAN PENOLAKAN {$filters['karantina']}",
            $filters
        );

        return $this->excel_handler->download(
            'Laporan_Penolakan',
            $headers,
            $data,
            $info
        );
    }
}
