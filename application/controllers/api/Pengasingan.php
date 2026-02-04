<?php
defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * @property CI_Input            $input
 * @property CI_Output           $output
 * @property CI_Config           $config
 * @property Pengasingan_model   $Pengasingan_model
 * @property Excel_handler         $excel_handler
 */
class Pengasingan extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Pengasingan_model');
        $this->load->helper(['jwt']);
        $this->load->library('excel_handler');
    }

    public function index()
    {
        $auth = $this->input->get_request_header('Authorization', TRUE);
        if (!$auth || !preg_match('/Bearer\s+(\S+)/', $auth, $m)) {
            return $this->json(401);
        }
        $filters = [
            'upt'        => $this->input->get('upt'),
            'karantina'  => strtoupper($this->input->get('karantina')),
            'permohonan' => strtoupper($this->input->get('permohonan')),
            'start_date' => $this->input->get('start_date'),
            'end_date'   => $this->input->get('end_date'),
            'search'     => $this->input->get('search', true),
        ];

       $page    = max((int) $this->input->get('page'), 1);
        $perPage = ((int) $this->input->get('per_page') === 10) ? 10 : 10;
        $offset  = ($page - 1) * $perPage;
        $ids = $this->Pengasingan_model
            ->getIds($filters, $perPage, $offset);

       
        $rows = [];
        if ($ids) {
            $rows = $this->Pengasingan_model
                ->getByIds($ids);
        }
        $total = $this->Pengasingan_model->countAll($filters);

        return $this->json([
            'success' => true,
            'data'    => $rows,
            'meta'    => [
                'page'       => $page,
                'per_page'   => $perPage,
                'total'      => $total,
                'total_page' => ceil($total / $perPage)
            ]
        ], 200);

    }
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

        
        $ids = $this->Pengasingan_model->getAllIdsForExport($filters);
        $rows = $this->Pengasingan_model->getByIds($ids, true);
        $headers = [
            'No', 'UPT', 'Nama Tempat', 'Tgl Mulai', 'Tgl Selesai', 
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
                $isIdem ? '' : $r['tempat'],
                $isIdem ? '' : $r['mulai'],
                $isIdem ? '' : $r['selesai'],
                $isIdem ? '' : $r['komoditas'],
                $isIdem ? '' : number_format($r['jumlah'], 3, ",", "."),
                $isIdem ? '' : $r['satuan'],
                $isIdem ? '' : $r['targets'],
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
        $this->load->library('excel_handler');
        $reportInfo = $this->buildReportHeader($title, $filters);

        return $this->excel_handler->download("Laporan_Pengasingan", $headers, $exportData, $reportInfo);

$rows = $this->Pengasingan_model->getByIds($ids, true);


        }
}
