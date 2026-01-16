<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * @property KwitansiBelumBayar_model $KwitansiBelumBayar_model
 * @property CI_Input  $input
 * @property CI_Output $output
 * @property CI_Config $config
 */
class KwitansiBelumBayar extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('KwitansiBelumBayar_model');
        $this->load->helper('jwt');
    }

    public function index()
    {
        /* ================= JWT GUARD ================= */
        $auth = $this->input->get_request_header('Authorization', true);
        if (!$auth || !preg_match('/Bearer\s+(\S+)/', $auth, $m)) {
            return $this->json(401, ['success' => false, 'message' => 'Unauthorized']);
        }

        try {
            
        } catch (Exception $e) {
            return $this->json(401, ['success' => false, 'message' => 'Token tidak valid']);
        }

        /* ================= FILTER ================= */
        $filters = [
            'karantina'   => strtolower($this->input->get('karantina', true)),
            'start_date'  => $this->input->get('start_date', true),
            'end_date'    => $this->input->get('end_date', true),
            'upt'         => $this->input->get('upt', true) ?? 'all',
            'permohonan'  => $this->input->get('permohonan', true),
            'berdasarkan' => $this->input->get('berdasarkan', true),
        ];

        try {
            $rows = $this->KwitansiBelumBayar_model->fetch($filters);
        } catch (Exception $e) {
            return $this->json(500, [
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }

        return $this->json(200, [
            'success' => true,
            'data'    => array_map([$this, 'mapRow'], $rows),
            'meta'    => [
                'total' => count($rows)
            ]
        ]);
    }

    private function mapRow(array $r): array
    {
        $mapPermohonan = [
            'EX' => 'Ekspor',
            'IM' => 'Impor',
            'DM' => 'Domestik Masuk',
            'DK' => 'Domestik Keluar',
        ];

        $mapKarantina = [
            'H' => 'Hewan',
            'I' => 'Ikan',
            'T' => 'Tumbuhan',
        ];

        return [
            'upt'              => $r['nama_upt'] ?? '',
            'satpel'           => ($r['nama_satpel'] ?? '') . ' - ' . ($r['nama_pospel'] ?? ''),
            'jenis_karantina'  => $mapKarantina[$r['jenis_karantina']] ?? '',
            'nomor'            => $r['nomor'] ?? '',
            'tanggal'          => $r['tanggal'] ?? '',
            'jenis_permohonan' => $mapPermohonan[$r['jenis_permohonan']] ?? '',
            'wajib_bayar'      => $r['nama_wajib_bayar'] ?? '',
            'tipe_bayar'       => $r['tipe_bayar'] ?? '',
            'total_pnbp'       => (int) ($r['total_pnbp'] ?? 0),
            'kode_bill'        => $r['kode_bill'] ?? '',
            'ntpn'             => $r['ntpn'] ?? '',
            'ntb'              => $r['ntb'] ?? '',
            'date_bill'        => $r['date_bill'] ?? '',
            'date_setor'       => $r['date_setor'] ?? '',
            'bank'             => $r['bank'] ?? 'BELUM',
        ];
    }

    private function json(int $status, array $data)
    {
        return $this->output
            ->set_status_header($status)
            ->set_content_type('application/json', 'utf-8')
            ->set_output(json_encode($data, JSON_UNESCAPED_UNICODE));
    }
}
