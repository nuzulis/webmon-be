<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'models/BaseModelStrict.php';

class SerahTerima_model extends BaseModelStrict
{
    /* ================= STEP 1 — AMBIL ID ================= */
    public function getIds($f, $limit, $offset)
    {
        $this->db->select('p.id, MAX(ba.tanggal) AS last_tanggal', false)
            ->from('ptk p')
            ->join('ba_penyerahan_mp ba', 'p.id = ba.ptk_id')
            ->join('ptk_komoditas pkom', 'p.id = pkom.ptk_id')
            ->where([
                'p.is_verifikasi' => '1',
                'p.is_batal'      => '0',
                'p.deleted_at'    => '1970-01-01 08:00:00',
                'pkom.deleted_at' => '1970-01-01 08:00:00',
            ]);

        // Filter UPT Tujuan (Logika Prefix 2 Digit)
        if (!empty($f['upt']) && $f['upt'] !== 'all') {
            $this->db->where("LEFT(ba.upt_tujuan_id, 2) =", substr($f['upt'], 0, 2), false);
        }

        if (!empty($f['karantina'])) {
            $this->db->where('ba.jenis_karantina', $f['karantina']);
        }

        // Filter Tanggal
        if (!empty($f['start_date']) && !empty($f['end_date'])) {
            $this->db->where('ba.tanggal >=', $f['start_date']);
            $this->db->where('ba.tanggal <=', $f['end_date'] . ' 23:59:59');
        }

        $this->db->group_by('p.id')
            ->order_by('last_tanggal', 'DESC')
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
        ANY_VALUE(IF(p.tssm_id IS NOT NULL, 'SSM', 'PTK')) AS sumber,
        ANY_VALUE(ba.nomor) AS nomor_ba,
        ANY_VALUE(ba.tanggal) AS tgl_ba,
        ANY_VALUE(ba.instansi_pihak1) AS instansi_asal,
        ANY_VALUE(ba.jenis_karantina) AS jns_kar,
        ANY_VALUE(ba.info_tambahan) AS keterangan,
        ANY_VALUE(mu.nama) AS upt_tujuan,
        ANY_VALUE(mj.nama) AS media_pembawa,
        ANY_VALUE(mp1.nama) AS petugas_penyerah,
        ANY_VALUE(mp2.nama) AS petugas_penerima,
        ANY_VALUE(p1.no_dok_permohonan) AS no_aju_tujuan,
        ANY_VALUE(p1.tgl_dok_permohonan) AS tgl_aju_tujuan,
        GROUP_CONCAT(DISTINCT kom.nama SEPARATOR '<br>') AS komoditas
    ", false);

    $this->db->from('ptk p')
        ->join('ba_penyerahan_mp ba', 'p.id = ba.ptk_id')
        ->join('ptk p1', 'ba.ptk_id_penerima = p1.id', 'left')
        ->join('ptk_komoditas pkom', 'p.id = pkom.ptk_id')
        ->join('master_upt mu', 'ba.upt_tujuan_id = mu.id', 'left')
        ->join('master_jenis_media_pembawa mj', 'ba.jenis_mp_id = mj.id', 'left')
        ->join('master_pegawai mp1', 'ba.user_asal_id = mp1.id', 'left')
        ->join('master_pegawai mp2', 'ba.user_tujuan_id = mp2.id', 'left');

    $tableKom = 'komoditas_tumbuhan';
    if ($karantina === 'H') $tableKom = 'komoditas_hewan';
    if ($karantina === 'I') $tableKom = 'komoditas_ikan';
    $this->db->join("$tableKom kom", 'pkom.komoditas_id = kom.id', 'left');

    // --- PERBAIKAN: Manual Escaping ---
    $quoted_ids = array_map(function($id) {
        return $this->db->escape($id);
    }, $ids);
    $ids_string = implode(',', $quoted_ids);
    $this->db->where("p.id IN ($ids_string)", NULL, FALSE);
    // ----------------------------------

    $this->db->group_by('p.id')
        ->order_by('tgl_ba', 'DESC');

    return $this->db->get()->result_array();
}

    /* ================= TOTAL DATA ================= */
    public function countAll($f)
    {
        $this->db->select('COUNT(DISTINCT p.id) AS total', false)
            ->from('ptk p')
            ->join('ba_penyerahan_mp ba', 'p.id = ba.ptk_id')
            ->join('ptk_komoditas pkom', 'p.id = pkom.ptk_id')
            ->where([
                'p.is_verifikasi' => '1',
                'p.is_batal'      => '0',
                'p.deleted_at'    => '1970-01-01 08:00:00',
                'pkom.deleted_at' => '1970-01-01 08:00:00',
            ]);

        if (!empty($f['upt']) && $f['upt'] !== 'all') {
            $this->db->where("LEFT(ba.upt_tujuan_id, 2) =", substr($f['upt'], 0, 2), false);
        }

        if (!empty($f['karantina'])) {
            $this->db->where('ba.jenis_karantina', $f['karantina']);
        }

        if (!empty($f['start_date']) && !empty($f['end_date'])) {
            $this->db->where('ba.tanggal >=', $f['start_date']);
            $this->db->where('ba.tanggal <=', $f['end_date'] . ' 23:59:59');
        }

        $row = $this->db->get()->row();
        return $row ? (int) $row->total : 0;
    }
}