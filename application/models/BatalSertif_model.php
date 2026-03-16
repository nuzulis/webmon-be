<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require_once APPPATH . 'models/BaseModelStrict.php';

class BatalSertif_model extends BaseModelStrict
{
    private function getPelepasanTable($karantina) {
        $map = ['H' => 'pn_pelepasan_kh', 'I' => 'pn_pelepasan_ki', 'T' => 'pn_pelepasan_kt'];
        return $map[strtoupper($karantina ?? 'T')] ?? $map['T'];
    }

    private function applyFilters($f, $table, $pegawaiJoined = false) {
        $this->db->where('p.is_batal', '0');
        if (!empty($f['upt']) && $f['upt'] !== 'all') {
            $this->db->where('p.upt_id', $f['upt']);
        }
        $lingkup = $f['lingkup'] ?? ($f['permohonan'] ?? '');
        if (!empty($lingkup) && !in_array(strtolower($lingkup), ['all', 'semua', ''])) {
            $this->db->where('p.jenis_permohonan', strtoupper($lingkup));
        }
        if (!empty($f['start_date']) && !empty($f['end_date'])) {
            $this->db->where('p8.tanggal >=', $f['start_date']);
            $this->db->where('p8.tanggal <=', $f['end_date'] . ' 23:59:59');
        }
        $this->db->where('p8.nomor_seri IS NOT NULL', null, false);
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

    private function applyHaving() {
        $wkt = '1970-01-01 08:00:00';
        $this->db->having("SUM(p8.deleted_at = '$wkt') = 0", null, false);
    }

    public function getIds(array $f, int $limit, int $offset): array {
        $table = $this->getPelepasanTable($f['karantina'] ?? 'T');
        $this->db->select('p.id, MAX(p8.tanggal) as sort_tgl', false)
            ->from('ptk p')
            ->join("$table p8", 'p.id = p8.ptk_id');

        $pegawaiJoined = !empty($f['search']);
        if ($pegawaiJoined) {
            $this->db->join('master_pegawai mp1', 'p8.user_ttd_id = mp1.id', 'left');
            $this->db->join('master_pegawai mp2', 'p8.user_delete = mp2.id', 'left');
        }

        $this->applyFilters($f, $table, $pegawaiJoined);
        $this->db->group_by('p.id');
        $this->applyHaving();

        $sortByKey = !empty($f['sort_by']) ? $f['sort_by'] : 'tgl_dok';
        if ($sortByKey === 'no_dok') {
            $this->db->order_by('MAX(p8.nomor)', strtoupper($f['sort_order'] ?? 'DESC'));
        } else {
            $this->db->order_by('sort_tgl', strtoupper($f['sort_order'] ?? 'DESC'));
        }

        $this->db->limit($limit, $offset);
        $res = $this->db->get();
        if (!$res) {
            log_message('error', 'Query Gagal: ' . $this->db->last_query());
            return [];
        }
        return array_column($res->result_array(), 'id');
    }

    public function countAll($f) {
        $table = $this->getPelepasanTable($f['karantina'] ?? 'T');
        $this->db->select('p.id', false)
            ->from('ptk p')
            ->join("$table p8", 'p.id = p8.ptk_id');

        $pegawaiJoined = !empty($f['search']);
        if ($pegawaiJoined) {
            $this->db->join('master_pegawai mp1', 'p8.user_ttd_id = mp1.id', 'left');
            $this->db->join('master_pegawai mp2', 'p8.user_delete = mp2.id', 'left');
        }

        $this->applyFilters($f, $table, $pegawaiJoined);
        $this->db->group_by('p.id');
        $this->applyHaving();

        $subquery = $this->db->get_compiled_select();
        $result   = $this->db->query("SELECT COUNT(*) AS total FROM ($subquery) AS sub");
        return (int) ($result->row()->total ?? 0);
    }

    public function getByIds($ids, $karantina = 'T', $sortBy = 'tgl_dok', $sortOrder = 'DESC') {
        if (empty($ids)) return [];
        $table = $this->getPelepasanTable($karantina);
        $this->db->select("
            p.id,
            ANY_VALUE(IF(p.tssm_id IS NOT NULL, 'SSM', 'PTK')) AS sumber,
            ANY_VALUE(p.no_aju) AS no_aju,
            ANY_VALUE(mu.nama) AS upt,
            ANY_VALUE(mu.nama_satpel) AS nama_satpel,
            ANY_VALUE(p.no_dok_permohonan) AS no_dok_permohonan,
            ANY_VALUE(p.tgl_dok_permohonan) AS tgl_dok_permohonan,
            GROUP_CONCAT(p8.nomor SEPARATOR '<br>') AS no_dok,
            GROUP_CONCAT(p8.nomor_seri SEPARATOR '<br>') AS nomor_seri,
            MAX(p8.tanggal) AS tgl_dok,
            GROUP_CONCAT(p8.deleted_at SEPARATOR '<br>') AS deleted_at,
            GROUP_CONCAT(p8.alasan_delete SEPARATOR '<br>') AS alasan_delete,
            ANY_VALUE(mp1.nama) AS penandatangan,
            ANY_VALUE(mp2.nama) AS yang_menghapus
        ", false)
        ->from('ptk p')
        ->join("$table p8", 'p.id = p8.ptk_id')
        ->join('master_upt mu', 'p.kode_satpel = mu.id', 'left')
        ->join('master_pegawai mp1', 'p8.user_ttd_id = mp1.id', 'left')
        ->join('master_pegawai mp2', 'p8.user_delete = mp2.id', 'left');

        $this->db->where_in('p.id', $ids);
        $this->db->group_by('p.id');
        $this->db->order_by('tgl_dok', strtoupper($sortOrder) === 'ASC' ? 'ASC' : 'DESC');
        return $this->db->get()->result_array();
    }

    public function getFullData(array $f): array {
        $table = $this->getPelepasanTable($f['karantina'] ?? 'T');
        $wkt   = '1970-01-01 08:00:00';

        $this->db->select("
            p.id,
            IF(p.tssm_id IS NOT NULL, 'SSM', 'PTK') AS sumber,
            p.no_aju,
            mu.nama AS upt,
            mu.nama_satpel,
            p.no_dok_permohonan,
            p.tgl_dok_permohonan,
            p8.nomor AS no_dok,
            p8.nomor_seri,
            p8.tanggal AS tgl_dok,
            p8.deleted_at,
            p8.alasan_delete,
            mp1.nama AS penandatangan,
            mp2.nama AS yang_menghapus
        ", false)
        ->from('ptk p')
        ->join("$table p8", 'p.id = p8.ptk_id')
        ->join('master_upt mu', 'p.kode_satpel = mu.id', 'left')
        ->join('master_pegawai mp1', 'p8.user_ttd_id = mp1.id', 'left')
        ->join('master_pegawai mp2', 'p8.user_delete = mp2.id', 'left');

        $this->applyFilters($f, $table, true);
        $this->db->where("p8.deleted_at != '$wkt'", null, false);
        $this->db->where("NOT EXISTS (SELECT 1 FROM $table px WHERE px.ptk_id = p.id AND px.deleted_at = '$wkt')", null, false);

        $sortCol   = (($f['sort_by'] ?? '') === 'no_dok') ? 'p8.nomor' : 'p8.tanggal';
        $sortOrder = strtoupper($f['sort_order'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
        $this->db->order_by('p.id', $sortOrder);
        $this->db->order_by($sortCol, $sortOrder);

        return $this->db->get()->result_array();
    }
}