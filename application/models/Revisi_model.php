<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'models/BaseModelStrict.php';


class Revisi_model extends BaseModelStrict
{
    private function getPelepasanTable($karantina) {
        $map = [
            'H' => 'pn_pelepasan_kh', 
            'I' => 'pn_pelepasan_ki', 
            'T' => 'pn_pelepasan_kt'
        ];
        $key = strtoupper($karantina ?? 'T');
        return $map[$key] ?? $map['T'];
    }

    private function applyFilters($f, $table, $pegawaiJoined = false) {
        $this->db->where('p.is_batal', '0');
        if (!empty($f['upt']) && $f['upt'] !== 'all') {
            $this->db->where('p.upt_id', $f['upt']);
        }
        
        if (!empty($f['permohonan'])) {
            $this->db->where('p.jenis_permohonan', $f['permohonan']);
        }
        if (!empty($f['start_date']) && !empty($f['end_date'])) {
            $this->db->where('p8.tanggal >=', $f['start_date']);
            $this->db->where('p8.tanggal <=', $f['end_date'] . ' 23:59:59');
        }
        $this->db->where('p8.nomor_seri IS NOT NULL', null, false);
        $this->db->where("p8.nomor_seri != '*******'", null, false);
        $this->db->where("p8.deleted_at != '1970-01-01 08:00:00'", null, false);
        if (!empty($f['search'])) {
            $q = $f['search'];
            $this->db->group_start();
                $this->db->like('p8.nomor', $q);
                $this->db->or_like('p8.nomor_seri', $q);
                $this->db->or_like('p.no_aju', $q);
                $this->db->or_like('p8.alasan_delete', $q);
                if ($pegawaiJoined) {
                    $this->db->or_like('mp1.nama', $q);
                    $this->db->or_like('mp2.nama', $q);
                }
            $this->db->group_end();
        }
    }

    public function getIds($f, $limit, $offset) {
        $table = $this->getPelepasanTable($f['karantina'] ?? 'T');
        $sortByKey = !empty($f['sort_by']) ? $f['sort_by'] : 'tgl_dok';
        $sortBy = ($sortByKey === 'no_dok') ? 'p8.nomor' : 'p8.tanggal';
        $sortOrder = strtoupper($f['sort_order'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
        $this->db->select('p.id', false)
            ->from('ptk p')
            ->join("$table p8", 'p.id = p8.ptk_id');
        $pegawaiJoined = !empty($f['search']);
        if ($pegawaiJoined) {
            $this->db->join('master_pegawai mp1', 'p8.user_ttd_id = mp1.id', 'left');
            $this->db->join('master_pegawai mp2', 'p8.user_delete = mp2.id', 'left');
        }

        $this->applyFilters($f, $table, $pegawaiJoined);
        $this->db->order_by($sortBy, $sortOrder)
            ->limit($limit, $offset);

        $res = $this->db->get()->result_array();
        return array_column($res, 'id');
    }
    public function countAll($f) {
        $table = $this->getPelepasanTable($f['karantina'] ?? 'T');
        $this->db->select('COUNT(*) AS total', false)
            ->from('ptk p')
            ->join("$table p8", 'p.id = p8.ptk_id');
        $pegawaiJoined = !empty($f['search']);
        if ($pegawaiJoined) {
            $this->db->join('master_pegawai mp1', 'p8.user_ttd_id = mp1.id', 'left');
            $this->db->join('master_pegawai mp2', 'p8.user_delete = mp2.id', 'left');
        }

        $this->applyFilters($f, $table, $pegawaiJoined);
        
        return (int) ($this->db->get()->row()->total ?? 0);
    }

    public function getByIds($ids, $karantina = 'T', $sortBy = 'tgl_dok', $sortOrder = 'DESC') {
        if (empty($ids)) return [];
        
        $table = $this->getPelepasanTable($karantina);
        $sortMap = [
            'no_dok'  => 'no_dok',
            'tgl_dok' => 'tgl_dok',
        ];
        $orderBy = $sortMap[$sortBy] ?? 'tgl_dok';
        $order = strtoupper($sortOrder) === 'ASC' ? 'ASC' : 'DESC';
        $this->db->select("
            p.id, 
            IF(p.tssm_id IS NOT NULL, 'SSM', 'PTK') AS sumber,
            p.no_aju, 
            mu.nama AS upt,
            mu.nama_satpel AS nama_satpel, 
            p.no_dok_permohonan,
            p.tgl_dok_permohonan,
            p8.nomor AS no_dok, 
            p8.nomor_seri,
            p8.tanggal AS tgl_dok,
            p8.deleted_at,
            p8.alasan_delete, 
            mp1.nama AS penandatangan,
            mp2.nama AS yang_menghapus
        ", false);

        $this->db->from('ptk p')
            ->join("$table p8", 'p.id = p8.ptk_id')
            ->join('master_upt mu', 'p.kode_satpel = mu.id')
            ->join('master_pegawai mp1', 'p8.user_ttd_id = mp1.id', 'left')
            ->join('master_pegawai mp2', 'p8.user_delete = mp2.id', 'left');

        $ids_string = implode(',', array_map([$this->db, 'escape'], $ids));
        $this->db->where("p.id IN ($ids_string)", NULL, FALSE);
        $this->db->order_by($orderBy, $order);
        
        return $this->db->get()->result_array();
    }
}