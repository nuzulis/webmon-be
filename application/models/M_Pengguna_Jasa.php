<?php
class M_Pengguna_Jasa extends CI_Model {
    public function get_profil($pj_id) {
        return $this->db->get_where('master_pelanggan', ['id' => $pj_id])->row_array();
    }

    public function get_history($pj_id) {
        $this->db->select('no_aju, tgl_aju, jenis_permohonan, status_terakhir');
        $this->db->from('ptk');
        $this->db->where('pelanggan_id', $pj_id);
        $this->db->order_by('tgl_aju', 'DESC');
        $this->db->limit(10);
        return $this->db->get()->result_array();
    }
}