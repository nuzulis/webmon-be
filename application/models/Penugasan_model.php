<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Penugasan_model extends CI_Model
{
    public function getList($f)
    {
        $this->db->select("
            h.nomor AS nomor_surtug,
            h.tanggal AS tgl_surtug,
            p.no_dok_permohonan,
            p.tgl_dok_permohonan,
            p.jenis_karantina,
            mu.nama AS upt,
            mu.nama_satpel AS satpel,
            mp1.nama AS nama_petugas,
            mp1.nip AS nip_petugas,
            mp2.nama AS penandatangan,
            mp2.nip AS nip_ttd,
            mpn.nama AS jenis_tugas
        ", false);

        $this->db->from('ptk p');
        $this->db->join('master_upt mu', 'p.upt_id = mu.id');
        $this->db->join('ptk_surtug_header h', 'p.id = h.ptk_id');
        $this->db->join('ptk_surtug_petugas pp', 'h.id = pp.ptk_surtug_header_id');
        $this->db->join('ptk_surtug_penugasan pnp', 'pp.id = pnp.ptk_surtug_petugas_id');
        $this->db->join('master_penugasan mpn', 'pnp.penugasan_id = mpn.id');
        $this->db->join('master_pegawai mp1', 'pp.petugas_id = mp1.id');
        $this->db->join('master_pegawai mp2', 'h.penanda_tangan_id = mp2.id');

        $this->db->where([
            'p.is_verifikasi' => '1',
            'p.is_batal'      => '0',
            'h.deleted_at'    => '1970-01-01 08:00:00'
        ]);

        /* ================= LOGIKA FILTER EKSKLUSIF ================= */
        
        // 1. Prioritaskan Petugas (Nama) jika diisi
        if (!empty($f['petugas'])) {
            $this->db->where('pp.petugas_id', $f['petugas']);
        } 
        // 2. Jika Petugas Kosong, baru cek Karantina
        else if (!empty($f['karantina'])) {
            $this->db->where('p.jenis_karantina', $f['karantina']);
        }

        /* ================= FILTER TAMBAHAN ================= */

        // Filter UPT tetap berjalan bersama filter utama
        if (!empty($f['upt']) && $f['upt'] !== 'all') {
            $cleanUpt = substr($f['upt'], 0, 2) . '00';
            $this->db->where('p.upt_id', $cleanUpt);
        }

        // Filter Tanggal tetap berjalan bersama filter utama
        if (!empty($f['start_date']) && !empty($f['end_date'])) {
            $this->db->where("DATE(h.tanggal) BETWEEN '{$f['start_date']}' AND '{$f['end_date']}'");
        }

        $this->db->order_by('h.tanggal', 'DESC');
        
        return $this->db->get()->result_array();
    }
}