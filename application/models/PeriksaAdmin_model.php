<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'models/BaseModelStrict.php';

class PeriksaAdmin_model extends BaseModelStrict
{
    public function getList($f, $limit, $offset)
    {
        $this->db->select("p.id");
        $this->db->select("MAX(DATE(p1a.tanggal)) as sort_date", false); 
        $this->db->from('ptk p');
        $this->db->join('pn_administrasi p1a', 'p.id = p1a.ptk_id');
        $this->db->join('ptk_komoditas pkom', 'p.id = pkom.ptk_id');
        $this->db->join('pn_fisik_kesehatan fisik', 'p.id = fisik.ptk_id', 'left');

        $this->db->where([
            'p.is_verifikasi' => '1',
            'p.is_batal'      => '0',
            'p.status_ptk'    => '1',
            'p1a.deleted_at'  => '1970-01-01 08:00:00',
            'fisik.ptk_id'    => NULL,
            'pkom.volumeP1'   => NULL,
            'pkom.volumeP3'   => NULL,
            'pkom.volumeP4'   => NULL,
            'pkom.volumeP5'   => NULL,
            'pkom.volumeP6'   => NULL,
            'pkom.volumeP7'   => NULL,
            'pkom.volumeP8'   => NULL
        ]);

        $this->applyFilters($f);
        
        $this->db->group_by('p.id');
        $this->db->order_by('sort_date', 'DESC');
        $this->db->limit($limit, $offset);
        $subquerySql = $this->db->get_compiled_select();
        $this->db->reset_query();

        $mainSql = "
            SELECT 
                p.id, p.no_aju, p.no_dok_permohonan, p.tgl_dok_permohonan,
                MAX(p1a.nomor) AS no_p1a, 
                MAX(p1a.tanggal) AS tgl_p1a,
                REPLACE(REPLACE(MAX(mu.nama), 'Balai Besar Karantina Hewan, Ikan, dan Tumbuhan', 'BBKHIT'), 'Balai Karantina Hewan, Ikan, dan Tumbuhan', 'BKHIT') AS upt,
                MAX(mu.nama_satpel) AS nama_satpel,
                MAX(p.nama_pengirim) AS nama_pengirim, 
                MAX(p.nama_penerima) AS nama_penerima,
                MAX(mn1.nama) AS asal, 
                MAX(mn3.nama) AS kota_asal,
                MAX(mn2.nama) AS tujuan, 
                MAX(mn4.nama) AS kota_tujuan,
                GROUP_CONCAT(DISTINCT pkom.nama_umum_tercetak SEPARATOR '<br>') AS komoditas
            FROM ($subquerySql) AS filtered
            JOIN ptk p ON filtered.id = p.id
            JOIN pn_administrasi p1a ON p.id = p1a.ptk_id
            JOIN master_upt mu ON p.kode_satpel = mu.id
            LEFT JOIN ptk_komoditas pkom ON p.id = pkom.ptk_id
            LEFT JOIN master_negara mn1 ON p.negara_asal_id = mn1.id
            LEFT JOIN master_negara mn2 ON p.negara_tujuan_id = mn2.id
            LEFT JOIN master_kota_kab mn3 ON p.kota_kab_asal_id = mn3.id
            LEFT JOIN master_kota_kab mn4 ON p.kota_kab_tujuan_id = mn4.id
            GROUP BY p.id
            ORDER BY tgl_p1a DESC
        ";

        return $this->db->query($mainSql)->result_array();
    }

    public function countAll($f)
    {
        $this->db->select('COUNT(DISTINCT p.id) as total')
            ->from('ptk p')
            ->join('pn_administrasi p1a', 'p.id = p1a.ptk_id')
            ->join('ptk_komoditas pkom', 'p.id = pkom.ptk_id')
            ->join('master_upt mu', 'p.kode_satpel = mu.id')
            ->join('pn_fisik_kesehatan fisik', 'p.id = fisik.ptk_id', 'left');

        $this->db->where([
            'p.is_verifikasi' => '1',
            'p.is_batal'      => '0',
            'p.status_ptk'    => '1',
            'p1a.deleted_at'  => '1970-01-01 08:00:00',
            'fisik.ptk_id'    => NULL,
            'pkom.volumeP1'   => NULL,
            'pkom.volumeP3'   => NULL,
            'pkom.volumeP4'   => NULL,
            'pkom.volumeP5'   => NULL,
            'pkom.volumeP6'   => NULL,
            'pkom.volumeP7'   => NULL,
            'pkom.volumeP8'   => NULL
        ]);

        $this->applyFilters($f);
        
        $res = $this->db->get()->row();
        return $res ? (int) $res->total : 0;
    }

    private function applyFilters($f)
    {
        if (!empty($f['upt']) && !in_array(strtolower($f['upt']), ['semua', 'all', 'undefined'])) {
            $this->db->where('p.upt_id', $f['upt']);
        }

        if (!empty($f['karantina']) && !in_array(strtolower($f['karantina']), ['all', ''])) {
            $this->db->where('p.jenis_karantina', substr(strtoupper($f['karantina']), 0, 1));
        }

        if (!empty($f['lingkup']) && !in_array(strtolower($f['lingkup']), ['all', 'semua', 'undefined', ''])) {
            $this->db->where('p.jenis_permohonan', strtoupper($f['lingkup']));
        }

        if (!empty($f['start_date'])) {
            $this->db->where('DATE(p1a.tanggal) >=', $f['start_date']);
        }
        if (!empty($f['end_date'])) {
            $this->db->where('DATE(p1a.tanggal) <=', $f['end_date']);
        }
    }


    public function getExportByFilter($f)
    {
        $this->db->select("
            p.id, p.no_aju, p.no_dok_permohonan, p.tgl_dok_permohonan,
            p1a.nomor AS no_p1a, p1a.tanggal AS tgl_p1a,
            mu.nama AS upt, mu.nama_satpel,
            p.nama_pengirim, p.nama_penerima,
            mn1.nama AS asal, mn3.nama AS kota_asal,
            mn2.nama AS tujuan, mn4.nama AS kota_tujuan,
            pkom.nama_umum_tercetak AS tercetak,
            pkom.kode_hs AS hs,
            pkom.volume_lain AS volume,
            ms.nama AS satuan
        ", false);

        $this->db->from('ptk p')
            ->join('pn_administrasi p1a', 'p.id = p1a.ptk_id')
            ->join('ptk_komoditas pkom', 'p.id = pkom.ptk_id')
            ->join('master_upt mu', 'p.kode_satpel = mu.id')
            ->join('master_satuan ms', 'pkom.satuan_lain_id = ms.id')
            ->join('master_negara mn1', 'p.negara_asal_id = mn1.id', 'left')
            ->join('master_negara mn2', 'p.negara_tujuan_id = mn2.id', 'left')
            ->join('master_kota_kab mn3', 'p.kota_kab_asal_id = mn3.id', 'left')
            ->join('master_kota_kab mn4', 'p.kota_kab_tujuan_id = mn4.id', 'left')
            ->join('pn_fisik_kesehatan fisik', 'p.id = fisik.ptk_id', 'left');

        $this->db->where([
            'p.is_verifikasi' => '1', 'p.is_batal' => '0', 'p.status_ptk' => '1',
            'p1a.deleted_at'  => '1970-01-01 08:00:00', 'fisik.ptk_id' => NULL,
            'pkom.volumeP1' => NULL, 'pkom.volumeP3' => NULL, 'pkom.volumeP4' => NULL,
            'pkom.volumeP5' => NULL, 'pkom.volumeP6' => NULL, 'pkom.volumeP7' => NULL, 'pkom.volumeP8' => NULL
        ]);

        $this->applyFilters($f);
        return $this->db->order_by('p1a.tanggal', 'DESC')->limit(10000)->get()->result_array();
    }

    public function getIds($f, $limit, $offset) { return []; }
    public function getByIds($ids) { return []; }
}