<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'models/BaseModelStrict.php';

class Perlakuan_model extends BaseModelStrict
{
    /* ================= STEP 1 — AMBIL ID ================= */
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

        $this->applyCommonFilter($filter, 'p');
        $this->applyDateFilter('p4.tanggal', $filter, true);

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

        // Gunakan ANY_VALUE untuk kolom yang tidak masuk GROUP BY agar kompatibel dengan ONLY_FULL_GROUP_BY
        $this->db->select("
            p.id,
            ANY_VALUE(p.tssm_id) AS tssm_id, 
            ANY_VALUE(p.no_aju) AS no_aju, 
            ANY_VALUE(p.no_dok_permohonan) AS no_dok_permohonan,
            ANY_VALUE(p4.nomor) AS no_p4, 
            ANY_VALUE(p4.tanggal) AS tgl_p4,
            ANY_VALUE(p4.nama_tempat) AS lokasi_perlakuan, 
            ANY_VALUE(p4.alamat_tempat) AS alamat_lokasi,
            ANY_VALUE(p4.alasan_perlakuan) AS alasan_perlakuan, 
            ANY_VALUE(p4.metode_perlakuan) AS metode,
            ANY_VALUE(p4.tgl_perlakuan_mulai) AS mulai, 
            ANY_VALUE(p4.tgl_perlakuan_selesai) AS selesai,
            ANY_VALUE(p4.nama_operator) AS nama_operator, 
            ANY_VALUE(p4.ket_perlakuan_lain) AS ket_perlakuan_lain,
            ANY_VALUE(mu.nama) AS upt, 
            ANY_VALUE(mu.nama_satpel) AS nama_satpel,
            ANY_VALUE(mr.nama) AS rekom, 
            ANY_VALUE(mp.deskripsi) AS tipe,
            ANY_VALUE(p.nama_pengirim) AS nama_pengirim, 
            ANY_VALUE(p.nama_penerima) AS nama_penerima,

            -- Agregasi Data Komoditas
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

        $this->applyCommonFilter($filter, 'p');
        $this->applyDateFilter('p4.tanggal', $filter, true);

        $row = $this->db->get()->row();
        return $row ? (int) $row->total : 0;
    }
}