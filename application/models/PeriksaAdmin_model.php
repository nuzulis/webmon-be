<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'core/BaseModelStrict.php';

class PeriksaAdmin_model extends BaseModelStrict
{
    public function __construct()
    {
        parent::__construct();
    }

    private function applyManualFilter($f, $hasSearchJoins = false)
    {
        $this->db->where([
            'p.is_verifikasi' => '1',
            'p.is_batal'      => '0',
            'p.status_ptk'    => '1',
            'p1a.deleted_at'  => '1970-01-01 08:00:00'
        ]);

        if (!empty($f['upt']) && !in_array(strtolower($f['upt']), ['all', 'semua', 'undefined'])) {
            $field = (strlen($f['upt']) <= 4) ? "p.upt_id" : "p.kode_satpel";
            $this->db->where($field, $f['upt']);
        }

        if (!empty($f['start_date'])) {
            $this->db->where('p1a.tanggal >=', $f['start_date'] . ' 00:00:00');
        }
        if (!empty($f['end_date'])) {
            $this->db->where('p1a.tanggal <=', $f['end_date'] . ' 23:59:59');
        }

        if (!empty($f['karantina']) && !in_array(strtolower($f['karantina']), ['all', ''])) {
            $this->db->where('p.jenis_karantina', substr(strtoupper($f['karantina']), 0, 1));
        }

        $lingkup = $f['lingkup'] ?? ($f['permohonan'] ?? '');
        if (!empty($lingkup) && !in_array(strtolower($lingkup), ['all', 'semua', ''])) {
            $this->db->where('p.jenis_permohonan', strtoupper($lingkup));
        }

        $this->db->where('NOT EXISTS (SELECT 1 FROM pn_fisik_kesehatan fisik WHERE fisik.ptk_id = p.id)', null, false);

        if (!empty($f['search'])) {
            $searchColumns = [
                'p.no_aju',
                'p.no_dok_permohonan',
                'p1a.nomor',
                'p.nama_pengirim',
                'p.nama_penerima',
            ];
            if ($hasSearchJoins) {
                $searchColumns[] = 'mu.nama';
                $searchColumns[] = 'mu.nama_satpel';
                $searchColumns[] = 'pk.nama_umum_tercetak';
                $searchColumns[] = 'pk.kode_hs';
                $searchColumns[] = 'mn1.nama';
                $searchColumns[] = 'mn3.nama';
            }
            $this->applyGlobalSearch($f['search'], $searchColumns);
        }
    }

    public function getIds($f, $limit, $offset)
    {
        $hasSearch = !empty($f['search']);
        $this->db->select('p.id, MAX(p1a.tanggal) as tgl_urut', false)
            ->from('ptk p')
            ->join('pn_administrasi p1a', 'p.id = p1a.ptk_id', 'inner');

        if ($hasSearch) {
            $this->db->join('master_upt mu', 'p.kode_satpel = mu.id', 'left');
            $this->db->join('ptk_komoditas pk', "p.id = pk.ptk_id AND pk.deleted_at = '1970-01-01 08:00:00'", 'left');
            $this->db->join('master_negara mn1', 'p.negara_asal_id = mn1.id', 'left');
            $this->db->join('master_kota_kab mn3', 'p.kota_kab_asal_id = mn3.id', 'left');
        }

        $this->applyManualFilter($f, $hasSearch);

        $this->db->group_by('p.id');
        $this->db->order_by('tgl_urut', 'DESC');
        $this->db->limit($limit, $offset);

        $res = $this->db->get();
        return $res ? array_column($res->result_array(), 'id') : [];
    }

    public function countAll($f)
    {
        $hasSearch = !empty($f['search']);
        $this->db->select('COUNT(DISTINCT p.id) as total')->from('ptk p')
            ->join('pn_administrasi p1a', 'p.id = p1a.ptk_id', 'inner');

        if ($hasSearch) {
            $this->db->join('master_upt mu', 'p.kode_satpel = mu.id', 'left');
            $this->db->join('ptk_komoditas pk', "p.id = pk.ptk_id AND pk.deleted_at = '1970-01-01 08:00:00'", 'left');
            $this->db->join('master_negara mn1', 'p.negara_asal_id = mn1.id', 'left');
            $this->db->join('master_kota_kab mn3', 'p.kota_kab_asal_id = mn3.id', 'left');
        }

        $this->applyManualFilter($f, $hasSearch);
        $res = $this->db->get();
        return $res ? (int) ($res->row()->total ?? 0) : 0;
    }

    public function getByIds($ids)
    {
        if (empty($ids)) return [];
        $this->db->select("
            p.id, 
            ANY_VALUE(p.no_aju) AS no_aju, 
            ANY_VALUE(p.no_dok_permohonan) AS no_dok_permohonan, 
            ANY_VALUE(p.tgl_dok_permohonan) AS tgl_dok_permohonan,
            ANY_VALUE(p1a.nomor) AS no_p1a, 
            MAX(p1a.tanggal) AS tgl_p1a,
            ANY_VALUE(REPLACE(REPLACE(mu.nama, 'Balai Besar Karantina Hewan, Ikan, dan Tumbuhan', 'BBKHIT'), 'Balai Karantina Hewan, Ikan, dan Tumbuhan', 'BKHIT')) AS upt,
            ANY_VALUE(mu.nama_satpel) AS nama_satpel,
            ANY_VALUE(p.nama_pengirim) AS nama_pengirim, 
            ANY_VALUE(p.nama_penerima) AS nama_penerima,
            MAX(mn1.nama) AS asal, 
            MAX(mn2.nama) AS tujuan,
            MAX(mn3.nama) AS kota_asal, 
            MAX(mn4.nama) AS kota_tujuan,
            GROUP_CONCAT(DISTINCT pkom.nama_umum_tercetak SEPARATOR '<br>') AS komoditas
        ", false);

        $this->db->from('ptk p')
            ->join('pn_administrasi p1a', 'p.id = p1a.ptk_id', 'inner')
            ->join('ptk_komoditas pkom', 'p.id = pkom.ptk_id', 'left')
            ->join('master_upt mu', 'p.kode_satpel = mu.id', 'left')
            ->join('master_negara mn1', 'p.negara_asal_id = mn1.id', 'left')
            ->join('master_negara mn2', 'p.negara_tujuan_id = mn2.id', 'left')
            ->join('master_kota_kab mn3', 'p.kota_kab_asal_id = mn3.id', 'left')
            ->join('master_kota_kab mn4', 'p.kota_kab_tujuan_id = mn4.id', 'left')
            ->where_in('p.id', $ids)
            ->group_by('p.id')
            ->order_by('tgl_p1a', 'DESC');

        return $this->db->get()->result_array();
    }

    public function getFullData($f)
    {
        $ids = $this->getIds($f, 20000, 0); 
        if (empty($ids)) return [];
        $this->db->select("
            p.id, 
            p.no_aju, 
            p.no_dok_permohonan,
            p1a.nomor AS no_p1a, 
            p1a.tanggal AS tgl_p1a,
            mu.nama AS upt, 
            mu.nama_satpel,
            p.nama_pengirim, 
            p.nama_penerima,
            mn1.nama AS asal, 
            mn2.nama AS tujuan,
            mn3.nama AS kota_asal, 
            mn4.nama AS kota_tujuan,
            pkom.nama_umum_tercetak AS tercetak, 
            pkom.kode_hs AS hs,
            pkom.volume_lain AS volume, 
            ms.nama AS satuan
        ", false);

        $this->db->from('ptk p')
            ->join('pn_administrasi p1a', 'p.id = p1a.ptk_id', 'inner')
            ->join('ptk_komoditas pkom', 'p.id = pkom.ptk_id', 'inner')
            ->join('master_upt mu', 'p.kode_satpel = mu.id', 'left')
            ->join('master_satuan ms', 'pkom.satuan_lain_id = ms.id', 'left')
            ->join('master_negara mn1', 'p.negara_asal_id = mn1.id', 'left')
            ->join('master_negara mn2', 'p.negara_tujuan_id = mn2.id', 'left')
            ->join('master_kota_kab mn3', 'p.kota_kab_asal_id = mn3.id', 'left')
            ->join('master_kota_kab mn4', 'p.kota_kab_tujuan_id = mn4.id', 'left')
            ->where_in('p.id', $ids)
            ->order_by('p1a.tanggal', 'DESC')
            ->order_by('p.id', 'ASC');
        
        return $this->db->get()->result_array();
    }

    public function getExportByFilter($f)
    {
        return $this->getFullData($f);
    }
}