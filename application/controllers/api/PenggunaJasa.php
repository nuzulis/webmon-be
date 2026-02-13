<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * @property CI_Input           $input
 * @property PenggunaJasa_model $PenggunaJasa_model
 * @property Excel_handler      $excel_handler
 */ 
class PenggunaJasa extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('PenggunaJasa_model');
        $this->load->library('excel_handler');
    }

    public function index()
    {
        $filters = [
            'upt'        => $this->input->get('upt', TRUE),
            'permohonan' => $this->input->get('permohonan', TRUE),
            'search'     => $this->input->get('search', TRUE),
            'sort_by'    => $this->input->get('sort_by', TRUE),
            'sort_order' => $this->input->get('sort_order', TRUE),
        ];

        $page    = max((int) $this->input->get('page'), 1);
        $perPage = (int) $this->input->get('per_page') ?: 10;
        $offset  = ($page - 1) * $perPage;
        $ids   = $this->PenggunaJasa_model->getIds($filters, $perPage, $offset);
        $data  = $this->PenggunaJasa_model->getByIds($ids);
        $total = $this->PenggunaJasa_model->countAll($filters);

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
    
    public function detail()
    {
        $input = json_decode(file_get_contents("php://input"), true);
        $id = $input['id'] ?? null;

        if (!$id) {
            return $this->json([
                'success' => false,
                'message' => 'ID tidak valid'
            ], 400);
        }
        
        $profil = $this->PenggunaJasa_model->get_profil_lengkap($id);

        $history = [];
        if ($profil) {
            $history = $this->PenggunaJasa_model->get_history_ptk($profil['id']);
        }

        return $this->json([
            'success' => true,
            'data' => [
                'profil'  => $profil,
                'history' => $history
            ]
        ], 200);
    }

    public function export_csv()
    {
        set_time_limit(0);
        ini_set('memory_limit', '256M');

        $filters = [
            'upt'        => $this->input->get('upt', TRUE),
            'permohonan' => $this->input->get('permohonan', TRUE),
            'search'     => $this->input->get('search', TRUE),
        ];
        $rows = $this->PenggunaJasa_model->getFullData($filters);

        if (empty($rows)) {
            return $this->json([
                'success' => false,
                'message' => 'Data tidak ditemukan'
            ], 404);
        }

        $filename = "Laporan_PenggunaJasa_" . date('Ymd_His') . ".csv";
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        
        $output = fopen('php://output', 'w');
        
        fputcsv($output, [
            'No', 'Nama Pemohon', 'Jenis Perusahaan', 'Nama Perusahaan', 
            'Identitas', 'Nomor Identitas', 'NITKU', 'UPT Registrasi', 
            'Lingkup Aktivitas', 'Rerata Frekuensi', 'Daftar Komoditas', 
            'Tempat Karantina', 'Status Kepemilikan', 'Email', 
            'Nomor Registrasi', 'Tanggal Registrasi', 'Status Blokir'
        ]);
        
        $no = 1;
        foreach ($rows as $r) {
            $lingkupArr = json_decode($r['lingkup_aktifitas'], true) ?: [];
            $lingkupTxt = implode("; ", array_column($lingkupArr, 'activity'));
            
            $komoditasArr = json_decode($r['daftar_komoditas'], true) ?: [];
            $komoditasTxt = implode("; ", array_filter($komoditasArr, function($v) { 
                return !empty($v); 
            }));

            fputcsv($output, [
                $no++,
                $r['pemohon'],
                $r['jenis_perusahaan'],
                $r['nama_perusahaan'],
                $r['jenis_identitas'],
                $r['nomor_identitas'],
                $r['nitku'],
                $r['upt'],
                $lingkupTxt ?: '-',
                $r['rerata_frekuensi'],
                $komoditasTxt ?: '-',
                ($r['tempat_karantina'] == 1 ? 'Internal' : 'Luar'),
                $r['status_kepemilikan'],
                $r['email'],
                $r['nomor_registrasi'],
                $r['tgl_registrasi'],
                ($r['blockir'] == 1 ? 'Terblokir' : 'Aktif')
            ]);
            
            flush(); 
        }

        fclose($output);
        
        $this->logActivity("EXPORT CSV: Pengguna Jasa");
        
        exit;
    }
}