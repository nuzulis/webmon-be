<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'core/BaseModelStrict.php';

class PeriksaFisik_model extends BaseModelStrict
{
    public function __construct()
    {
        parent::__construct();
    }
    private function getTableSuffix($karantina) {
        $k = strtolower(substr($karantina ?? '', 0, 1));
        if ($k == 'h') return 'kh';
        if ($k == 't') return 'kt';
        return 'ki';
    }

    public function getIds($f, $limit, $offset)
    {
        $suffix = $this->getTableSuffix($f['karantina'] ?? '');
        $pelepasanTable = "pn_pelepasan_" . $suffix;
        $this->db->select('p.id, MAX(p1b.tanggal) as max_tanggal', false);
        $this->db->from('ptk p');
        $this->db->join('pn_fisik_kesehatan p1b', 'p.id = p1b.ptk_id');
        $this->db->join('ptk_komoditas pkom', 'p.id = pkom.ptk_id');
        $this->db->join('master_upt mu', 'p.kode_satpel = mu.id', 'left');
        $this->db->join("$pelepasanTable p8", 'p.id = p8.ptk_id', 'left');

        $this->applyManualFilter($f, $suffix);

        $sortMap = [
            'no_aju'             => 'p.no_aju',
            'no_dok_permohonan'  => 'p.no_dok_permohonan',
            'tgl_dok_permohonan' => 'p.tgl_dok_permohonan',
            'no_p1b'             => 'MAX(p1b.nomor)',
            'tgl_p1b'            => 'max_tanggal',
            'upt'                => 'MAX(mu.nama)',
            'nama_satpel'        => 'MAX(mu.nama_satpel)',
            'nama_pengirim'      => 'p.nama_pengirim',
            'nama_penerima'      => 'p.nama_penerima',
        ];

        $this->applySorting(
            $f['sort_by'] ?? null,
            $f['sort_order'] ?? 'DESC',
            $sortMap,
            ['max_tanggal', 'DESC']
        );

        $this->db->group_by('p.id');
        $this->db->limit($limit, $offset);

        $query = $this->db->get();
        if (!$query) return [];

        return array_column($query->result_array(), 'id');
    }

    public function getByIds($ids)
    {
        if (empty($ids)) return [];
        
        $this->db->select("
            p.id, 
            p.no_aju, 
            p.no_dok_permohonan, 
            p.tgl_dok_permohonan,
            MAX(p1b.nomor) AS no_p1b, 
            MAX(p1b.tanggal) AS tgl_p1b,
            REPLACE(REPLACE(MAX(mu.nama), 'Balai Besar Karantina Hewan, Ikan, dan Tumbuhan', 'BBKHIT'), 'Balai Karantina Hewan, Ikan, dan Tumbuhan', 'BKHIT') AS upt,
            MAX(mu.nama_satpel) AS nama_satpel,
            p.nama_pengirim, 
            p.nama_penerima,
            MAX(mn1.nama) AS asal, 
            MAX(mn3.nama) AS kota_asal,
            MAX(mn2.nama) AS tujuan, 
            MAX(mn4.nama) AS kota_tujuan,
            GROUP_CONCAT(DISTINCT pkom.nama_umum_tercetak SEPARATOR '<br>') AS komoditas
        ", false);

        $this->db->from('ptk p');
        $this->db->join('pn_fisik_kesehatan p1b', 'p.id = p1b.ptk_id');
        $this->db->join('ptk_komoditas pkom', 'p.id = pkom.ptk_id', 'left');
        $this->db->join('master_upt mu', 'p.kode_satpel = mu.id', 'left');
        $this->db->join('master_negara mn1', 'p.negara_asal_id = mn1.id', 'left');
        $this->db->join('master_negara mn2', 'p.negara_tujuan_id = mn2.id', 'left');
        $this->db->join('master_kota_kab mn3', 'p.kota_kab_asal_id = mn3.id', 'left');
        $this->db->join('master_kota_kab mn4', 'p.kota_kab_tujuan_id = mn4.id', 'left');

        $this->db->where_in('p.id', $ids);
        $this->db->group_by('p.id');
        $this->db->order_by('tgl_p1b', 'DESC');

        return $this->db->get()->result_array();
    }

    public function countAll($f)
    {
        $suffix = $this->getTableSuffix($f['karantina'] ?? '');
        $pelepasanTable = "pn_pelepasan_" . $suffix;

        $this->db->select('COUNT(DISTINCT p.id) as total');
        $this->db->from('ptk p');
        $this->db->join('pn_fisik_kesehatan p1b', 'p.id = p1b.ptk_id');
        $this->db->join('ptk_komoditas pkom', 'p.id = pkom.ptk_id');
        $this->db->join('master_upt mu', 'p.kode_satpel = mu.id', 'left');
        $this->db->join("$pelepasanTable p8", 'p.id = p8.ptk_id', 'left');

        $this->applyManualFilter($f, $suffix);

        $res = $this->db->get()->row();
        return $res ? (int) $res->total : 0;
    }

    public function getList($f, $limit, $offset)
    {
        $ids = $this->getIds($f, $limit, $offset);
        return $this->getByIds($ids);
    }

    public function getFullData($f)
    {
        $ids = $this->getIds($f, 100000, 0);
        if (empty($ids)) return [];
        $this->db->select("
            p.id, 
            p.no_aju, 
            p.no_dok_permohonan, 
            p.tgl_dok_permohonan,
            p1b.nomor AS no_p1b, 
            p1b.tanggal AS tgl_p1b,
            mu.nama AS upt, 
            mu.nama_satpel,
            p.nama_pengirim, 
            p.nama_penerima,
            mn1.nama AS asal, 
            mn3.nama AS kota_asal,
            mn2.nama AS tujuan, 
            mn4.nama AS kota_tujuan,
            pkom.nama_umum_tercetak, 
            pkom.kode_hs AS hs,
            pkom.volume_lain AS volume, 
            ms.nama AS satuan
        ", false);

        $this->db->from('ptk p');
        $this->db->join('pn_fisik_kesehatan p1b', 'p.id = p1b.ptk_id');
        $this->db->join('ptk_komoditas pkom', 'p.id = pkom.ptk_id');
        $this->db->join('master_upt mu', 'p.kode_satpel = mu.id', 'left');
        $this->db->join('master_satuan ms', 'pkom.satuan_lain_id = ms.id', 'left');
        $this->db->join('master_negara mn1', 'p.negara_asal_id = mn1.id', 'left');
        $this->db->join('master_negara mn2', 'p.negara_tujuan_id = mn2.id', 'left');
        $this->db->join('master_kota_kab mn3', 'p.kota_kab_asal_id = mn3.id', 'left');
        $this->db->join('master_kota_kab mn4', 'p.kota_kab_tujuan_id = mn4.id', 'left');
        $this->db->where_in('p.id', $ids);
        $this->db->order_by('p1b.tanggal', 'DESC');
        $this->db->order_by('p.id', 'ASC');

        return $this->db->get()->result_array();
    }
    public function getExportByFilter($f)
    {
        return $this->getFullData($f);
    }

    private function applyManualFilter($f, $suffix)
    {
        $this->db->where([
            'p.is_verifikasi' => '1',
            'p.is_batal'      => '0',
            'p1b.deleted_at'  => '1970-01-01 08:00:00',
            'p.upt_id !='     => '1000',
            'p8.ptk_id'       => NULL, 
            'pkom.volumeP1 !=' => NULL,
            'pkom.volumeP3'    => NULL,
            'pkom.volumeP4'    => NULL,
            'pkom.volumeP5'    => NULL,
            'pkom.volumeP6'    => NULL,
            'pkom.volumeP7'    => NULL,
            'pkom.volumeP8'    => NULL,
        ]);
        if (!empty($f['upt']) && !in_array(strtolower($f['upt']), ['all', 'semua', 'undefined'])) {
            if (strlen($f['upt']) <= 4) {
                $this->db->where("p.upt_id", $f['upt']);
            } else {
                $this->db->where("p.kode_satpel", $f['upt']);
            }
        }
        if (!empty($f['karantina']) && !in_array(strtolower($f['karantina']), ['all', 'semua', ''])) {
            $this->db->where('p.jenis_karantina', substr(strtoupper($f['karantina']), 0, 1));
        }
        $lingkup = $f['lingkup'] ?? ($f['permohonan'] ?? '');
        if (!empty($lingkup) && !in_array(strtolower($lingkup), ['all', 'semua', 'undefined', ''])) {
            $this->db->where('p.jenis_permohonan', strtoupper($lingkup));
        }
        if (!empty($f['start_date'])) {
            $this->db->where('DATE(p1b.tanggal) >=', $f['start_date']);
        }
        if (!empty($f['end_date'])) {
            $this->db->where('DATE(p1b.tanggal) <=', $f['end_date']);
        }
        if (!empty($f['search'])) {
            $search = trim($f['search']);
            $this->db->group_start();
                $this->db->like('p.no_aju', $search);
                $this->db->or_like('p.no_dok_permohonan', $search);
                $this->db->or_like('p1b.nomor', $search);
                $this->db->or_like('mu.nama', $search);
                $this->db->or_like('mu.nama_satpel', $search);
                $this->db->or_like('p.nama_pengirim', $search);
                $this->db->or_like('p.nama_penerima', $search);
                $this->db->or_like('pkom.nama_umum_tercetak', $search);
            $this->db->group_end();
        }
    }
}