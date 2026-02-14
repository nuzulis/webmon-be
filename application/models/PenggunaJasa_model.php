<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'core/BaseModelStrict.php';

class PenggunaJasa_model extends BaseModelStrict
{
    public function __construct()
    {
        parent::__construct();
    }

    public function getIds($f, $limit, $offset)
    {
        $this->db->select('DISTINCT pj.id, MAX(r.created_at) as max_created', false)
            ->from('dbregptk.registers r')
            ->join('dbregptk.pj_barantins pj', 'r.pj_barantin_id = pj.id')
            ->join('dbregptk.users u', 'pj.user_id = u.id', 'left')
            ->join('barantin.master_upt mu', 'mu.id = r.master_upt_id', 'left');

        $this->applyManualFilter($f);
        
        $this->db->group_by('pj.id');
        $this->db->order_by('max_created', 'DESC');
        
        $this->db->limit($limit, $offset);

        $res = $this->db->get()->result_array();
        return array_column($res, 'id');
    }

        public function getByIds($ids)
    {
        if (empty($ids)) return [];

        $CI =& get_instance();
        $uptFilter = $CI->input->get('upt');
        $this->db->select("
            pj.id, 
            ANY_VALUE(u.id) AS uid, 
            ANY_VALUE(pj.user_id) as user_id, 
            ANY_VALUE(u.name) AS pemohon, 
            ANY_VALUE(pre.jenis_perusahaan) as jenis_perusahaan, 
            ANY_VALUE(pj.nama_perusahaan) as nama_perusahaan, 
            ANY_VALUE(pj.jenis_identitas) as jenis_identitas, 
            ANY_VALUE(pj.nomor_identitas) as nomor_identitas, 
            ANY_VALUE(pj.nitku) as nitku, 
            ANY_VALUE(r.master_upt_id) as master_upt_id, 
            ANY_VALUE(pj.lingkup_aktifitas) as lingkup_aktifitas, 
            ANY_VALUE(pj.rerata_frekuensi) as rerata_frekuensi, 
            ANY_VALUE(pj.daftar_komoditas) as daftar_komoditas, 
            ANY_VALUE(pj.tempat_karantina) as tempat_karantina, 
            ANY_VALUE(pj.status_kepemilikan) as status_kepemilikan, 
            ANY_VALUE(mu.nama) AS upt, 
            ANY_VALUE(pj.email) as email, 
            ANY_VALUE(pj.nomor_registrasi) as nomor_registrasi, 
            MAX(r.created_at) AS tgl_registrasi, 
            ANY_VALUE(r.blockir) as blockir
        ", false);

        $this->db->from('dbregptk.pj_barantins pj')
            ->join('dbregptk.registers r', 'pj.id = r.pj_barantin_id')
            ->join('dbregptk.users u', 'pj.user_id = u.id', 'left')
            ->join('dbregptk.pre_registers pre', 'r.pre_register_id = pre.id', 'left')
            ->join('barantin.master_upt mu', 'mu.id = r.master_upt_id', 'left');

        $this->db->where_in('pj.id', $ids);
        $this->db->where('r.status', 'DISETUJUI');
        if ($uptFilter && !in_array(strtolower($uptFilter), ['all', 'semua', '1000'])) {
            $prefix = substr($uptFilter, 0, 2);
            $this->db->like('r.master_upt_id', $prefix, 'after');
        }

        $this->db->group_by('pj.id');
        $this->db->order_by('tgl_registrasi', 'DESC');

        $res = $this->db->get();
        return $res ? $res->result_array() : [];
    }
    public function countAll($f)
    {
        $this->db->select('COUNT(DISTINCT pj.id) AS total', false)
            ->from('dbregptk.registers r')
            ->join('dbregptk.pj_barantins pj', 'r.pj_barantin_id = pj.id')
            ->join('barantin.master_upt mu', 'mu.id = r.master_upt_id', 'left')
            ->join('dbregptk.users u', 'pj.user_id = u.id', 'left');

        $this->applyManualFilter($f);

        $row = $this->db->get()->row();
        return $row ? (int) $row->total : 0;
    }

    private function applyManualFilter($f)
    {
        $this->db->where('r.status', 'DISETUJUI');
        if (!empty($f['upt']) && !in_array(strtolower($f['upt']), ['all', 'semua', 'undefined', '1000'])) {
            $upt = $f['upt'];
            $prefix = substr($upt, 0, 2);
            
            $this->db->group_start();
                $this->db->like('mu.kode_upt', $prefix, 'after');
                $this->db->or_like('r.master_upt_id', $prefix, 'after');
            $this->db->group_end();
        }
        if (!empty($f['permohonan'])) {
            $this->db->like('pj.lingkup_aktifitas', strtoupper($f['permohonan']));
        }
        if (!empty($f['search'])) {
            $s = $this->db->escape_like_str(trim($f['search']));
            $this->db->group_start();
                $this->db->like('pj.nama_perusahaan', $s);
                $this->db->or_like('pj.nomor_identitas', $s);
                $this->db->or_like('u.name', $s);
                $this->db->or_like('mu.nama', $s);
                $this->db->or_like('pj.nitku', $s);
            $this->db->group_end();
        }
    }

    public function getFullData($f)
    {
        $ids = $this->getIds($f, 10000, 0); 
        if (empty($ids)) return [];

        return $this->getByIds($ids);
    }

   public function get_profil_lengkap($id) 
{
    $this->db->select("
        pj.id, 
        ANY_VALUE(u.id) AS uid, 
        ANY_VALUE(pre.pemohon) as pemohon, 
        ANY_VALUE(pre.jenis_perusahaan) AS tipe_kantor,
        ANY_VALUE(pj.jenis_perusahaan) as jenis_perusahaan,
        ANY_VALUE(pj.nama_perusahaan) as nama_perusahaan, 
        ANY_VALUE(pj.jenis_identitas) as jenis_identitas, 
        ANY_VALUE(pj.nomor_identitas) as nomor_identitas, 
        ANY_VALUE(pj.nitku) as nitku, 
        ANY_VALUE(pj.alamat) as alamat,
        ANY_VALUE(pj.email) as email,
        ANY_VALUE(pj.telepon) as telepon,
        ANY_VALUE(mu.nama) AS upt, 
        ANY_VALUE(pj.lingkup_aktifitas) as lingkup_aktifitas, 
        ANY_VALUE(pj.daftar_komoditas) as daftar_komoditas, 
        ANY_VALUE(pj.rerata_frekuensi) as rerata_frekuensi,
        ANY_VALUE(pj.tempat_karantina) as tempat_karantina,
        ANY_VALUE(pj.status_kepemilikan) as status_kepemilikan,
        MAX(r.created_at) AS tgl_registrasi, 
        ANY_VALUE(r.blockir) as blockir,
        ANY_VALUE(r.status) AS status_registrasi
    ", false);

    $this->db->from('dbregptk.registers r')
        ->join('dbregptk.pj_barantins pj', 'r.pj_barantin_id = pj.id')
        ->join('dbregptk.users u', 'pj.user_id = u.id', 'left')
        ->join('dbregptk.pre_registers pre', 'r.pre_register_id = pre.id', 'left')
        ->join('barantin.master_upt mu', 'mu.id = r.master_upt_id', 'left')
        ->where('pj.id', $id)
        ->group_by('pj.id');
    
    $res = $this->db->get();
    return $res ? $res->row_array() : null;
}

    public function get_history_ptk($pj_id) 
    {
        $this->db->select('id, no_dok_permohonan, tgl_dok_permohonan, jenis_karantina, jenis_permohonan, nama_pengirim, nama_pemohon')
            ->from('ptk')
            ->where('pengguna_jasa_id', $pj_id)
            ->where('is_batal', '0')
            ->order_by('tgl_dok_permohonan', 'DESC')
            ->limit(10);
                
        return $this->db->get()->result_array();
    }
    public function getList($f, $is_export = false, $limit = 10, $offset = 0)
    {
        if ($is_export) return $this->getFullData($f);
        
        $ids = $this->getIds($f, $limit, $offset);
        return $this->getByIds($ids);
    }

    public function countList($f)
    {
        return $this->countAll($f);
    }
}