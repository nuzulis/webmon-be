<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class PenggunaJasa_model extends CI_Model
{
    public function getList($f, $is_export=false)
    {
        $sql = "
            SELECT 
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
                r.blockir -- Pindah dari pj.blockir ke r.blockir
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

        $res = $this->db->query($sql, $params)->result_array();
        
        foreach ($res as &$row) {
            $row['blockir'] = (int) $row['blockir'];
            
            // Fallback pemohon: Gunakan nama perusahaan jika u.name kosong
            if (empty($row['pemohon'])) {
                $row['pemohon'] = $row['nama_perusahaan'];
            }
        }

        return $res;
    }
}