<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'core/BaseModelStrict.php';

class PeriksaAdmin_model extends BaseModelStrict
{
    public function __construct()
    {
        parent::__construct();
    }

    public function getIds($f, $limit, $offset)
    {
        $this->db->select('p.id', false)
            ->from('ptk p');
        $this->db->where([
            'p.is_verifikasi' => '1',
            'p.is_batal'      => '0',
            'p.status_ptk'    => '1',
        ]);
        if (!empty($f['upt']) && !in_array(strtolower($f['upt']), ['semua', 'all', 'undefined'])) {
            $this->db->where('p.upt_id', $f['upt']);
        }
        
        if (!empty($f['karantina']) && !in_array(strtolower($f['karantina']), ['all', ''])) {
            $this->db->where('p.jenis_karantina', substr(strtoupper($f['karantina']), 0, 1));
        }
        
        if (!empty($f['permohonan']) && !in_array(strtolower($f['permohonan']), ['all', 'semua', 'undefined', ''])) {
            $this->db->where('p.jenis_permohonan', strtoupper($f['permohonan']));
        }
        if (!empty($f['start_date']) && !empty($f['end_date'])) {
            $start = $this->db->escape($f['start_date']);
            $end = $this->db->escape($f['end_date']);
            
            $this->db->where("EXISTS (
                SELECT 1 FROM pn_administrasi p1a 
                WHERE p1a.ptk_id = p.id 
                AND p1a.deleted_at = '1970-01-01 08:00:00'
                AND DATE(p1a.tanggal) >= $start
                AND DATE(p1a.tanggal) <= $end
            )", null, false);
        }
        $this->db->where("EXISTS (
            SELECT 1 FROM ptk_komoditas pkom 
            WHERE pkom.ptk_id = p.id 
            AND pkom.volumeP1 IS NULL 
            AND pkom.volumeP3 IS NULL 
            AND pkom.volumeP4 IS NULL 
            AND pkom.volumeP5 IS NULL 
            AND pkom.volumeP6 IS NULL 
            AND pkom.volumeP7 IS NULL 
            AND pkom.volumeP8 IS NULL
        )", null, false);
        $this->db->where("NOT EXISTS (
            SELECT 1 FROM pn_fisik_kesehatan fisik 
            WHERE fisik.ptk_id = p.id
        )", null, false);
        $this->db->order_by('p.id', 'DESC');
        $this->db->limit($limit, $offset);

        $res = $this->db->get();
        if (!$res) return [];
        
        return array_column($res->result_array(), 'id');
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
            ANY_VALUE(p1a.tanggal) AS tgl_p1a,
            ANY_VALUE(REPLACE(REPLACE(mu.nama, 'Balai Besar Karantina Hewan, Ikan, dan Tumbuhan', 'BBKHIT'), 'Balai Karantina Hewan, Ikan, dan Tumbuhan', 'BKHIT')) AS upt,
            ANY_VALUE(mu.nama_satpel) AS nama_satpel,
            ANY_VALUE(p.nama_pengirim) AS nama_pengirim, 
            ANY_VALUE(p.nama_penerima) AS nama_penerima,
            ANY_VALUE(mn1.nama) AS asal, 
            ANY_VALUE(mn3.nama) AS kota_asal,
            ANY_VALUE(mn2.nama) AS tujuan, 
            ANY_VALUE(mn4.nama) AS kota_tujuan,
            GROUP_CONCAT(DISTINCT pkom.nama_umum_tercetak SEPARATOR '<br>') AS komoditas
        ", false);

        $this->db->from('ptk p')
            ->join('pn_administrasi p1a', 'p.id = p1a.ptk_id')
            ->join('master_upt mu', 'p.kode_satpel = mu.id', 'left')
            ->join('ptk_komoditas pkom', 'p.id = pkom.ptk_id', 'left')
            ->join('master_negara mn1', 'p.negara_asal_id = mn1.id', 'left')
            ->join('master_negara mn2', 'p.negara_tujuan_id = mn2.id', 'left')
            ->join('master_kota_kab mn3', 'p.kota_kab_asal_id = mn3.id', 'left')
            ->join('master_kota_kab mn4', 'p.kota_kab_tujuan_id = mn4.id', 'left');

        $this->db->where_in('p.id', $ids);
        $this->db->group_by('p.id');
        $this->db->order_by('tgl_p1a', 'DESC');

        return $this->db->get()->result_array();
    }

    public function countAll($f)
    {
        $this->db->select('COUNT(*) as total', false)
            ->from('ptk p');

        $this->db->where([
            'p.is_verifikasi' => '1',
            'p.is_batal'      => '0',
            'p.status_ptk'    => '1',
        ]);

        if (!empty($f['upt']) && !in_array(strtolower($f['upt']), ['semua', 'all', 'undefined'])) {
            $this->db->where('p.upt_id', $f['upt']);
        }
        
        if (!empty($f['karantina']) && !in_array(strtolower($f['karantina']), ['all', ''])) {
            $this->db->where('p.jenis_karantina', substr(strtoupper($f['karantina']), 0, 1));
        }
        
        if (!empty($f['permohonan']) && !in_array(strtolower($f['permohonan']), ['all', 'semua', 'undefined', ''])) {
            $this->db->where('p.jenis_permohonan', strtoupper($f['permohonan']));
        }

        if (!empty($f['start_date']) && !empty($f['end_date'])) {
            $start = $this->db->escape($f['start_date']);
            $end = $this->db->escape($f['end_date']);
            
            $this->db->where("EXISTS (
                SELECT 1 FROM pn_administrasi p1a 
                WHERE p1a.ptk_id = p.id 
                AND p1a.deleted_at = '1970-01-01 08:00:00'
                AND DATE(p1a.tanggal) >= $start
                AND DATE(p1a.tanggal) <= $end
            )", null, false);
        }

        $this->db->where("EXISTS (
            SELECT 1 FROM ptk_komoditas pkom 
            WHERE pkom.ptk_id = p.id 
            AND pkom.volumeP1 IS NULL 
            AND pkom.volumeP3 IS NULL 
            AND pkom.volumeP4 IS NULL 
            AND pkom.volumeP5 IS NULL 
            AND pkom.volumeP6 IS NULL 
            AND pkom.volumeP7 IS NULL 
            AND pkom.volumeP8 IS NULL
        )", null, false);

        $this->db->where("NOT EXISTS (
            SELECT 1 FROM pn_fisik_kesehatan fisik 
            WHERE fisik.ptk_id = p.id
        )", null, false);

        $res = $this->db->get()->row();
        return $res ? (int) $res->total : 0;
    }
    public function getFullData($f)
    {
        $ids = $this->getIds($f, 10000, 0);
        
        if (empty($ids)) return [];

        $this->db->select("
            p.id, 
            p.no_aju, 
            p.no_dok_permohonan, 
            p.tgl_dok_permohonan,
            p1a.nomor AS no_p1a, 
            p1a.tanggal AS tgl_p1a,
            mu.nama AS upt, 
            mu.nama_satpel,
            p.nama_pengirim, 
            p.nama_penerima,
            mn1.nama AS asal, 
            mn3.nama AS kota_asal,
            mn2.nama AS tujuan, 
            mn4.nama AS kota_tujuan,
            pkom.nama_umum_tercetak AS tercetak,
            pkom.kode_hs AS hs,
            pkom.volume_lain AS volume,
            ms.nama AS satuan
        ", false);

        $this->db->from('ptk p')
            ->join('pn_administrasi p1a', 'p.id = p1a.ptk_id')
            ->join('ptk_komoditas pkom', 'p.id = pkom.ptk_id')
            ->join('master_upt mu', 'p.kode_satpel = mu.id', 'left')
            ->join('master_satuan ms', 'pkom.satuan_lain_id = ms.id', 'left')
            ->join('master_negara mn1', 'p.negara_asal_id = mn1.id', 'left')
            ->join('master_negara mn2', 'p.negara_tujuan_id = mn2.id', 'left')
            ->join('master_kota_kab mn3', 'p.kota_kab_asal_id = mn3.id', 'left')
            ->join('master_kota_kab mn4', 'p.kota_kab_tujuan_id = mn4.id', 'left');

        $this->db->where_in('p.id', $ids);
        $this->db->order_by('p1a.tanggal', 'DESC');

        return $this->db->get()->result_array();
    }

    public function getList($f, $limit, $offset)
    {
        $ids = $this->getIds($f, $limit, $offset);
        return $this->getByIds($ids);
    }

    public function getExportByFilter($f)
    {
        return $this->getFullData($f);
    }
}