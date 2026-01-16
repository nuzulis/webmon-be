<?php
defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * @property CI_Input          $input
 * @property CI_Output         $output
 * @property CI_Config         $config
 * @property Pelepasan_model   $Pelepasan_model
 * @property Excel_handler      $excel_handler
 */
class Pelepasan extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Pelepasan_model');
        $this->load->helper(['jwt']);
        $this->load->library('excel_handler');
    }

    public function index()
    {
        /* JWT */
        $auth = $this->input->get_request_header('Authorization', true);
        if (!$auth || !preg_match('/Bearer\s+(\S+)/', $auth, $m)) {
            return $this->json(401, ['success' => false, 'message' => 'Unauthorized']);
        }

        try {
            
        } catch (Exception $e) {
            return $this->json(401, ['success' => false, 'message' => 'Token tidak valid']);
        }

        /* FILTER */
        $filters = [
            'upt'        => $this->input->get('upt', true),
            'karantina'  => strtoupper($this->input->get('karantina', true)),
            'permohonan' => strtoupper($this->input->get('permohonan', true)),
            'start_date' => $this->input->get('start_date', true),
            'end_date'   => $this->input->get('end_date', true),
        ];

        if (!in_array($filters['karantina'], ['H','I','T'], true)) {
            return $this->json(400, ['success' => false, 'message' => 'Karantina wajib (H/I/T)']);
        }

        /* PAGINATION */
        $page    = max((int) $this->input->get('page'), 1);
        $perPage = (int) $this->input->get('per_page');
        $perPage = ($perPage > 0 && $perPage <= 25) ? $perPage : 20;
        $offset  = ($page - 1) * $perPage;

        $ids   = $this->Pelepasan_model->getIds($filters, $perPage, $offset);
        $data = $ids ? $this->Pelepasan_model->getByIds($ids) : [];
        $total = $this->Pelepasan_model->countAll($filters);

        return $this->json(200, [
            'success' => true,
            'data'    => $data,
            'meta'    => [
                'page'       => $page,
                'per_page'   => $perPage,
                'total'      => $total,
                'total_page' => ceil($total / $perPage),
            ]
        ]);
    }
    public function export_excel()
{
    $filters = [
        'upt'        => $this->input->get('upt', true),
        'karantina'  => strtoupper($this->input->get('karantina', true)),
        'permohonan' => strtoupper($this->input->get('permohonan', true)),
        'start_date' => $this->input->get('start_date', true),
        'end_date'   => $this->input->get('end_date', true),
    ];

    $ids = $this->Pelepasan_model->getIds($filters, 5000, 0);
    $rows = $this->Pelepasan_model->getByIds($ids);

    $headers = [
        'No.', 'Pengajuan via', 'No. Aju', 'Tgl Aju', 'No. K.1.1', 'Tgl K.1.1', 'UPT', 'Satpel',
        'Tempat Periksa', 'Alamat Tempat Periksa', 'Tgl Periksa', 'Pemohon', 'Alamat Pemohon', 'Identitas Pemohon',
        'Pengirim', 'Alamat Pengirim', 'Identitas Pengirim', 'Penerima', 'Alamat Penerima', 'Identitas Penerima',
        'Asal', 'Daerah Asal', 'Pelabuhan Asal', 'Tujuan', 'Daerah Tujuan', 'Pelabuhan Tujuan',
        'Moda Alat Angkut', 'Nama Alat Angkut', 'Nomor Voyage', 'Jenis Kemasan', 'Jumlah Kemasan', 'Tanda Kemasan',
        'Nomor Dokumen', 'Nomor Seri', 'Tgl Dokumen', 'Klasifikasi', 'Komoditas', 'Nama Tercetak', 'Kode HS',
        'volumeP1', 'volumeP2', 'volumeP3', 'volumeP4', 'volumeP5', 'volumeP6', 'volumeP7', 'volumeP8',
        'satuan', 'Harga Barang (Rp)', 'Kontainer', 'Dokumen Pendukung'
    ];

    $exportData = [];
    $no = 1;
    $lastId = null;

    foreach ($rows as $r) {
        $isIdem = ($r['id'] === $lastId);
        
        $exportData[] = [
            $isIdem ? '' : $no++, // Nomor Urut Idem
            $isIdem ? 'Idem' : (isset($r['tssm_id']) ? 'SSM' : 'PTK'), // Kolom ini biasanya ikut Idem di native
            $r['no_aju'],
            $r['tgl_aju'],
            $r['no_dok_permohonan'],
            $r['tgl_dok_permohonan'],
            $r['upt'],
            $r['satpel'],
            $r['nama_tempat_pemeriksaan'],
            $r['alamat_tempat_pemeriksaan'],
            $r['tgl_pemeriksaan'],
            $r['nama_pemohon'],
            $r['alamat_pemohon'],
            $r['nomor_identitas_pemohon'],
            $r['nama_pengirim'],
            $r['alamat_pengirim'],
            $r['nomor_identitas_pengirim'],
            $r['nama_penerima'],
            $r['alamat_penerima'],
            $r['nomor_identitas_penerima'],
            $r['asal'],
            $r['kota_asal'],
            $r['pelabuhanasal'],
            $r['tujuan'],
            $r['kota_tujuan'],
            $r['pelabuhantuju'],
            $r['moda'],
            $r['nama_alat_angkut_terakhir'],
            $r['no_voyage_terakhir'],
            $r['kemas'],
            $r['total_kemas'],
            $r['tanda_khusus'],
            $r['nkt'],
            $r['seri'],
            $r['tanggal_lepas'],
            $r['klasifikasi'],
            $r['komoditas'],
            $r['nama_umum_tercetak'],
            $r['hs'],
            $r['p1'], $r['p2'], $r['p3'], $r['p4'], 
            $r['p5'], $r['p6'], $r['p7'], $r['p8'],
            $r['satuan'],
            $r['harga_rp'],
            $r['kontainer_string'],
        $r['dokumen_pendukung_string']
        ];
        $lastId = $r['id'];
    }

    $title = "LAPORAN PELEPASAN KARANTINA " . $filters['karantina'];
    $reportInfo = $this->buildReportHeader($title, $filters, $rows);

    $this->load->library('excel_handler');
    return $this->excel_handler->download("Laporan_Pelepasan", $headers, $exportData, $reportInfo);
}
    private function json(int $status, array $data)
    {
        return $this->output
            ->set_status_header($status)
            ->set_content_type('application/json', 'utf-8')
            ->set_output(json_encode($data, JSON_UNESCAPED_UNICODE));
    }
}
