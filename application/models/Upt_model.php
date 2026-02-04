<?php
class Upt_model extends CI_Model {

    public function get_upt_by_kode($kode_upt) {
        return $this->db->get_where('master_upt', ['kode_upt' => $kode_upt])->row();
    }

    public function get_upt_regional($kode_upt_user) {
        $this->db->select('regional');
        $this->db->where('kode_upt', $kode_upt_user);
        $user_regional = $this->db->get('master_upt')->row();

        if (!$user_regional) return [];
        $this->db->where('regional', $user_regional->regional);
        $this->db->where('regional IS NOT NULL', null, false);
        $this->db->order_by('kode_upt', 'ASC');
        return $this->db->get('master_upt')->result();
    }
}