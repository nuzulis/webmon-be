<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'core/BaseModelStrict.php';

class Permohonan_model extends BaseModelStrict
{
    protected $db_excel;

    public function __construct()
    {
        parent::__construct();
        $this->db_excel = $this->load->database('excel', TRUE);
    }

    public function getAll(array $f): array
    {
        $kar       = strtoupper($f['karantina'] ?? 'H');
        $tabel_kom = 'komoditas_' . ($kar === 'H' ? 'hewan' : ($kar === 'I' ? 'ikan' : 'tumbuhan'));

        $this->db->select("
            p.id,
            ANY_VALUE(p.no_aju) AS no_aju,
            ANY_VALUE(p.no_dok_permohonan) AS no_dok_permohonan,
            ANY_VALUE(p.tgl_dok_permohonan) AS tgl_dok_permohonan,
            ANY_VALUE(mu.nama) AS upt,
            ANY_VALUE(mu.nama_satpel) AS satpel,
            ANY_VALUE(p.nama_pengirim) AS nama_pengirim,
            ANY_VALUE(p.nama_penerima) AS nama_penerima,
            GROUP_CONCAT(DISTINCT kom.nama SEPARATOR '<br>') AS komoditas,
            GROUP_CONCAT(DISTINCT
                CASE
                    WHEN mh.level_risiko = 'L' THEN 'Low'
                    WHEN mh.level_risiko = 'M' THEN 'Medium'
                    WHEN mh.level_risiko = 'H' THEN 'High'
                    ELSE mh.level_risiko
                END SEPARATOR '<br>'
            ) AS risiko,
            ANY_VALUE(p1b.waktu_periksa) AS tgl_periksa,
            ANY_VALUE(TIMESTAMPDIFF(MINUTE, p1b.waktu_periksa, NOW())) AS selisih_menit
        ", false);

        $this->db->from('ptk p')
            ->join('ptk_komoditas pkom', 'p.id = pkom.ptk_id')
            ->join('status8p', 'p.id = status8p.id', 'left')
            ->join('ba_penyerahan_mp ba', 'p.id = ba.ptk_id', 'left')
            ->join('master_upt mu', 'p.kode_satpel = mu.id', 'left')
            ->join('master_hs mh', 'pkom.kode_hs = mh.kode', 'left')
            ->join('pn_fisik_kesehatan p1b', 'p.id = p1b.ptk_id', 'left')
            ->join("$tabel_kom kom", 'pkom.komoditas_id = kom.id', 'left');

        $this->applyFilter($f);

        $this->db->group_by('p.id')
                 ->order_by('MAX(p.tgl_dok_permohonan)', 'DESC');

        return $this->db->get()->result_array();
    }

    public function getForExcel(array $f): array
    {
        $kar       = strtoupper($f['karantina'] ?? 'H');
        $tabel_kom = 'komoditas_' . ($kar === 'H' ? 'hewan' : ($kar === 'I' ? 'ikan' : 'tumbuhan'));

        $this->db_excel->select("
            p.id,
            p.no_aju,
            p.tgl_aju,
            p.no_dok_permohonan,
            p.tgl_dok_permohonan,
            p.tssm_id,
            mu.nama AS upt,
            mu.nama_satpel AS satpel,
            p.nama_tempat_pemeriksaan,
            p.nama_pemohon,
            p.nama_pengirim,
            p.nama_penerima,
            mn1.nama AS asal,
            mn2.nama AS tujuan,
            mn3.nama AS kota_asal,
            mn4.nama AS kota_tujuan,
            p.nama_alat_angkut_terakhir,
            mjk.deskripsi AS kemas,
            pkom.jumlah_kemasan AS total_kemas,
            kom.nama AS komoditas,
            pkom.nama_umum_tercetak,
            pkom.kode_hs AS hs,
            pkom.volumeP1 AS p1,
            pkom.volumeP2 AS p2,
            pkom.volumeP3 AS p3,
            pkom.volumeP4 AS p4,
            pkom.volumeP5 AS p5,
            ms.nama AS satuan,
            pkom.harga_rp,
            mh.level_risiko AS risiko,
            p1b.waktu_periksa AS tgl_periksa,
            TIMESTAMPDIFF(MINUTE, p1b.waktu_periksa, NOW()) AS selisih_menit
        ", false);

        $this->db_excel->from('ptk p')
            ->join('ptk_komoditas pkom', 'p.id = pkom.ptk_id')
            ->join('status8p', 'p.id = status8p.id', 'left')
            ->join('ba_penyerahan_mp ba', 'p.id = ba.ptk_id', 'left')
            ->join('master_upt mu', 'p.kode_satpel = mu.id', 'left')
            ->join('master_satuan ms', 'pkom.satuan_lain_id = ms.id', 'left')
            ->join('master_jenis_kemasan mjk', 'p.kemasan_id = mjk.id', 'left')
            ->join('master_hs mh', 'pkom.kode_hs = mh.kode', 'left')
            ->join('master_negara mn1', 'p.negara_asal_id = mn1.id', 'left')
            ->join('master_negara mn2', 'p.negara_tujuan_id = mn2.id', 'left')
            ->join('master_kota_kab mn3', 'p.kota_kab_asal_id = mn3.id', 'left')
            ->join('master_kota_kab mn4', 'p.kota_kab_tujuan_id = mn4.id', 'left')
            ->join('pn_fisik_kesehatan p1b', 'p.id = p1b.ptk_id', 'left')
            ->join("$tabel_kom kom", 'pkom.komoditas_id = kom.id', 'left');

        $this->applyFilter($f, $this->db_excel);

        $this->db_excel->order_by('p.no_aju', 'ASC')
                       ->order_by('pkom.id', 'ASC');

        return $this->db_excel->get()->result_array();
    }

    private function applyFilter(array $f, $db = null): void
    {
        $db = $db ?? $this->db;

        $db->where([
            'p.is_verifikasi' => '1',
            'p.is_batal'      => '0',
            'pkom.deleted_at' => '1970-01-01 08:00:00',
            'status8p.p6'     => null,
            'status8p.p7'     => null,
            'status8p.p8'     => null,
            'ba.id'           => null,
        ]);

        if (!empty($f['upt']) && !in_array(strtolower($f['upt']), ['all', 'semua'])) {
            $db->where('p.upt_id', $f['upt']);
        }
        if (!empty($f['karantina'])) {
            $db->where('p.jenis_karantina', strtoupper($f['karantina']));
        }

        $lingkup = $f['lingkup'] ?? ($f['permohonan'] ?? '');
        if (!empty($lingkup) && !in_array(strtolower($lingkup), ['all', 'semua'])) {
            $db->where('p.jenis_permohonan', strtoupper($lingkup));
        }

        if (!empty($f['start_date'])) {
            $db->where('DATE(p.tgl_dok_permohonan) >=', $f['start_date']);
        }
        if (!empty($f['end_date'])) {
            $db->where('DATE(p.tgl_dok_permohonan) <=', $f['end_date']);
        }
    }
}
