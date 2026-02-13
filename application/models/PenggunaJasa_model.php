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
        $this->db->select('pj.id, MAX(r.created_at) as max_created', false)
            ->from('dbregptk.registers r')
            ->join('dbregptk.pj_barantins pj', 'r.pj_barantin_id = pj.id')
            ->join('dbregptk.users u', 'pj.user_id = u.id', 'left')
            ->join('barantin.master_upt mu', 'mu.id = r.master_upt_id', 'left')
            ->where('r.status', 'DISETUJUI');

        $this->applyManualFilter($f);
        $sortMap = [
            'nama_perusahaan'  => 'pj.nama_perusahaan',
            'pemohon'          => 'u.name',
            'upt'              => 'mu.nama',
            'tgl_registrasi'   => 'MAX(r.created_at)',
            'nomor_identitas'  => 'pj.nomor_identitas',
        ];

        $this->applySorting(
            $f['sort_by'] ?? null,
            $f['sort_order'] ?? 'DESC',
            $sortMap,
            ['MAX(r.created_at)', 'DESC']
        );

        $this->db->group_by('pj.id');
        $this->db->limit($limit, $offset);

        return array_column($this->db->get()->result_array(), 'id');
    }

    public function getByIds($ids)
    {
        if (empty($ids)) return [];

        $this->db->select("
            pj.id, 
            u.id AS uid, 
            pj.user_id, 
            u.name AS pemohon, 
            pre.jenis_perusahaan, 
            pj.nama_perusahaan, 
            pj.jenis_identitas, 
            pj.nomor_identitas, 
            pj.nitku, 
            r.master_upt_id, 
            pj.lingkup_aktifitas, 
            pj.rerata_frekuensi, 
            pj.daftar_komoditas, 
            pj.tempat_karantina, 
            pj.status_kepemilikan, 
            mu.nama AS upt, 
            pj.email, 
            pj.nomor_registrasi, 
            r.created_at AS tgl_registrasi, 
            r.blockir
        ", false);

        $this->db->from('dbregptk.registers r')
            ->join('dbregptk.pj_barantins pj', 'r.pj_barantin_id = pj.id')
            ->join('dbregptk.users u', 'pj.user_id = u.id')
            ->join('dbregptk.pre_registers pre', 'r.pre_register_id = pre.id')
            ->join('barantin.master_upt mu', 'mu.id = r.master_upt_id', 'left');

        $this->db->where_in('pj.id', $ids);
        $this->db->order_by('r.created_at', 'DESC');

        return $this->db->get()->result_array();
    }
    public function countAll($f)
    {
        $this->db->select('COUNT(DISTINCT pj.id) AS total', false)
            ->from('dbregptk.registers r')
            ->join('dbregptk.pj_barantins pj', 'r.pj_barantin_id = pj.id')
            ->join('dbregptk.users u', 'pj.user_id = u.id', 'left')
            ->join('barantin.master_upt mu', 'mu.id = r.master_upt_id', 'left')
            ->where('r.status', 'DISETUJUI');

        $this->applyManualFilter($f);

        $row = $this->db->get()->row();
        return $row ? (int) $row->total : 0;
    }

    public function getFullData($f)
    {
        $this->db->select("
            pj.id, 
            u.id AS uid, 
            pj.user_id, 
            u.name AS pemohon, 
            pre.jenis_perusahaan, 
            pj.nama_perusahaan, 
            pj.jenis_identitas, 
            pj.nomor_identitas, 
            pj.nitku, 
            r.master_upt_id, 
            pj.lingkup_aktifitas, 
            pj.rerata_frekuensi, 
            pj.daftar_komoditas, 
            pj.tempat_karantina, 
            pj.status_kepemilikan, 
            mu.nama AS upt, 
            pj.email, 
            pj.nomor_registrasi, 
            r.created_at AS tgl_registrasi, 
            r.blockir
        ", false);

        $this->db->from('dbregptk.registers r')
            ->join('dbregptk.pj_barantins pj', 'r.pj_barantin_id = pj.id')
            ->join('dbregptk.users u', 'pj.user_id = u.id')
            ->join('dbregptk.pre_registers pre', 'r.pre_register_id = pre.id')
            ->join('barantin.master_upt mu', 'mu.id = r.master_upt_id', 'left')
            ->where('r.status', 'DISETUJUI');

        $this->applyManualFilter($f);

        $this->db->order_by('r.created_at', 'DESC');

        return $this->db->get()->result_array();
    }

    private function applyManualFilter($f)
    {
        if (!empty($f['upt']) && !in_array(strtolower($f['upt']), ['all', 'semua'])) {
            $this->db->where('r.master_upt_id', $f['upt']);
        }
        if (!empty($f['permohonan'])) {
            $this->db->like('pj.lingkup_aktifitas', $f['permohonan']);
        }
        if (!empty($f['search'])) {
            $this->applyGlobalSearch($f['search'], [
                'pj.nama_perusahaan',
                'pj.nomor_identitas',
                'pj.nitku',
                'pj.email',
                'pj.nomor_registrasi',
                'u.name',
                'mu.nama',
            ]);
        }
    }

    public function get_profil_lengkap($id) 
    {
        $this->db->select("
            pj.id, 
            u.id AS uid, 
            pre.pemohon, 
            pre.jenis_perusahaan AS tipe_kantor,
            pj.jenis_perusahaan,
            pj.nama_perusahaan, 
            pj.jenis_identitas, 
            pj.nomor_identitas, 
            pj.nitku, 
            pj.alamat,
            pj.email,
            pj.telepon,
            mu.nama AS upt, 
            pj.lingkup_aktifitas, 
            pj.daftar_komoditas, 
            pj.rerata_frekuensi,
            pj.tempat_karantina,
            pj.status_kepemilikan,
            r.created_at AS tgl_registrasi, 
            r.blockir,
            r.status AS status_registrasi
        ", false);

        $this->db->from('dbregptk.registers r')
            ->join('dbregptk.pj_barantins pj', 'r.pj_barantin_id = pj.id')
            ->join('dbregptk.users u', 'pj.user_id = u.id')
            ->join('dbregptk.pre_registers pre', 'r.pre_register_id = pre.id')
            ->join('barantin.master_upt mu', 'mu.id = r.master_upt_id', 'left')
            ->where('pj.id', $id);
        
        return $this->db->get()->row_array();
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

    public function getList($f, $is_export = false, $limit = null, $offset = null)
    {
        if ($is_export) {
            return $this->getFullData($f);
        }
        
        $ids = $this->getIds($f, $limit, $offset);
        return $this->getByIds($ids);
    }

    public function countList($f)
    {
        return $this->countAll($f);
    }

    public function get_list_data($limit, $offset, $upt = null, $permohonan = null)
    {
        $f = [
            'upt' => $upt,
            'permohonan' => $permohonan,
        ];
        
        $ids = $this->getIds($f, $limit, $offset);
        return $this->getByIds($ids);
    }

    public function get_total_count($upt = null, $permohonan = null)
    {
        $f = [
            'upt' => $upt,
            'permohonan' => $permohonan,
        ];
        
        return $this->countAll($f);
    }
}