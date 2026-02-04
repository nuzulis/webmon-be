<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'models/BaseModelStrict.php';

class Nnc_model extends BaseModelStrict
{
    private function applyFilters($f)
    {
        $this->db->where([
            'p.is_verifikasi' => '1',
            'p.is_batal'      => '0',
            'p6.deleted_at'   => '1970-01-01 08:00:00',
            'p6.dokumen_karantina_id' => '32',
        ]);

        if (!empty($f['upt']) && !in_array(strtolower($f['upt']), ['all', 'semua'])) {
            if (strlen($f['upt']) <= 4) {
                $this->db->where("p.upt_id", $f['upt']);
            } else {
                $this->db->where("p.kode_satpel", $f['upt']);
            }
        }

        if (!empty($f['karantina'])) $this->db->where('p.jenis_karantina', $f['karantina']);
        if (!empty($f['lingkup'])) $this->db->where('p.jenis_permohonan', $f['lingkup']);
        
        if (!empty($f['start_date'])) $this->db->where('DATE(p6.tanggal) >=', $f['start_date']);
        if (!empty($f['end_date'])) $this->db->where('DATE(p6.tanggal) <=', $f['end_date']);


        if (!empty($f['search'])) {
            $q = $f['search'];
            $this->db->group_start();
                $this->db->like('p.no_aju', $q);
                $this->db->or_like('p.no_dok_permohonan', $q);
                $this->db->or_like('p6.nomor', $q);
                $this->db->or_like('p.nama_pengirim', $q);
                $this->db->or_like('p.nama_penerima', $q);
                $this->db->or_like('pkom.nama_umum_tercetak', $q);
            $this->db->group_end();
        }
    }

    public function getIds($f, $limit, $offset)
    {
        $this->db->select('p.id, MAX(p6.tanggal) AS last_tgl', false)
            ->from('ptk p')
            ->join('pn_penolakan p6', 'p.id = p6.ptk_id');

        if (!empty($f['search'])) {
            $this->db->join('ptk_komoditas pkom', 'p.id = pkom.ptk_id AND pkom.deleted_at = "1970-01-01 08:00:00"', 'left', FALSE);
        }

        $this->applyFilters($f);

        $this->db->group_by('p.id')->order_by('last_tgl', 'DESC');

        if ($limit < 5000) $this->db->limit($limit, $offset);

        $res = $this->db->get()->result_array();
        return array_column($res, 'id');
    }

    public function countAll($f)
    {
        $this->db->select('COUNT(DISTINCT p.id) AS total', false)
            ->from('ptk p')
            ->join('pn_penolakan p6', 'p.id = p6.ptk_id');
        if (!empty($f['search'])) {
            $this->db->join('ptk_komoditas pkom', 'p.id = pkom.ptk_id AND pkom.deleted_at = "1970-01-01 08:00:00"', 'left', FALSE);
        }

        $this->applyFilters($f);

        $res = $this->db->get()->row();
        return $res ? (int) $res->total : 0;
    }
    public function getByIds($ids, $karantina = 'T')
{
    if (empty($ids)) return [];

    $this->db->select("
        p.id, 
        ANY_VALUE(p.tssm_id) as tssm_id, 
        ANY_VALUE(p.no_aju) as no_aju, 
        ANY_VALUE(p.no_dok_permohonan) as no_dok_permohonan, 
        ANY_VALUE(p.tgl_dok_permohonan) as tgl_dok_permohonan, 
        ANY_VALUE(p.kode_satpel) as kode_satpel,
        ANY_VALUE(mu.nama_satpel) as nama_satpel, 
        ANY_VALUE(p.upt_id) as upt_id,
        ANY_VALUE(REPLACE(REPLACE(mt.nama, 'Balai Karantina Hewan, Ikan, dan Tumbuhan', 'BKHIT'), 
                'Balai Besar Karantina Hewan, Ikan, dan Tumbuhan', 'BBKHIT')) AS upt,
        ANY_VALUE(p6.tanggal) AS tgl_penolakan, 
        ANY_VALUE(p6.nomor) AS nomor_penolakan,
        ANY_VALUE(p6.information) as information, 
        ANY_VALUE(p6.consignment) as consignment, 
        ANY_VALUE(p6.consignment_detil) as consignment_detil, 
        ANY_VALUE(p6.kepada) as kepada,
        ANY_VALUE(COALESCE(p6.specify1, '')) AS specify1,
        ANY_VALUE(COALESCE(p6.specify2, '')) AS specify2,
        ANY_VALUE(COALESCE(p6.specify3, '')) AS specify3,
        ANY_VALUE(COALESCE(p6.specify4, '')) AS specify4,
        ANY_VALUE(COALESCE(p6.specify5, '')) AS specify5,
        ANY_VALUE(mp.nama) AS petugas, 
        ANY_VALUE(p6.dokumen_karantina_id) as dokumen_karantina_id,
        ANY_VALUE(p.is_verifikasi) as is_verifikasi, 
        ANY_VALUE(p.is_batal) as is_batal,
        ANY_VALUE(p.nama_pengirim) as nama_pengirim, 
        ANY_VALUE(p.nama_penerima) as nama_penerima,
        ANY_VALUE(p.jenis_karantina) as jenis_karantina, 
        ANY_VALUE(COALESCE(mn1.nama, '')) AS asal, 
        ANY_VALUE(COALESCE(mn2.nama, '')) AS tujuan,
        ANY_VALUE(COALESCE(mn3.nama, '')) AS kota_asal, 
        ANY_VALUE(COALESCE(mn4.nama, '')) AS kota_tujuan,
        GROUP_CONCAT(pkom.nama_umum_tercetak SEPARATOR '<br>') AS komoditas,
        GROUP_CONCAT(pkom.kode_hs SEPARATOR '<br>') AS hs,
        GROUP_CONCAT(pkom.volumeP6 SEPARATOR '<br>') AS volume,
        GROUP_CONCAT(ms.nama SEPARATOR '<br>') AS satuan
    ", false);

    $this->db->from('ptk p');
    $this->db->join('pn_penolakan p6', 'p.id = p6.ptk_id');
    $this->db->join('master_pegawai mp', 'p6.user_ttd_id = mp.id');
    $this->db->join('master_upt mu', 'p.kode_satpel = mu.id');
    $this->db->join('master_upt mt', 'p.upt_id = mt.id');
    $this->db->join('ptk_komoditas pkom', 'p.id = pkom.ptk_id AND pkom.deleted_at = "1970-01-01 08:00:00"', 'left', FALSE);
    $this->db->join('master_satuan ms', 'pkom.satuan_lain_id = ms.id', 'left');
    $this->db->join('master_negara mn1', 'p.negara_asal_id = mn1.id', 'left');
    $this->db->join('master_negara mn2', 'p.negara_tujuan_id = mn2.id', 'left');
    $this->db->join('master_kota_kab mn3', 'p.kota_kab_asal_id = mn3.id', 'left');
    $this->db->join('master_kota_kab mn4', 'p.kota_kab_tujuan_id = mn4.id', 'left');

    $this->db->where_in('p.id', $ids);
    $this->db->where('p6.dokumen_karantina_id', '32');
    $this->db->where('p6.deleted_at', '1970-01-01 08:00:00');
    $this->db->group_by('p.id, p6.id');

    return $this->db->get()->result_array();
}

    public function getExportData($f)
{
    $ids = $this->getIds($f, 100000, 0);
    if (empty($ids)) return [];

    $tables = ['H' => 'komoditas_hewan', 'I' => 'komoditas_ikan', 'T' => 'komoditas_tumbuhan'];

    $this->db->select("
        p.id, p.no_aju, p.no_dok_permohonan, p.tgl_dok_permohonan,
        REPLACE(REPLACE(mt.nama, 'Balai Karantina Hewan, Ikan, dan Tumbuhan', 'BKHIT'), 
                'Balai Besar Karantina Hewan, Ikan, dan Tumbuhan', 'BBKHIT') AS upt,
        mu.nama_satpel,
        p6.tanggal AS tgl_penolakan, p6.nomor AS nomor_penolakan,
        p6.information, p6.consignment, p6.consignment_detil, p6.kepada,
        p6.specify1, p6.specify2, p6.specify3, p6.specify4, p6.specify5,
        mp.nama AS petugas,
        p.nama_pengirim, p.nama_penerima,
        COALESCE(mn1.nama, '') AS asal, 
        COALESCE(mn2.nama, '') AS tujuan,
        COALESCE(mn3.nama, '') AS kota_asal, 
        COALESCE(mn4.nama, '') AS kota_tujuan,
        pkom.nama_umum_tercetak as komoditas,
        pkom.volumeP6 as volume,
        ms.nama as satuan,
        pkom.kode_hs
    ", false);

    $this->db->from('ptk p');
    $this->db->join('pn_penolakan p6', 'p.id = p6.ptk_id');
    $this->db->join('master_pegawai mp', 'p6.user_ttd_id = mp.id');
    $this->db->join('master_upt mu', 'p.kode_satpel = mu.id');
    $this->db->join('master_upt mt', 'p.upt_id = mt.id');
    $this->db->join('ptk_komoditas pkom', 'p.id = pkom.ptk_id AND pkom.deleted_at = "1970-01-01 08:00:00"', 'inner', FALSE);
    $this->db->join('master_satuan ms', 'pkom.satuan_lain_id = ms.id', 'left');
    $this->db->join('master_negara mn1', 'p.negara_asal_id = mn1.id', 'left');
    $this->db->join('master_negara mn2', 'p.negara_tujuan_id = mn2.id', 'left');
    $this->db->join('master_kota_kab mn3', 'p.kota_kab_asal_id = mn3.id', 'left');
    $this->db->join('master_kota_kab mn4', 'p.kota_kab_tujuan_id = mn4.id', 'left');

    $this->db->where_in('p.id', $ids);
    $this->db->where('p6.dokumen_karantina_id', '32');
    $this->db->where('p6.deleted_at', '1970-01-01 08:00:00');
    $this->db->order_by('p6.tanggal', 'DESC');
    $this->db->order_by('p.no_aju', 'ASC');

    return $this->db->get()->result_array();
}
}