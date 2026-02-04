<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class PenggunaJasaDetail_model extends BaseModelStrict {

    public function getIds($filter, $limit, $offset)
    {
        $this->db->select('pj.id');
        $this->db->from('dbregptk.registers AS r');
        $this->db->join('dbregptk.pj_barantins AS pj', 'r.pj_barantin_id = pj.id');
        
        $this->applyFilters($filter);

        $this->db->limit($limit, $offset);
        $this->db->order_by('r.created_at', 'DESC');
        
        $res = $this->db->get()->result_array();
        return array_column($res, 'id');
    }
    public function getByIds($ids)
    {
        if (empty($ids)) return [];

        $this->db->select('pj.id, user_id, pemohon, pre.jenis_perusahaan, nama_perusahaan, 
                           jenis_identitas, nomor_identitas, nitku, r.master_upt_id, 
                           lingkup_aktifitas, rerata_frekuensi, daftar_komoditas, 
                           tempat_karantina, status_kepemilikan, pj.alamat, mu.nama AS upt, 
                           pj.email, nomor_registrasi, r.created_at AS tgl_registrasi, blockir');
        $this->db->from('dbregptk.registers AS r');
        $this->db->join('dbregptk.pj_barantins AS pj', 'r.pj_barantin_id = pj.id');
        $this->db->join('dbregptk.pre_registers AS pre', 'r.pre_register_id = pre.id');
        $this->db->join('barantin.master_upt AS mu', 'mu.id = r.master_upt_id');
        $this->db->where_in('pj.id', $ids);
        $this->db->order_by('r.created_at', 'DESC');

        return $this->db->get()->result_array();
    }

    public function countAll($filter)
    {
        $this->db->from('dbregptk.registers AS r');
        $this->db->join('dbregptk.pj_barantins AS pj', 'r.pj_barantin_id = pj.id');
        
        $this->applyFilters($filter);
        
        return $this->db->count_all_results();
    }

    private function applyFilters($filter)
    {
        $this->db->where('r.status', 'DISETUJUI');

        if (!empty($filter['upt'])) {
            $this->db->where('r.master_upt_id', $filter['upt']);
        }

        if (!empty($filter['permohonan'])) {
            $this->db->like('pj.lingkup_aktifitas', $filter['permohonan']);
        }
    }

    public function get_profil_lengkap($id) {
        $ids = [$id];
        $res = $this->getByIds($ids);
        return $res ? $res[0] : null;
    }

    public function get_history_ptk($id) {
        $sql = "SELECT p.id, no_dok_permohonan, tgl_dok_permohonan, jenis_karantina, 
                       jenis_permohonan, nama_pengirim, nama_pemohon, nama_umum_tercetak 
                FROM ptk AS p
                JOIN ptk_komoditas AS kom ON p.id = kom.ptk_id
                JOIN komoditas_hewan AS kh ON kh.id = kom.komoditas_id
                WHERE no_dok_permohonan IS NOT NULL AND is_batal = '0' 
                      AND kom.deleted_at = '1970-01-01 08:00:00' AND pengguna_jasa_id = ?
                
                UNION
                
                SELECT p.id, no_dok_permohonan, tgl_dok_permohonan, jenis_karantina, 
                       jenis_permohonan, nama_pengirim, nama_pemohon, nama_umum_tercetak 
                FROM ptk AS p
                JOIN ptk_komoditas AS kom ON p.id = kom.ptk_id
                JOIN komoditas_ikan AS ki ON ki.id = kom.komoditas_id
                WHERE no_dok_permohonan IS NOT NULL AND is_batal = '0' 
                      AND kom.deleted_at = '1970-01-01 08:00:00' AND pengguna_jasa_id = ?
                
                UNION
                
                SELECT p.id, no_dok_permohonan, tgl_dok_permohonan, jenis_karantina, 
                       jenis_permohonan, nama_pengirim, nama_pemohon, nama_umum_tercetak 
                FROM ptk AS p
                JOIN ptk_komoditas AS kom ON p.id = kom.ptk_id
                JOIN komoditas_tumbuhan AS kt ON kt.id = kom.komoditas_id
                WHERE no_dok_permohonan IS NOT NULL AND is_batal = '0' 
                      AND kom.deleted_at = '1970-01-01 08:00:00' AND pengguna_jasa_id = ?
                
                ORDER BY tgl_dok_permohonan DESC LIMIT 50";
                
        return $this->db->query($sql, [$id, $id, $id])->result_array();
    }
}