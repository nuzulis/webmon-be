<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'models/BaseModelStrict.php';

class Permohonan_model extends BaseModelStrict
{
    private function applyFilters($f) {
        $this->db->where([
            'p.is_verifikasi' => '1',
            'p.is_batal'      => '0',
            'pkom.deleted_at' => '1970-01-01 08:00:00',
            'status8p.p6'     => null, 
            'status8p.p7'     => null, 
            'status8p.p8'     => null, 
            'ba.id'           => null
        ]);

        if (!empty($f['upt']) && $f['upt'] !== 'all') {
            $this->db->where('p.upt_id', $f['upt']);
        }

        if (!empty($f['karantina'])) {
            $this->db->where('p.jenis_karantina', strtoupper($f['karantina']));
        }

        if (!empty($f['permohonan'])) {
            $this->db->where('p.jenis_permohonan', $f['permohonan']);
        }
        
        if (!empty($f['start_date']) && !empty($f['end_date'])) {
            $this->db->where("DATE(p.tgl_dok_permohonan) BETWEEN '{$f['start_date']}' AND '{$f['end_date']}'");
        }

        if (!empty($f['search'])) {
            $q = $f['search'];
            $this->db->group_start();
                $this->db->like('p.no_aju', $q);
                $this->db->or_like('p.no_dok_permohonan', $q);
                $this->db->or_like('p.nama_pengirim', $q);
                $this->db->or_like('p.nama_penerima', $q);
                $this->db->or_like('kom.nama', $q);
            $this->db->group_end();
        }
    }

    public function getIds($f, $limit, $offset) {
        $this->db->select('p.id, MAX(p.tgl_dok_permohonan) AS last_tgl', false)
            ->from('ptk p')
            ->join('ptk_komoditas pkom', 'p.id = pkom.ptk_id')
            ->join('status8p', 'p.id = status8p.id', 'left')
            ->join('ba_penyerahan_mp ba', 'p.id = ba.ptk_id', 'left');

        if (!empty($f['search'])) {
            $kar = strtoupper($f['karantina'] ?? 'H');
            $tabel_kom = "komoditas_" . ($kar == 'H' ? 'hewan' : ($kar == 'I' ? 'ikan' : 'tumbuhan'));
            $this->db->join("$tabel_kom kom", 'pkom.komoditas_id = kom.id', 'left');
        }

        $this->applyFilters($f);

        $this->db->group_by('p.id')
                 ->order_by('last_tgl', 'DESC')
                 ->limit($limit, $offset);

        return array_column($this->db->get()->result_array(), 'id');
    }

    public function countAll($f) {
        $this->db->select('COUNT(DISTINCT p.id) AS total', false)
            ->from('ptk p')
            ->join('ptk_komoditas pkom', 'p.id = pkom.ptk_id')
            ->join('status8p', 'p.id = status8p.id', 'left')
            ->join('ba_penyerahan_mp ba', 'p.id = ba.ptk_id', 'left');

        if (!empty($f['search'])) {
            $kar = strtoupper($f['karantina'] ?? 'H');
            $tabel_kom = "komoditas_" . ($kar == 'H' ? 'hewan' : ($kar == 'I' ? 'ikan' : 'tumbuhan'));
            $this->db->join("$tabel_kom kom", 'pkom.komoditas_id = kom.id', 'left');
        }

        $this->applyFilters($f);
        $res = $this->db->get()->row();
        return $res ? (int) $res->total : 0;
    }

    public function getByIds($ids, $karantina = 'H') {
        if (empty($ids)) return [];
        
        $kar = strtoupper($karantina ?: 'H');
        $tabel_kom = "komoditas_" . ($kar == 'H' ? 'hewan' : ($kar == 'I' ? 'ikan' : 'tumbuhan'));

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
            ->join('master_upt mu', 'p.kode_satpel = mu.id')
            ->join('master_hs mh', 'pkom.kode_hs = mh.kode', 'left')
            ->join('pn_fisik_kesehatan p1b', 'p.id = p1b.ptk_id', 'left')
            ->join("$tabel_kom kom", 'pkom.komoditas_id = kom.id', 'left');

        $this->db->where_in('p.id', $ids)
                 ->group_by('p.id')
                 ->order_by('p.tgl_dok_permohonan', 'DESC');

        return $this->db->get()->result_array();
    }
    

   public function getByIdsExport($ids, $karantina = 'H')
{
    if (!is_array($ids) || empty($ids)) return [];

    $tabel_kom = "komoditas_" . (
        $karantina == 'H' ? 'hewan' :
        ($karantina == 'I' ? 'ikan' : 'tumbuhan')
    );

    $this->db->select("
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

    $this->db->from('ptk p')
        ->join('ptk_komoditas pkom', 'p.id = pkom.ptk_id')
        ->join('master_upt mu', 'p.kode_satpel = mu.id')
        ->join('master_satuan ms', 'pkom.satuan_lain_id = ms.id', 'left')
        ->join('master_jenis_kemasan mjk', 'p.kemasan_id = mjk.id', 'left')
        ->join('master_hs mh', 'pkom.kode_hs = mh.kode', 'left')
        ->join('master_negara mn1', 'p.negara_asal_id = mn1.id', 'left')
        ->join('master_negara mn2', 'p.negara_tujuan_id = mn2.id', 'left')
        ->join('master_kota_kab mn3', 'p.kota_kab_asal_id = mn3.id', 'left')
        ->join('master_kota_kab mn4', 'p.kota_kab_tujuan_id = mn4.id', 'left')
        ->join('pn_fisik_kesehatan p1b', 'p.id = p1b.ptk_id', 'left')
        ->join("$tabel_kom kom", 'pkom.komoditas_id = kom.id', 'left');

    $quoted_ids = array_map([$this->db, 'escape'], $ids);
    $ids_string = implode(',', $quoted_ids);

    $this->db->where("p.id IN ($ids_string)", NULL, FALSE);

    $this->db->order_by('p.no_aju', 'ASC')
             ->order_by('pkom.id', 'ASC');

    return $this->db->get()->result_array();
}

}