<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'models/BaseModelStrict.php';

class BatalPermohonan_model extends BaseModelStrict
{
    public function getIds($f, $limit, $offset)
    {
        $this->db->select('p.id, p.deleted_at', false)
            ->from('ptk p')
            ->where('p.is_batal', '1');

        $this->applyManualFilter($f);

        $this->db->order_by('p.deleted_at', 'DESC')
                 ->limit($limit, $offset);

        return array_column($this->db->get()->result_array(), 'id');
    }

    public function getByIds($ids)
    {
        if (empty($ids)) return [];

        $this->db->select("
            p.id,
            p.no_dok_permohonan,
            p.tgl_dok_permohonan,
            p.nama_pengirim,
            p.nama_penerima,
            p.alasan_batal,
            p.deleted_at AS tgl_batal,
            mu.nama AS upt,
            mu.nama_satpel,
            mp.nama AS pembatal
        ", false);

        $this->db->from('ptk p')
            ->join('master_upt mu', 'p.kode_satpel = mu.id')
            ->join('master_pegawai mp', 'p.user_batal = mp.id', 'left')
            ->where_in('p.id', $ids)
            ->order_by('p.deleted_at', 'DESC');

        return $this->db->get()->result_array();
    }

    public function countAll($f)
    {
        $this->db->select('COUNT(*) AS total', false)
            ->from('ptk p')
            ->where('p.is_batal', '1');

        $this->applyManualFilter($f);

        $res = $this->db->get()->row();
        return $res ? (int) $res->total : 0;
    }


    private function applyManualFilter($f)
    {
        if (!empty($f['karantina'])) {
            $valKarantina = strtoupper(trim($f['karantina']));
            $this->db->where('p.jenis_karantina', $valKarantina);
        }

        if (!empty($f['upt']) && !in_array($f['upt'], ['all', 'Semua'])) {
        
            if (strlen($f['upt']) <= 4) {
                $this->db->where('p.upt_id', $f['upt']);
            } else {
                $this->db->where('p.kode_satpel', $f['upt']);
            }
        }

        if (method_exists($this, 'applyDateFilter')) {
            $this->applyDateFilter('p.tgl_dok_permohonan', $f);
        }

        if (!empty($f['search'])) {
            $q = $f['search'];
            $this->db->group_start();
                $this->db->like('p.no_dok_permohonan', $q);
                $this->db->or_like('p.nama_pengirim', $q);
                $this->db->or_like('p.nama_penerima', $q);
                $this->db->or_like('p.alasan_batal', $q);
            $this->db->group_end();
        }
    }


    public function getFullData($f)
    {
        $this->db->select('p.id')
            ->from('ptk p')
            ->where('p.is_batal', '1');

        $this->applyManualFilter($f);

        $ids = array_column($this->db->get()->result_array(), 'id');
        return $this->getByIds($ids);
    }
}