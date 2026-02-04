<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'models/BaseModelStrict.php';

class Perlakuan_model extends BaseModelStrict
{
    /* =====================================================
     * STEP 1 — AMBIL ID (UNTUK PAGINATION)
     * ===================================================== */
    public function getIds($filter, $limit, $offset)
    {
        $this->db->select('p.id, MAX(p4.tanggal) AS last_tgl', false)
            ->from('ptk p')
            ->join('pn_perlakuan p4', 'p.id = p4.ptk_id')
            ->where([
                'p.is_verifikasi'         => '1',
                'p.is_batal'              => '0',
                'p4.deleted_at'           => '1970-01-01 08:00:00',
                'p4.dokumen_karantina_id' => '23',
            ]);

        /* ===== FILTER UPT (2 digit induk → kode_satpel 4 digit) ===== */
        if (isset($filter['upt']) && $filter['upt'] != '' && $filter['upt'] != 'Semua' && $filter['upt'] != 'all') {
    $kodeUpt = substr($filter['upt'], 0, 2);
    $this->db->like('p.kode_satpel', $kodeUpt, 'after');
}

        /* ===== FILTER KARANTINA ===== */
        if (!empty($filter['karantina'])) {
            $this->db->where('p.jenis_karantina', $filter['karantina']);
        }

        /* ===== FILTER LINGKUP / PERMOHONAN ===== */
        if (!empty($filter['permohonan'])) {
            $this->db->where('p.jenis_permohonan', $filter['permohonan']);
        }

        /* ===== FILTER TANGGAL ===== */
        $this->applyDateFilter('p4.tanggal', $filter, true);

        $this->db->group_by('p.id')
                 ->order_by('last_tgl', 'DESC')
                 ->limit($limit, $offset);

        return array_column($this->db->get()->result_array(), 'id');
    }

    /* =====================================================
     * STEP 2 — DATA TABEL (GROUP_CONCAT)
     * ===================================================== */
    public function getByIds($ids, $karantina = null)
    {
        if (empty($ids)) return [];

        $this->db->select("
            p.id,
            ANY_VALUE(p.no_aju) AS no_aju,
            ANY_VALUE(p4.nomor) AS no_p4,
            ANY_VALUE(p4.tanggal) AS tgl_p4,
            ANY_VALUE(p4.nama_tempat) AS lokasi_perlakuan,
            ANY_VALUE(p4.alasan_perlakuan) AS alasan_perlakuan,
            ANY_VALUE(p4.metode_perlakuan) AS metode,
            ANY_VALUE(p4.tgl_perlakuan_mulai) AS mulai,
            ANY_VALUE(p4.tgl_perlakuan_selesai) AS selesai,
            ANY_VALUE(p4.nama_operator) AS nama_operator,

            ANY_VALUE(mu.nama) AS upt,
            ANY_VALUE(mu.nama_satpel) AS nama_satpel,
            ANY_VALUE(mr.nama) AS rekom,
            ANY_VALUE(mp.deskripsi) AS tipe,
            ANY_VALUE(p.nama_pengirim) AS nama_pengirim,
            ANY_VALUE(p.nama_penerima) AS nama_penerima,

            GROUP_CONCAT(DISTINCT kom.nama SEPARATOR '<br>') AS komoditas,
            GROUP_CONCAT(DISTINCT pkom.volumeP8 SEPARATOR '<br>') AS volume,
            GROUP_CONCAT(DISTINCT ms.nama SEPARATOR '<br>') AS satuan
        ", false);

        $this->db->from('ptk p')
            ->join('pn_perlakuan p4', 'p.id = p4.ptk_id')
            ->join('ptk_komoditas pkom', 'p.id = pkom.ptk_id', 'left')
            ->join('master_satuan ms', 'pkom.satuan_lain_id = ms.id', 'left')
            ->join('master_upt mu', 'p.kode_satpel = mu.id')
            ->join('master_rekomendasi mr', 'p4.rekomendasi_id = mr.id', 'left')
            ->join('master_perlakuan mp', 'p4.tipe_perlakuan_id = mp.id', 'left');

        if ($karantina === 'H') {
            $this->db->join('komoditas_hewan kom', 'pkom.komoditas_id = kom.id', 'left');
        } elseif ($karantina === 'I') {
            $this->db->join('komoditas_ikan kom', 'pkom.komoditas_id = kom.id', 'left');
        } else {
            $this->db->join('komoditas_tumbuhan kom', 'pkom.komoditas_id = kom.id', 'left');
        }

        $this->db->where_in('p.id', $ids)
                 ->group_by('p.id')
                 ->order_by('ANY_VALUE(p4.tanggal)', 'DESC');

        return $this->db->get()->result_array();
    }

    /* =====================================================
     * STEP 3 — DATA EXCEL (FLAT / IDEM)
     * ===================================================== */
    public function getByIdsForExcel($ids, $karantina = null)
    {
        if (empty($ids)) return [];

        $this->db->select("
            p.id,
            p.no_aju,
            p4.nomor AS no_p4,
            p4.tanggal AS tgl_p4,
            mu.nama AS upt,
            mu.nama_satpel,
            p4.nama_tempat AS lokasi_perlakuan,
            p4.metode_perlakuan AS metode,
            p.nama_pengirim,
            p.nama_penerima,

            kom.nama AS komoditas,
            pkom.volumeP8 AS volume,
            ms.nama AS satuan,

            p4.alasan_perlakuan,
            mp.deskripsi AS tipe,
            p4.tgl_perlakuan_mulai AS mulai,
            p4.tgl_perlakuan_selesai AS selesai,
            mr.nama AS rekom,
            p4.nama_operator
        ", false);

        $this->db->from('ptk p')
            ->join('pn_perlakuan p4', 'p.id = p4.ptk_id')
            ->join('ptk_komoditas pkom', 'p.id = pkom.ptk_id')
            ->join('master_satuan ms', 'pkom.satuan_lain_id = ms.id', 'left')
            ->join('master_upt mu', 'p.kode_satpel = mu.id')
            ->join('master_rekomendasi mr', 'p4.rekomendasi_id = mr.id', 'left')
            ->join('master_perlakuan mp', 'p4.tipe_perlakuan_id = mp.id', 'left');

        if ($karantina === 'H') {
            $this->db->join('komoditas_hewan kom', 'pkom.komoditas_id = kom.id');
        } elseif ($karantina === 'I') {
            $this->db->join('komoditas_ikan kom', 'pkom.komoditas_id = kom.id');
        } else {
            $this->db->join('komoditas_tumbuhan kom', 'pkom.komoditas_id = kom.id');
        }

        $this->db->where_in('p.id', $ids)
                 ->order_by('p.id, pkom.id');

        return $this->db->get()->result_array();
    }

    /* =====================================================
     * COUNT (PAGINATION)
     * ===================================================== */
    public function countAll($filter)
    {
        $this->db->select('COUNT(DISTINCT p.id) AS total', false)
            ->from('ptk p')
            ->join('pn_perlakuan p4', 'p.id = p4.ptk_id')
            ->where([
                'p.is_verifikasi'         => '1',
                'p.is_batal'              => '0',
                'p4.deleted_at'           => '1970-01-01 08:00:00',
                'p4.dokumen_karantina_id' => '23',
            ]);

        if (isset($filter['upt']) && $filter['upt'] != '' && $filter['upt'] != 'Semua' && $filter['upt'] != 'all') {
    $kodeUpt = substr($filter['upt'], 0, 2);
    $this->db->like('p.kode_satpel', $kodeUpt, 'after');
}

        if (!empty($filter['karantina'])) {
            $this->db->where('p.jenis_karantina', $filter['karantina']);
        }

        if (!empty($filter['permohonan'])) {
            $this->db->where('p.jenis_permohonan', $filter['permohonan']);
        }

        $this->applyDateFilter('p4.tanggal', $filter, true);

        $row = $this->db->get()->row();
        return $row ? (int) $row->total : 0;
    }
}
