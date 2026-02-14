<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'core/BaseModelStrict.php';

class Transaksi_model extends BaseModelStrict
{
    public function __construct()
    {
        parent::__construct();
    }

    private function applyManualFilter($f)
{
    $this->db->where([
        'p.is_verifikasi' => '1',
        'p.is_batal'      => '0',
        'p.deleted_at'    => '1970-01-01 08:00:00'
    ]);
    if (!empty($f['upt']) && !in_array(strtolower($f['upt']), ['all', 'semua', 'undefined'])) {
        if (strlen($f['upt']) <= 4) {
            $this->db->group_start()
                     ->where("p.upt_id", $f['upt'])
                     ->or_like("p.kode_satpel", $f['upt'], 'after')
                     ->group_end();
        } else {
            $this->db->where("p.kode_satpel", $f['upt']);
        }
    }

    if (!empty($f['karantina']) && !in_array(strtolower($f['karantina']), ['all', ''])) {
        $this->db->where('p.jenis_karantina', substr(strtoupper($f['karantina']), 0, 1));
    }
    $lingkup = $f['lingkup'] ?? ($f['permohonan'] ?? '');
    if (!empty($lingkup) && !in_array(strtolower($lingkup), ['all', 'semua', ''])) {
        $this->db->where('p.jenis_permohonan', strtoupper($lingkup));
    }
if (!empty($f['start_date']) && !empty($f['end_date'])) {
    $this->db->where('p.tgl_dok_permohonan >=', $f['start_date'] . ' 00:00:00');
    $this->db->where('p.tgl_dok_permohonan <=', $f['end_date'] . ' 23:59:59');
}
    if (!empty($f['search'])) {
        $search = trim($f['search']);
        $s = $this->db->escape_like_str($search);
        
        $this->db->group_start();
            $this->db->like('p.no_aju', $search);
            $this->db->or_like('p.no_dok_permohonan', $search);
            $this->db->or_like('p.nama_pengirim', $search);
            $this->db->or_like('p.nama_penerima', $search);
            $this->db->or_like('p.nama_tempat_pemeriksaan', $search);
            $this->db->or_where("EXISTS (
                SELECT 1 FROM ptk_komoditas sk
                WHERE sk.ptk_id = p.id AND sk.deleted_at = '1970-01-01 08:00:00'
                AND (sk.nama_umum_tercetak LIKE '%{$s}%' OR sk.kode_hs LIKE '%{$s}%' OR sk.volume_lain LIKE '%{$s}%')
            )", null, false);
            $this->db->or_where("EXISTS (
                SELECT 1 FROM master_upt mu WHERE mu.id = p.kode_satpel
                AND (mu.nama LIKE '%{$s}%' OR mu.nama_satpel LIKE '%{$s}%')
            )", null, false);
            $this->db->or_where("EXISTS (SELECT 1 FROM master_negara mn WHERE mn.id = p.negara_asal_id AND mn.nama LIKE '%{$s}%')", null, false);
            $this->db->or_where("EXISTS (SELECT 1 FROM master_negara mn2 WHERE mn2.id = p.negara_tujuan_id AND mn2.nama LIKE '%{$s}%')", null, false);
            $this->db->or_where("EXISTS (SELECT 1 FROM master_kota_kab mk WHERE mk.id = p.kota_kab_asal_id AND mk.nama LIKE '%{$s}%')", null, false);
            $this->db->or_where("EXISTS (SELECT 1 FROM master_kota_kab mk2 WHERE mk2.id = p.kota_kab_tujuan_id AND mk2.nama LIKE '%{$s}%')", null, false);
        $this->db->group_end();
    }
}

    public function getIds($f, $limit, $offset)
    {
        $this->db->select('p.id', false)->from('ptk p');
        
        $this->applyManualFilter($f);

        $sortMap = [
            'no_aju'  => 'p.no_aju',
            'tgl_aju' => 'p.tgl_aju',
            'no_dok'  => 'p.no_dok_permohonan',
            'tgl_dok' => 'p.tgl_dok_permohonan',
        ];

        $this->applySorting(
            $f['sort_by'] ?? null,
            $f['sort_order'] ?? 'DESC',
            $sortMap,
            ['p.tgl_dok_permohonan', 'DESC']
        );

        $this->db->group_by('p.id');
        $this->db->limit($limit, $offset);

        $res = $this->db->get();
        return $res ? array_column($res->result_array(), 'id') : [];
    }

    public function countAll($f)
    {
        $this->db->select('COUNT(DISTINCT p.id) as total')->from('ptk p');
        $this->applyManualFilter($f);

        $res = $this->db->get()->row();
        return $res ? (int) $res->total : 0;
    }

    public function getByIds($ids, $karantina = 'T')
{
    if (empty($ids)) return [];
    $this->db->select("
        p.id,
        ANY_VALUE(IF(p.tssm_id IS NOT NULL, 'SSM', 'PTK')) AS sumber,
        ANY_VALUE(p.no_aju) AS no_aju, 
        ANY_VALUE(p.tgl_aju) AS tgl_aju,
        ANY_VALUE(p.no_dok_permohonan) AS no_dok, 
        ANY_VALUE(p.tgl_dok_permohonan) AS tgl_dok,
        ANY_VALUE(mu.nama) AS upt, 
        ANY_VALUE(mu.nama_satpel) AS satpel,
        ANY_VALUE(p.nama_pengirim) AS pengirim, 
        ANY_VALUE(p.nama_penerima) AS penerima,
        ANY_VALUE(CONCAT(COALESCE(mn1.nama,''), ' - ', COALESCE(mn3.nama,''))) AS asal_kota,
        ANY_VALUE(CONCAT(COALESCE(mn2.nama,''), ' - ', COALESCE(mn4.nama,''))) AS tujuan_kota,
        ANY_VALUE(p.nama_tempat_pemeriksaan) AS tempat_periksa,
        ANY_VALUE(p.tgl_pemeriksaan) AS tgl_periksa,
        GROUP_CONCAT(pkom.nama_umum_tercetak SEPARATOR '<br>') AS komoditas,
        GROUP_CONCAT(pkom.kode_hs SEPARATOR '<br>') AS hs,
        GROUP_CONCAT(pkom.volume_lain SEPARATOR '<br>') AS volume,
        GROUP_CONCAT(COALESCE(ms.nama, '-') SEPARATOR '<br>') AS satuan
    ", false);

    $this->db->from('ptk p')
        ->join('master_upt mu', 'p.kode_satpel = mu.id', 'left')
        ->join('ptk_komoditas pkom', 'p.id = pkom.ptk_id', 'left')
        ->join('master_satuan ms', 'pkom.satuan_lain_id = ms.id', 'left')
        ->join('master_negara mn1', 'p.negara_asal_id = mn1.id', 'left')
        ->join('master_negara mn2', 'p.negara_tujuan_id = mn2.id', 'left')
        ->join('master_kota_kab mn3', 'p.kota_kab_asal_id = mn3.id', 'left')
        ->join('master_kota_kab mn4', 'p.kota_kab_tujuan_id = mn4.id', 'left')
        ->where_in('p.id', $ids)
        ->group_by('p.id')
        ->order_by('p.tgl_dok_permohonan', 'DESC');
            
    $query = $this->db->get();
    if (!$query) {
        return [];
    }
            
    return $query->result_array();
}
}