<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'models/BaseModelStrict.php';

class Tangkapan_model extends BaseModelStrict
{
    protected $db_excel;

    public function __construct()
    {
        parent::__construct();
        $this->db_excel = $this->load->database('excel', TRUE);
    }

    private function komTable(string $karantina): string
    {
        return match (strtoupper($karantina)) {
            'H' => 'komoditas_hewan',
            'I' => 'komoditas_ikan',
            default => 'komoditas_tumbuhan',
        };
    }

    public function getAll(array $f): array
    {
        $tableKom = $this->komTable($f['karantina'] ?? 'T');

        $this->db->select("
            MIN(p.id) AS id,

            p3.nomor AS no_p3,
            MAX(p3.tanggal) AS tgl_p3,
            MAX(p3.alasan) AS alasan_tahan,
            MAX(p3.petugas) AS petugas_pelaksana,
            MAX(mr.nama) AS rekomendasi,

            MAX(
                REPLACE(
                    REPLACE(mu.nama,
                        'Balai Karantina Hewan, Ikan, dan Tumbuhan', 'BKHIT'
                    ),
                    'Balai Besar Karantina Hewan, Ikan, dan Tumbuhan', 'BBKHIT'
                )
            ) AS upt_singkat,

            MAX(mu.nama_satpel) AS satpel,

            MAX(
                CASE
                    WHEN p3.lokasi_tangkap = 'L' THEN 'Di luar tempat pemasukan'
                    WHEN p3.lokasi_tangkap = 'D' THEN 'Di dalam tempat pemasukan'
                    ELSE p3.lokasi_tangkap
                END
            ) AS lokasi_label,

            MAX(p3.ket_lokasi_tangkap) AS ket_lokasi,
            MAX(p.nama_pengirim) AS pengirim,
            MAX(p.nama_penerima) AS penerima,

            MAX(COALESCE(mn1.nama, kab1.nama)) AS asal,
            MAX(COALESCE(mn2.nama, kab2.nama)) AS tujuan,

            GROUP_CONCAT(DISTINCT kom.nama SEPARATOR '<br>') AS komoditas,
            GROUP_CONCAT(DISTINCT pkom.volume_lain SEPARATOR '<br>') AS volume,
            GROUP_CONCAT(DISTINCT ms.nama SEPARATOR '<br>') AS satuan
        ", false)
        ->from('ptk p')
        ->join('tangkapan p3', 'p.id = p3.ptk_id')
        ->join('master_upt mu', 'p.kode_satpel = mu.id', 'left')
        ->join('ptk_komoditas pkom', 'p.id = pkom.ptk_id', 'left')
        ->join("$tableKom kom", 'pkom.komoditas_id = kom.id', 'left')
        ->join('master_satuan ms', 'pkom.satuan_lain_id = ms.id', 'left')
        ->join('master_rekomendasi mr', 'p3.rekomendasi_id = mr.id', 'left')
        ->join('master_negara mn1', 'p.negara_asal_id = mn1.id', 'left')
        ->join('master_negara mn2', 'p.negara_tujuan_id = mn2.id', 'left')
        ->join('master_kota_kab kab1', 'p.kota_kab_asal_id = kab1.id', 'left')
        ->join('master_kota_kab kab2', 'p.kota_kab_tujuan_id = kab2.id', 'left');

        $this->applyFilter($f);

        $this->db->group_by('p3.nomor')
                 ->order_by('MAX(p3.tanggal)', 'DESC');

        return $this->db->get()->result_array();
    }

    public function getForExcel(array $f): array
    {
        $tableKom = $this->komTable($f['karantina'] ?? 'T');

        $this->db_excel->select("
            p.id,
            p3.nomor AS no_p3,
            p3.tanggal AS tgl_p3,
            mu.nama AS upt,
            mu.nama_satpel AS satpel,

            CASE
                WHEN p3.lokasi_tangkap = 'L' THEN 'Di luar tempat pemasukan'
                WHEN p3.lokasi_tangkap = 'D' THEN 'Di dalam tempat pemasukan'
                ELSE p3.lokasi_tangkap
            END AS lokasi_label,

            p3.ket_lokasi_tangkap AS ket_lokasi,
            COALESCE(mn1.nama, kab1.nama) AS asal,
            COALESCE(mn2.nama, kab2.nama) AS tujuan,
            p.nama_pengirim AS pengirim,
            p.nama_penerima AS penerima,

            kom.nama AS komoditas,
            pkom.volume_lain AS volume,
            pkom.volumeP1 AS vol_p1, pkom.volumeP2 AS vol_p2, pkom.volumeP3 AS vol_p3,
            pkom.nettoP1 AS net_p1, pkom.nettoP2 AS net_p2, pkom.nettoP3 AS net_p3,
            ms.nama AS satuan,

            p3.alasan AS alasan_tahan,
            p3.petugas AS petugas_pelaksana,
            mr.nama AS rekomendasi
        ", false)
        ->from('ptk p')
        ->join('tangkapan p3', 'p.id = p3.ptk_id')
        ->join('master_upt mu', 'p.kode_satpel = mu.id')
        ->join('ptk_komoditas pkom', 'p.id = pkom.ptk_id', 'left')
        ->join("$tableKom kom", 'pkom.komoditas_id = kom.id', 'left')
        ->join('master_satuan ms', 'pkom.satuan_lain_id = ms.id', 'left')
        ->join('master_rekomendasi mr', 'p3.rekomendasi_id = mr.id', 'left')
        ->join('master_negara mn1', 'p.negara_asal_id = mn1.id', 'left')
        ->join('master_negara mn2', 'p.negara_tujuan_id = mn2.id', 'left')
        ->join('master_kota_kab kab1', 'p.kota_kab_asal_id = kab1.id', 'left')
        ->join('master_kota_kab kab2', 'p.kota_kab_tujuan_id = kab2.id', 'left');

        $this->applyFilter($f, $this->db_excel);

        $this->db_excel->order_by('p3.nomor', 'ASC');

        return $this->db_excel->get()->result_array();
    }

    private function applyFilter(array $f, $db = null): void
    {
        $db = $db ?? $this->db;

        $db->where('p.is_batal', '0')
           ->where('p.upt_id <>', '1000');

        if (!empty($f['upt']) && $f['upt'] !== 'all') {
            $db->where('p.upt_id', $f['upt']);
        }

        if (!empty($f['karantina'])) {
            $db->where('p.jenis_karantina', substr($f['karantina'], -1));
        }

        $lingkup = $f['lingkup'] ?? ($f['permohonan'] ?? '');
        if (!empty($lingkup) && !in_array(strtolower($lingkup), ['all', 'semua', ''])) {
            $db->where('p.jenis_permohonan', strtoupper($lingkup));
        }

        if (!empty($f['start_date'])) {
            $db->where('p3.tanggal >=', $f['start_date'] . ' 00:00:00');
        }
        if (!empty($f['end_date'])) {
            $db->where('p3.tanggal <=', $f['end_date'] . ' 23:59:59');
        }
    }
}
