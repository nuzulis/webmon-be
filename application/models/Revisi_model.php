<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'models/BaseModelStrict.php';

class Revisi_model extends BaseModelStrict
{
    private function getPelepasanTable($karantina) {
        $map = ['H' => 'pn_pelepasan_kh', 'I' => 'pn_pelepasan_ki', 'T' => 'pn_pelepasan_kt'];
        return $map[strtoupper($karantina)] ?? '';
    }

    private function applyFilters($f, $table) {
        $this->db->where('p.is_batal', '0');
        $this->db->where('p8.nomor_seri IS NOT NULL', null, false);
        $this->db->where("p8.nomor_seri != '*******'", null, false);
        $this->db->where("p8.deleted_at != '1970-01-01 08:00:00'", null, false);

        if (!empty($f['upt']) && $f['upt'] !== 'all') $this->db->where('p.upt_id', $f['upt']);
        if (!empty($f['permohonan'])) $this->db->where('p.jenis_permohonan', $f['permohonan']);

        if (!empty($f['start_date']) && !empty($f['end_date'])) {
            $this->db->where('p8.tanggal >=', $f['start_date']);
            $this->db->where('p8.tanggal <=', $f['end_date'] . ' 23:59:59');
        }

        if (!empty($f['search'])) {
            $q = $f['search'];
            $this->db->group_start();
                $this->db->like('p8.nomor', $q);
                $this->db->or_like('p.no_aju', $q);
                $this->db->or_like('p8.alasan_delete', $q);
                $this->db->or_like('mp1.nama', $q);
                $this->db->or_like('mp2.nama', $q);
            $this->db->group_end();
        }
    }

    public function getIds($f, $limit, $offset) {
        $table = $this->getPelepasanTable($f['karantina']);
        if ($table === '') return [];

        $this->db->select('p.id, MAX(p8.tanggal) AS last_tanggal', false)
            ->from('ptk p')
            ->join("$table p8", 'p.id = p8.ptk_id')
            ->join('master_pegawai mp1', 'p8.user_ttd_id = mp1.id', 'left')
            ->join('master_pegawai mp2', 'p8.user_delete = mp2.id', 'left');

        $this->applyFilters($f, $table);

        $this->db->group_by('p.id')->order_by('last_tanggal', 'DESC')->limit($limit, $offset);

        $res = $this->db->get()->result_array();
        return array_column($res, 'id');
    }

    public function countAll($f) {
        $table = $this->getPelepasanTable($f['karantina']);
        if (!$table) return 0;

        $this->db->select('COUNT(DISTINCT p.id) AS total', false)
            ->from('ptk p')
            ->join("$table p8", 'p.id = p8.ptk_id')
            ->join('master_pegawai mp1', 'p8.user_ttd_id = mp1.id', 'left')
            ->join('master_pegawai mp2', 'p8.user_delete = mp2.id', 'left');

        $this->applyFilters($f, $table);
        return (int) $this->db->get()->row()->total;
    }

    public function getByIds($ids, $karantina = null) {
        if (empty($ids)) return [];
        $table = $this->getPelepasanTable($karantina);
        if ($table === '') return [];

        $this->db->select("
            p.id, ANY_VALUE(IF(p.tssm_id IS NOT NULL, 'SSM', 'PTK')) AS sumber,
            ANY_VALUE(p.no_aju) AS no_aju, ANY_VALUE(mu.nama) AS upt,
            ANY_VALUE(mu.nama_satpel) AS nama_satpel, 
            ANY_VALUE(p.no_dok_permohonan) AS no_dok_permohonan,
            ANY_VALUE(p.tgl_dok_permohonan) AS tgl_dok_permohonan,
            ANY_VALUE(p8.nomor) AS no_dok, ANY_VALUE(p8.tanggal) AS tgl_dok,
            ANY_VALUE(p8.alasan_delete) AS alasan_delete, ANY_VALUE(mp1.nama) AS penandatangan,
            ANY_VALUE(mp2.nama) AS yang_menghapus
        ", false);

        $this->db->from('ptk p')
            ->join("$table p8", 'p.id = p8.ptk_id')
            ->join('master_upt mu', 'p.kode_satpel = mu.id')
            ->join('master_pegawai mp1', 'p8.user_ttd_id = mp1.id', 'left')
            ->join('master_pegawai mp2', 'p8.user_delete = mp2.id', 'left');

        $ids_string = implode(',', array_map([$this->db, 'escape'], $ids));
        $this->db->where("p.id IN ($ids_string)", NULL, FALSE); 

        $this->db->group_by('p.id')->order_by('tgl_dok', 'DESC');
        return $this->db->get()->result_array();
    }
}