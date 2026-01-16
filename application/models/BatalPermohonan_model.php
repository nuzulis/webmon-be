<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'models/BaseModelStrict.php';

class BatalPermohonan_model extends BaseModelStrict
{

    public function getIds($filter, $limit, $offset)
    {
        $this->db->select('p.id, MAX(p.deleted_at) AS last_deleted', false)
            ->from('ptk p')
            ->where('p.is_batal', '1');

        $this->applyCommonFilter($filter);
        
        // Cek jika helper tanggal tersedia
        if (method_exists($this, 'applyDateFilter')) {
            $this->applyDateFilter('p.tgl_dok_permohonan', $filter);
        }

        $this->db->group_by('p.id')
                 ->order_by('last_deleted', 'DESC')
                 ->limit($limit, $offset);

        return array_column($this->db->get()->result_array(), 'id');
    }

    /**
     * STEP 2 — DATA LIST
     * Menghapus array agar sinkron
     */
    public function getByIds($ids)
    {
        if (empty($ids)) return [];

        $this->db->select("
            p.id,
            ANY_VALUE(p.tssm_id) AS tssm_id,
            ANY_VALUE(p.no_aju) AS no_aju,
            ANY_VALUE(p.no_dok_permohonan) AS no_dok_permohonan,
            ANY_VALUE(p.tgl_dok_permohonan) AS tgl_dok_permohonan,
            ANY_VALUE(p.nama_pengirim) AS nama_pengirim,
            ANY_VALUE(p.nama_penerima) AS nama_penerima,
            ANY_VALUE(p.alasan_batal) AS alasan_batal,
            ANY_VALUE(p.deleted_at) AS tgl_batal,
            ANY_VALUE(mu.nama) AS upt,
            ANY_VALUE(mu.nama_satpel) AS nama_satpel,
            ANY_VALUE(mp.nama) AS pembatal,
            ANY_VALUE(p.jenis_karantina) AS jenis_karantina,
            ANY_VALUE(p.jenis_permohonan) AS jenis_permohonan
        ", false);

        $this->db->from('ptk p')
            ->join('master_upt mu', 'p.kode_satpel = mu.id')
            ->join('master_pegawai mp', 'p.user_batal = mp.id', 'left')
            ->where_in('p.id', $ids)
            ->group_by('p.id')
            ->order_by('tgl_batal', 'DESC');

        return $this->db->get()->result_array();
    }

    /**
     * TOTAL DATA
     * Menghapus : int agar sinkron
     */
    public function countAll($filter)
    {
        $this->db->select('COUNT(DISTINCT p.id) AS total', false)
            ->from('ptk p')
            ->where('p.is_batal', '1');

        $this->applyCommonFilter($filter);
        
        $res = $this->db->get()->row();
        return $res ? (int) $res->total : 0;
    }
 
 public function getFullData($filter)
{
    $this->db->select("
        p.id, p.tssm_id, p.no_aju, p.no_dok_permohonan, p.tgl_dok_permohonan,
        p.nama_pengirim, p.nama_penerima, p.alasan_batal, p.deleted_at AS tgl_batal,
        mu.nama AS upt, mu.nama_satpel, mp.nama AS pembatal
    ", false);

    $this->db->from('ptk p')
        ->join('master_upt mu', 'p.kode_satpel = mu.id')
        ->join('master_pegawai mp', 'p.user_batal = mp.id', 'left')
        ->where('p.is_batal', '1');

    // Terapkan filter
    $this->applyCommonFilter($filter);
    
    if (method_exists($this, 'applyDateFilter')) {
        $this->applyDateFilter('p.tgl_dok_permohonan', $filter);
    }

    $this->db->order_by('p.deleted_at', 'DESC');

    return $this->db->get()->result_array();
}
}