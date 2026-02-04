<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'models/BaseModelStrict.php';

class Tangkapan_model extends BaseModelStrict
{
    /* =====================================================
     * STEP 1 — AMBIL ID (1 NOMOR P3 = 1 BARIS)
     * ===================================================== */
    public function getIds($f, $limit, $offset)
    {
        $this->db->select("
            MIN(p.id) AS id,
            p3.nomor,
            MAX(p3.tanggal) AS last_tgl
        ", false)
        ->from('ptk p')
        ->join('tangkapan p3', 'p.id = p3.ptk_id')
        ->where('p.is_batal', '0')
        ->where('p.upt_id <>', '1000');

        /* ===== FILTER SAMA PERSIS ===== */
        if (!empty($f['upt']) && $f['upt'] !== 'all') {
            $this->db->where('p.upt_id', $f['upt']);
        }

        if (!empty($f['karantina'])) {
            $this->db->where('p.jenis_karantina', substr($f['karantina'], -1));
        }

        if (!empty($f['permohonan'])) {
            $this->db->where('p.jenis_permohonan', $f['permohonan']);
        }

        if (!empty($f['start_date']) && !empty($f['end_date'])) {
            $this->db->where('p3.tanggal >=', $f['start_date'].' 00:00:00');
            $this->db->where('p3.tanggal <=', $f['end_date'].' 23:59:59');
        }

        $this->db
            ->group_by('p3.nomor')
            ->order_by('last_tgl', 'DESC')
            ->limit($limit, $offset);

        $res = $this->db->get()->result_array();
        return array_column($res, 'id');
    }

    /* =====================================================
     * STEP 2 — DATA TABEL (GROUP_CONCAT)
     * ===================================================== */
   public function getByIds($ids, $karantina = null)
{
    if (empty($ids)) return [];

    $tableKom = match ($karantina) {
        'H' => 'komoditas_hewan',
        'I' => 'komoditas_ikan',
        default => 'komoditas_tumbuhan'
    };

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
    ", false);

    $this->db->from('ptk p')
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

    $quoted = array_map(fn($id) => $this->db->escape($id), $ids);
    $this->db->where("p.id IN (" . implode(',', $quoted) . ")", null, false);

    return $this->db
        ->group_by('p3.nomor')
        ->order_by('tgl_p3', 'DESC')
        ->get()
        ->result_array();
}


    /* =====================================================
     * COUNT — HARUS SAMA DENGAN TABEL
     * ===================================================== */
    public function countAll($f)
    {
        $this->db->select('COUNT(DISTINCT p3.nomor) AS total', false)
            ->from('ptk p')
            ->join('tangkapan p3', 'p.id = p3.ptk_id')
            ->where('p.is_batal', '0')
            ->where('p.upt_id <>', '1000');

        if (!empty($f['upt']) && $f['upt'] !== 'all') {
            $this->db->where('p.upt_id', $f['upt']);
        }

        if (!empty($f['karantina'])) {
            $this->db->where('p.jenis_karantina', substr($f['karantina'], -1));
        }

        if (!empty($f['permohonan'])) {
            $this->db->where('p.jenis_permohonan', $f['permohonan']);
        }

        if (!empty($f['start_date']) && !empty($f['end_date'])) {
            $this->db->where('p3.tanggal >=', $f['start_date'].' 00:00:00');
            $this->db->where('p3.tanggal <=', $f['end_date'].' 23:59:59');
        }

        return (int) ($this->db->get()->row()->total ?? 0);
    }

    /* =====================================================
     * EXPORT — DETAIL KOMODITAS (IDEM JALAN)
     * ===================================================== */
    public function getExportByIds($ids, $karantina)
{
    if (empty($ids)) return [];

    $tableKom = match ($karantina) {
        'H' => 'komoditas_hewan',
        'I' => 'komoditas_ikan',
        default => 'komoditas_tumbuhan'
    };

    $quoted = array_map(fn($id) => $this->db->escape($id), $ids);

    $this->db->select("
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
        ms.nama AS satuan,

        p3.alasan AS alasan_tahan,
        p3.petugas AS petugas_pelaksana,
        mr.nama AS rekomendasi
    ", false);

    $this->db->from('ptk p')
        ->join('tangkapan p3', 'p.id = p3.ptk_id')
        ->join('master_upt mu', 'p.kode_satpel = mu.id')
        ->join('ptk_komoditas pkom', 'p.id = pkom.ptk_id', 'left')
        ->join("$tableKom kom", 'pkom.komoditas_id = kom.id', 'left')
        ->join('master_satuan ms', 'pkom.satuan_lain_id = ms.id', 'left')
        ->join('master_rekomendasi mr', 'p3.rekomendasi_id = mr.id', 'left')
        ->join('master_negara mn1', 'p.negara_asal_id = mn1.id', 'left')
        ->join('master_negara mn2', 'p.negara_tujuan_id = mn2.id', 'left')
        ->join('master_kota_kab kab1', 'p.kota_kab_asal_id = kab1.id', 'left')
        ->join('master_kota_kab kab2', 'p.kota_kab_tujuan_id = kab2.id', 'left')
        ->where("p.id IN (" . implode(',', $quoted) . ")", null, false)
        ->order_by('p3.nomor', 'ASC');

    return $this->db->get()->result_array();
}
}
