<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'models/BaseModelStrict.php';

class Transaksi_model extends BaseModelStrict
{
        

    public function getIds($f, $limit, $offset)
    {
        $this->db->select('p.id')
            ->from('ptk p')
            ->join('ptk_komoditas pkom', 'p.id = pkom.ptk_id')
            ->where([
                'p.is_verifikasi' => '1',
                'p.is_batal'      => '0',
                'pkom.deleted_at' => '1970-01-01 08:00:00',
            ]);

        if (empty($f['start_date'])) {
            $f['start_date'] = date('Y-m-d');
            $f['end_date'] = date('Y-m-d');
        }
        $this->applyDateFilter('p.tgl_dok_permohonan', $f);

        if (!empty($f['upt']) && $f['upt'] !== 'all') {
            $this->db->where('p.upt_id', $f['upt']);
        }

        if (!empty($f['karantina'])) {
            $this->db->where('p.jenis_karantina', $f['karantina']);
        }

        $this->db->group_by('p.id')
                ->order_by('p.tgl_dok_permohonan', 'DESC')
                ->limit($limit, $offset);

        $res = $this->db->get()->result_array();
        return array_column($res, 'id');
    }

    public function countAll($f)
    {
        if (empty($f['start_date'])) {
            $f['start_date'] = date('Y-m-d');
            $f['end_date'] = date('Y-m-d');
        }

        $this->db->from('ptk p')
            ->join('ptk_komoditas pkom', 'p.id = pkom.ptk_id')
            ->where([
                'p.is_verifikasi' => '1',
                'p.is_batal'      => '0',
                'pkom.deleted_at' => '1970-01-01 08:00:00',
            ]);

        $this->applyDateFilter('p.tgl_dok_permohonan', $f);

        if (!empty($f['upt']) && $f['upt'] !== 'all') {
            $this->db->where('p.upt_id', $f['upt']);
        }
        
        return $this->db->count_all_results();
    }


public function getByIds($ids, $karantina = 'T')
{
    if (empty($ids)) return [];

    $tableMap = [
        'H' => ['kom' => 'komoditas_hewan', 'klas' => 'klasifikasi_hewan'],
        'I' => ['kom' => 'komoditas_ikan', 'klas' => 'klasifikasi_ikan'],
        'T' => ['kom' => 'komoditas_tumbuhan', 'klas' => 'klasifikasi_tumbuhan']
    ];

    $target = $tableMap[$karantina] ?? $tableMap['T'];

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
        GROUP_CONCAT(kom.nama SEPARATOR '<br>') AS komoditas,
        GROUP_CONCAT(pkom.kode_hs SEPARATOR '<br>') AS hs,
        GROUP_CONCAT(pkom.volume_lain SEPARATOR '<br>') AS volume,
        GROUP_CONCAT(ms.nama SEPARATOR '<br>') AS satuan
    ", false);

    $this->db->from('ptk p')
        ->join('master_upt mu', 'p.kode_satpel = mu.id')
        ->join('ptk_komoditas pkom', 'p.id = pkom.ptk_id')
        ->join('master_satuan ms', 'pkom.satuan_lain_id = ms.id', 'left')
        ->join('master_negara mn1', 'p.negara_asal_id = mn1.id', 'left')
        ->join('master_negara mn2', 'p.negara_tujuan_id = mn2.id', 'left')
        ->join('master_kota_kab mn3', 'p.kota_kab_asal_id = mn3.id', 'left')
        ->join('master_kota_kab mn4', 'p.kota_kab_tujuan_id = mn4.id', 'left')
        ->join($target['kom'] . " kom", 'pkom.komoditas_id = kom.id')
        ->join($target['klas'] . " klas", 'pkom.klasifikasi_id = klas.id');
    $quoted_ids = array_map(fn($id) => $this->db->escape($id), $ids);
    $this->db->where("p.id IN (" . implode(',', $quoted_ids) . ")", NULL, FALSE);

    $this->db->group_by('p.id')->order_by('p.tgl_dok_permohonan', 'DESC');
    return $this->db->get()->result_array();
}

   
}