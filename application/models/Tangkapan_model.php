<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'models/BaseModelStrict.php';

class Tangkapan_model extends BaseModelStrict
{
    /* ================= STEP 1 — AMBIL ID ================= */
    /* ================= STEP 1 — AMBIL ID ================= */
public function getIds($f, $limit, $offset)
{
    $this->db->select('p.id, MAX(p3.tanggal) AS last_tgl', false)
        ->from('ptk p')
        ->join('tangkapan p3', 'p.id = p3.ptk_id')
        ->where('p.is_batal', '0') // Hanya cek apakah tidak dibatalkan
        ->where('p.upt_id <>', '1000');

    // 1. Perbaikan Filter Karantina (Toleransi input 'T' atau 'KT')
    if (!empty($f['karantina'])) {
        $kar = strtoupper(substr($f['karantina'], -1)); // Mengambil huruf terakhir (T/H/I)
        $this->db->where('p.jenis_karantina', $kar);
    }

    // 2. Filter Permohonan
    if (!empty($f['permohonan'])) {
        $this->db->where('p.jenis_permohonan', $f['permohonan']);
    }

    // 3. Filter UPT
    if (!empty($f['upt']) && $f['upt'] !== 'all') {
        $this->db->where('p.upt_id', $f['upt']);
    }

    // 4. Filter Tanggal (Gunakan format yang konsisten)
    if (!empty($f['start_date']) && !empty($f['end_date'])) {
        $this->db->where('p3.tanggal >=', $f['start_date'] . ' 00:00:00');
        $this->db->where('p3.tanggal <=', $f['end_date'] . ' 23:59:59');
    }

    $this->db->group_by('p.id')
        ->order_by('last_tgl', 'DESC')
        ->limit($limit, $offset);

    $res = $this->db->get()->result_array();
    return array_column($res, 'id');
}

    /* ================= STEP 2 — DATA DETAIL ================= */
public function getByIds($ids, $karantina = null)
{
    if (empty($ids)) return [];

    $this->db->select("
        p.id,
        ANY_VALUE(p3.nomor) AS no_p3,
        ANY_VALUE(p3.tanggal) AS tgl_p3,
        ANY_VALUE(p3.alasan) AS alasan_tahan,
        ANY_VALUE(p3.petugas) AS petugas_pelaksana,
        ANY_VALUE(mr.nama) AS rekomendasi,
        ANY_VALUE(
            REPLACE(
                REPLACE(mu.nama, 'Balai Karantina Hewan, Ikan, dan Tumbuhan', 'BKHIT'),
                'Balai Besar Karantina Hewan, Ikan, dan Tumbuhan', 'BBKHIT'
            )
        ) AS upt_singkat,
        ANY_VALUE(mu.nama_satpel) AS satpel,
        ANY_VALUE(
            CASE 
                WHEN p3.lokasi_tangkap = 'L' THEN 'Di luar tempat pemasukan'
                WHEN p3.lokasi_tangkap = 'D' THEN 'Di dalam tempat pemasukan'
                ELSE p3.lokasi_tangkap 
            END
        ) AS lokasi_label,
        ANY_VALUE(p3.ket_lokasi_tangkap) AS ket_lokasi,
        ANY_VALUE(p.nama_pengirim) AS pengirim,
        ANY_VALUE(p.nama_penerima) AS penerima,
        ANY_VALUE(p.jenis_permohonan) AS jns_dok,
        ANY_VALUE(COALESCE(mn1.nama, mn3.nama)) AS asal,
        ANY_VALUE(COALESCE(mn2.nama, mn4.nama)) AS tujuan,
        GROUP_CONCAT(DISTINCT kom.nama SEPARATOR '<br>') AS komoditas,
        GROUP_CONCAT(DISTINCT pkom.kode_hs SEPARATOR '<br>') AS kode_hs,
        GROUP_CONCAT(DISTINCT pkom.volume_lain SEPARATOR '<br>') AS volume,
        GROUP_CONCAT(DISTINCT ms.nama SEPARATOR '<br>') AS satuan
    ", false);

    $this->db->from('ptk p')
        ->join('tangkapan p3', 'p.id = p3.ptk_id')
        ->join('master_rekomendasi mr', 'p3.rekomendasi_id = mr.id', 'left')
        ->join('master_upt mu', 'p.kode_satpel = mu.id')
        ->join('ptk_komoditas pkom', 'p.id = pkom.ptk_id', 'left')
        ->join('master_satuan ms', 'pkom.satuan_lain_id = ms.id', 'left')
        ->join('master_negara mn1', 'p.negara_asal_id = mn1.id', 'left')
        ->join('master_negara mn2', 'p.negara_tujuan_id = mn2.id', 'left')
        ->join('master_kota_kab mn3', 'p.kota_kab_asal_id = mn3.id', 'left')
        ->join('master_kota_kab mn4', 'p.kota_kab_tujuan_id = mn4.id', 'left');

    $tableKom = match ($karantina) {
        'H' => 'komoditas_hewan',
        'I' => 'komoditas_ikan',
        default => 'komoditas_tumbuhan'
    };
    $this->db->join("$tableKom kom", 'pkom.komoditas_id = kom.id', 'left');

    // Perbaikan: Manual Escaping untuk ID banyak
    $quoted_ids = array_map(fn($id) => $this->db->escape($id), $ids);
    $this->db->where("p.id IN (" . implode(',', $quoted_ids) . ")", NULL, FALSE);

    $this->db->group_by('p.id')->order_by('tgl_p3', 'DESC');
    return $this->db->get()->result_array();
}


public function countAll($f)
    {
        $this->db->select('COUNT(DISTINCT p.id) AS total', false)
            ->from('ptk p')
            ->join('tangkapan p3', 'p.id = p3.ptk_id')
            ->where([
                'p.is_verifikasi' => '1',
                'p.is_batal'      => '0',
                'p.upt_id <>'     => '1000'
            ]);

        if (!empty($f['upt']) && $f['upt'] !== 'all') {
            $this->db->where('p.upt_id', $f['upt']);
        }
        if (!empty($f['karantina'])) {
            $this->db->where('p.jenis_karantina', $f['karantina']);
        }
        if (!empty($f['permohonan'])) {
            $this->db->where('p.jenis_permohonan', $f['permohonan']);
        }
        if (!empty($f['start_date']) && !empty($f['end_date'])) {
            $this->db->where('p3.tanggal >=', $f['start_date']);
            $this->db->where('p3.tanggal <=', $f['end_date'] . ' 23:59:59');
        }

        $row = $this->db->get()->row();
        return $row ? (int) $row->total : 0;
    }
}