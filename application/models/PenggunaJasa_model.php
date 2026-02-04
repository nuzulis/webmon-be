<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class PenggunaJasa_model extends CI_Model
{
public function getList($f, $is_export = false, $limit = null, $offset = null)
{
    $sql = "
        SELECT 
            pj.id, u.id AS uid, pj.user_id, u.name AS pemohon, 
            pre.jenis_perusahaan, pj.nama_perusahaan, pj.jenis_identitas, 
            pj.nomor_identitas, pj.nitku, r.master_upt_id, 
            pj.lingkup_aktifitas, pj.rerata_frekuensi, pj.daftar_komoditas, 
            pj.tempat_karantina, pj.status_kepemilikan, mu.nama AS upt, 
            pj.email, pj.nomor_registrasi, r.created_at AS tgl_registrasi, 
            r.blockir
        FROM dbregptk.registers AS r
        JOIN dbregptk.pj_barantins AS pj ON r.pj_barantin_id = pj.id
        JOIN dbregptk.users AS u ON pj.user_id = u.id
        JOIN dbregptk.pre_registers AS pre ON r.pre_register_id = pre.id
        JOIN barantin.master_upt AS mu ON mu.id = r.master_upt_id
        WHERE r.status = 'DISETUJUI'
    ";

    $params = [];
    if (!empty($f['upt']) && $f['upt'] !== 'all') {
        $sql .= " AND r.master_upt_id = ?";
        $params[] = $f['upt'];
    }
    if (!empty($f['permohonan'])) {
        $sql .= " AND pj.lingkup_aktifitas LIKE ?";
        $params[] = '%' . $f['permohonan'] . '%';
    }

    $sql .= " ORDER BY r.created_at DESC";

    if ($is_export === false && $limit !== null) {
        $sql .= " LIMIT ? OFFSET ?";
        $params[] = (int)$limit;
        $params[] = (int)$offset;
    }

    return $this->db->query($sql, $params)->result_array();
}

public function get_list_data($limit, $offset, $upt = null, $permohonan = null)
{
    $this->db->select('pj.id, u.id AS uid, pj.nama_perusahaan, pre.pemohon, pj.jenis_identitas, pj.nomor_identitas, mu.nama AS upt, pj.lingkup_aktifitas, r.blockir');
    $this->db->from('dbregptk.registers AS r');
    $this->db->join('dbregptk.pj_barantins AS pj', 'r.pj_barantin_id = pj.id');
    $this->db->join('dbregptk.users AS u', 'pj.user_id = u.id');
    $this->db->join('dbregptk.pre_registers AS pre', 'r.pre_register_id = pre.id');
    $this->db->join('barantin.master_upt AS mu', 'mu.id = r.master_upt_id');
    
    $this->db->where('r.status', 'DISETUJUI');

    if ($upt && $upt !== 'all') {
        $this->db->where('r.master_upt_id', $upt);
    }
    
    if ($permohonan && $permohonan !== 'all') {
        $this->db->like('pj.lingkup_aktifitas', $permohonan);
    }

    $this->db->limit($limit, $offset);
    $this->db->order_by('r.created_at', 'DESC');
    
    return $this->db->get()->result_array();
}

public function get_total_count($upt = null, $permohonan = null)
{
    $this->db->from('dbregptk.registers AS r');
    $this->db->join('dbregptk.pj_barantins AS pj', 'r.pj_barantin_id = pj.id');
    
    $this->db->where('r.status', 'DISETUJUI');

    if ($upt && $upt !== 'all') {
        $this->db->where('r.master_upt_id', $upt);
    }
    
    if ($permohonan && $permohonan !== 'all') {
        $this->db->like('pj.lingkup_aktifitas', $permohonan);
    }

    return $this->db->count_all_results();
}
public function countList($f)
{
    $sql = "
        SELECT COUNT(*) as total
        FROM dbregptk.registers AS r
        JOIN dbregptk.pj_barantins AS pj ON r.pj_barantin_id = pj.id
        WHERE r.status = 'DISETUJUI'
    ";

    $params = [];
    if (!empty($f['upt']) && $f['upt'] !== 'all') {
        $sql .= " AND r.master_upt_id = ?";
        $params[] = $f['upt'];
    }
    if (!empty($f['permohonan'])) {
        $sql .= " AND pj.lingkup_aktifitas LIKE ?";
        $params[] = '%' . $f['permohonan'] . '%';
    }

    $query = $this->db->query($sql, $params)->row();
    return (int) $query->total;
}

public function get_profil_lengkap($id) 
{
    $sql = "SELECT 
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
            FROM dbregptk.registers AS r
            JOIN dbregptk.pj_barantins AS pj ON r.pj_barantin_id = pj.id
            JOIN dbregptk.users AS u ON pj.user_id = u.id 
            JOIN dbregptk.pre_registers AS pre ON r.pre_register_id = pre.id
            JOIN barantin.master_upt AS mu ON mu.id = r.master_upt_id
            WHERE pj.id = ?";
    
    return $this->db->query($sql, [$id])->row_array();
}

public function get_history_ptk($pj_id) 
{
      $sql = "SELECT 
                id, no_dok_permohonan, tgl_dok_permohonan, 
                jenis_karantina, jenis_permohonan, nama_pengirim, nama_pemohon
            FROM ptk 
            WHERE pengguna_jasa_id = ? 
              AND is_batal = 0
            ORDER BY tgl_dok_permohonan DESC 
            LIMIT 10";
                
    return $this->db->query($sql, [$pj_id])->result_array();
}
}
