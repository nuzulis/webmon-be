<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * @property Penugasan_model $Penugasan_model
 * @property CI_Input        $input
 * @property CI_Output       $output
 * @property CI_Config       $config
 * @property Excel_handler    $excel_handler
 */
class Penugasan extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Penugasan_model');
        $this->load->helper('jwt');
        $this->load->library('excel_handler');
    }

    public function index()
    {
        /* ================= JWT GUARD ================= */
        $auth = $this->input->get_request_header('Authorization', TRUE);
        if (!$auth || !preg_match('/Bearer\s+(\S+)/', $auth, $m)) {
            return $this->json(401, ['success' => false, 'message' => 'Unauthorized']);
        }

        /* ================= FILTER ================= */
        $filters = [
            'upt'        => $this->input->get('upt', true),
            'karantina'  => strtoupper(trim($this->input->get('karantina', true))),
            'start_date' => $this->input->get('start_date', true),
            'end_date'   => $this->input->get('end_date', true),
            'petugas'    => $this->input->get('petugas', true),
        ];

        /* ================= DATA ================= */
        $data = $this->Penugasan_model->getList($filters);

        return $this->json(200, [
            'success' => true,
            'data'    => $data,
            'meta'    => [
                'total' => count($data)
            ]
        ]);
    }

    public function export_excel()
    {
        /* ================= FILTER ================= */
        $filters = [
            'upt'        => $this->input->get('upt', true),
            'karantina'  => strtoupper(trim($this->input->get('karantina', true))),
            'start_date' => $this->input->get('start_date', true),
            'end_date'   => $this->input->get('end_date', true),
            'petugas'    => $this->input->get('petugas', true),
        ];

        // 1. Ambil Data
        $rows = $this->Penugasan_model->getList($filters);

        // 2. Persiapan Header Excel
        $headers = [
            'No', 
            'Nomor Surtug', 'Tgl Surtug', 
            'No. Permohonan', 'Tgl Permohonan', 
            'UPT', 'Satpel',
            'Nama Petugas', 'NIP Petugas',
            'Jenis Tugas',
            'Penandatangan', 'NIP TTD'
        ];

        // 3. Mapping Data dengan Logika Idem
        $exportData = [];
        $no = 1;
        $lastSurtug = null;

        foreach ($rows as $r) {
            // Jika nomor surtug sama dengan baris sebelumnya, tampilkan Idem
            $isIdem = ($r['nomor_surtug'] === $lastSurtug);

            $exportData[] = [
                $isIdem ? '' : $no++,
                $isIdem ? 'Idem' : $r['nomor_surtug'],
                $isIdem ? '' : $r['tgl_surtug'],
                $isIdem ? '' : $r['no_dok_permohonan'],
                $isIdem ? '' : $r['tgl_dok_permohonan'],
                $isIdem ? '' : $r['upt'],
                $isIdem ? '' : $r['satpel'],
                // Detail Petugas dan Tugas biasanya unik per baris dalam satu Surtug
                $r['nama_petugas'],
                $r['nip_petugas'],
                $r['jenis_tugas'],
                $isIdem ? '' : $r['penandatangan'],
                $isIdem ? '' : $r['nip_ttd'],
            ];

            $lastSurtug = $r['nomor_surtug'];
        }

        $title = "LAPORAN PENUGASAN PETUGAS KARANTINA";
        $reportInfo = $this->buildReportHeader($title, $filters);

        return $this->excel_handler->download("Laporan_Penugasan", $headers, $exportData, $reportInfo);
    }

    private function json(int $status, array $data)
    {
        return $this->output
            ->set_status_header($status)
            ->set_content_type('application/json', 'utf-8')
            ->set_output(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }
}