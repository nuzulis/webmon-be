<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * @property CI_Input           $input
 * @property CI_Output          $output
 * @property PeriksaLapangan_model $PeriksaLapangan_model
 * @property Excel_handler      $excel_handler
 */
class PeriksaLapangan extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('PeriksaLapangan_model');
        $this->load->helper(['jwt']);
        $this->load->library('excel_handler');
    }

    public function index()
    {
        $filters = [
            'upt'        => $this->input->get('upt', TRUE),
            'karantina'  => strtoupper(trim($this->input->get('karantina', TRUE))),
            'lingkup'    => strtoupper(trim($this->input->get('lingkup', TRUE))),
            'start_date' => $this->input->get('start_date', TRUE),
            'end_date'   => $this->input->get('end_date', TRUE),
            'search'     => $this->input->get('search', TRUE),
            'sort_by'    => $this->input->get('sort_by', TRUE),
            'sort_order' => $this->input->get('sort_order', TRUE),
        ];

        $page    = max((int) $this->input->get('page'), 1);
        $perPage = (int) ($this->input->get('per_page') ?? 10);
        $offset  = ($page - 1) * $perPage;

        $ids   = $this->PeriksaLapangan_model->getIds($filters, $perPage, $offset);
        $data  = $this->PeriksaLapangan_model->getByIds($ids);
        $total = $this->PeriksaLapangan_model->countAll($filters);

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

    public function detail($id)
    {
        $data = $this->PeriksaLapangan_model->getDetailFinal($id);
        if (!$data) {
            return $this->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }
        return $this->json(['success' => true, 'data' => $data], 200);
    }


    public function export_excel()
    {
        $filters = [
            'upt'        => $this->input->get('upt', TRUE),
            'karantina'  => strtoupper(trim($this->input->get('karantina', TRUE))),       
            'lingkup' => strtoupper(trim($this->input->get('lingkup', TRUE))),
            'start_date' => $this->input->get('start_date', TRUE),
            'end_date'   => $this->input->get('end_date', TRUE),
        ];

        $this->applyScope($filters);
        $rows = $this->PeriksaLapangan_model->getExportByFilter($filters);

        if (empty($rows)) {
            die("Data tidak ditemukan untuk periode ini.");
        }

        $exportData = [];
        $no = 1;
        $lastId = null;

        foreach ($rows as $r) {
            $isIdem = ($r['id'] === $lastId);

            $exportData[] = [
                $isIdem ? '' : $no++,
                $isIdem ? '' : ($r['upt_nama'] . ' / ' . ($r['nama_satpel'] ?? '-')),
                $isIdem ? 'Idem' : ($r['no_dok_permohonan'] ?? ''),
                $isIdem ? '' : ($r['tgl_dok_permohonan'] ?? ''),
                $r['no_surtug'],
                $r['tgl_surtug'],
                $r['nama_petugas'] . ' (' . $r['nip_petugas'] . ')',
                $r['nama_komoditas'],
                $r['target'],
                $r['metode'],
                $r['mulai'],
                $r['selesai'],
                ($r['durasi_menit'] ?? 0) . ' Menit',
                (!empty($r['selesai'])) ? 'SELESAI' : 'PROSES',
                $r['keterangan_log'],
            ];
            $lastId = $r['id'];
        }

        $headers = [
            'No', 'UPT / Satpel', 'No Permohonan', 'Tgl Permohonan', 
            'No Surtug', 'Tgl Surtug', 'Petugas', 'Komoditas', 'Target', 'Metode', 
            'Mulai', 'Selesai', 'Durasi', 'Status', 'Keterangan Log'
        ];

        $title = "LAPORAN PEMERIKSAAN LAPANGAN (OFFICER)";
        $reportInfo = $this->buildReportHeader($title, $filters);

        return $this->excel_handler->download(
            "Laporan_PeriksaLapangan_" . date('Ymd_His'),
            $headers,
            $exportData,
            $reportInfo
        );
    }
}