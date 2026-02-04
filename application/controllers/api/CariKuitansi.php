<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * @property CariKuitansi_model $CariKuitansi_model
 * @property CI_Input  $input
 * @property CI_Output $output
 * @property CI_Config $config
 */
class CariKuitansi extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('CariKuitansi_model');
        $this->load->helper('jwt');
    }

    public function index()
{
   header('Access-Control-Allow-Origin: *');
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        exit;
    }

    $this->output->set_content_type('application/json');
    $filter    = strtoupper($this->input->get('filter', true));
    $pencarian = trim($this->input->get('pencarian', true));

    if (!$filter || !$pencarian) {
        return $this->output->set_output(json_encode([
            'success' => false,
            'error' => 'Parameter tidak lengkap'
        ]));
    }

    try {
        $data = $this->CariKuitansi_model->cari($filter, $pencarian);
        
        if (empty($data) || !isset($data['list_kuitansi'])) {
             return $this->output->set_output(json_encode([
                'success' => true,
                'rows' => [],
                'detail' => null
            ]));
        }

        return $this->output->set_output(json_encode([
            'success' => true,
            'rows' => $this->mapRows($data['list_kuitansi']),
            'detail' => $this->mapDetail($data)
        ]));

    } catch (Exception $e) {
        return $this->output->set_output(json_encode([
            'success' => false, 
            'error' => $e->getMessage()
        ]));
    }
}

    private function mapRows(array $rows): array
    {
        $mapKarantina = [
            'H' => 'Hewan',
            'I' => 'Ikan',
            'T' => 'Tumbuhan',
        ];

        $out = [];
        $no  = 1;

        foreach ($rows as $r) {
            $out[] = [
                'no'              => $no++,
                'nomor'           => $r['nomor'] ?? '',
                'tanggal'         => $r['tanggal'] ?? '',
                'jenis_karantina' => $mapKarantina[$r['jenis_karantina']] ?? '',
                'nomor_dokumen'   => $r['nomor_dokumen'] ?? '',
                'tgl_dokumen'     => $r['tgl_dokumen'] ?? '',
                'nomor_seri'      => $r['nomor_seri'] ?? '',
                'nama_wajib_bayar'=> $r['nama_wajib_bayar'] ?? '',
                'tipe_bayar'      => $r['tipe_bayar'] ?? '',
                'total_pnbp'      => (int) ($r['total_pnbp'] ?? 0),
                'status_bayar'    => $r['status_bayar'] ?? '',
                'created_at'      => $r['created_at'] ?? '',
                'updated_at'      => $r['updated_at'] ?? '',
                'deleted_at'      => $r['deleted_at'] ?? '',
                'alasan_hapus'    => $r['alasan_hapus'] ?? '',
            ];
        }

        return $out;
    }

    private function mapDetail(array $d): array
    {
        return [
            'nama_upt'    => $d['nama_upt'] ?? '',
            'nama_satpel' => $d['nama_satpel'] ?? '',
            'nama_pospel' => $d['nama_pospel'] ?? '',
            'kode_bill'   => $d['kode_bill'] ?? '',
            'date_bill'   => $d['date_bill'] ?? '',
            'ntb'         => $d['ntb'] ?? '',
            'ntpn'        => $d['ntpn'] ?? '',
            'date_setor'  => $d['date_setor'] ?? '',
            'total_bill'  => (int) ($d['total_bill'] ?? 0),
            'status_bill' => $d['status_bill'] ?? '',
            'bank'        => $d['bank'] ?? '',
            'deleted_at'  => $d['deleted_at'] ?? '',
            'alasan_hapus'=> $d['alasan_hapus'] ?? '',
        ];
    }

   
}
