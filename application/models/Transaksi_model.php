<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'models/BaseModelStrict.php';

class Transaksi_model extends BaseModelStrict
{
    private function applyFilters($f)
    {
        $this->db->where([
            'p.is_verifikasi' => '1',
            'p.is_batal'      => '0',
            'pkom.deleted_at' => '1970-01-01 08:00:00',
        ]);

        if (!empty($f['upt']) && $f['upt'] !== 'all') {
            $this->db->where('p.upt_id', $f['upt']);
        }

        if (!empty($f['karantina'])) {
            $this->db->where('p.jenis_karantina', $f['karantina']);
        }

         if (!empty($f['search'])) {
            $q = $f['search'];
            $this->db->group_start();
                $this->db->like('p.no_aju', $q);
                $this->db->or_like('p.no_dok_permohonan', $q);
                $this->db->or_like('p.nama_pengirim', $q);
                $this->db->or_like('p.nama_penerima', $q);
                $this->db->or_like('mu.nama', $q);
                $this->db->or_like('mu.nama_satpel', $q);
                $this->db->or_like('kom.nama', $q);
            $this->db->group_end();
        }
    }

    public function getIds($f, $limit, $offset)
    {
        $sortMap = [
            'sumber'   => 'sumber',
            'no_aju'   => 'p.no_aju',
            'tgl_aju'  => 'p.tgl_aju',
            'no_dok'   => 'p.no_dok_permohonan',
            'tgl_dok'  => 'p.tgl_dok_permohonan',
        ];
        
        $sortByKey = !empty($f['sort_by']) ? $f['sort_by'] : 'tgl_dok';
        $sortBy = $sortMap[$sortByKey] ?? 'p.tgl_dok_permohonan';
        $sortOrder = strtoupper($f['sort_order'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';

        $tableMap = [
            'H' => ['kom' => 'komoditas_hewan', 'klas' => 'klasifikasi_hewan'],
            'I' => ['kom' => 'komoditas_ikan', 'klas' => 'klasifikasi_ikan'],
            'T' => ['kom' => 'komoditas_tumbuhan', 'klas' => 'klasifikasi_tumbuhan']
        ];
        $target = $tableMap[$f['karantina'] ?? 'T'] ?? $tableMap['T'];

        if (empty($f['start_date'])) {
            $f['start_date'] = date('Y-m-d');
            $f['end_date'] = date('Y-m-d');
        }

        $this->db->select("
            p.id,
            IF(p.tssm_id IS NOT NULL, 'SSM', 'PTK') AS sumber
        ", false)
            ->from('ptk p')
            ->join('ptk_komoditas pkom', 'p.id = pkom.ptk_id')
            ->join('master_upt mu', 'p.kode_satpel = mu.id', 'left')
            ->join($target['kom'] . " kom", 'pkom.komoditas_id = kom.id', 'left');

        $this->applyDateFilter('p.tgl_dok_permohonan', $f);
        $this->applyFilters($f);

        $this->db->group_by('p.id, sumber, ' . $sortBy)
                ->order_by($sortBy, $sortOrder)
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

        $tableMap = [
            'H' => ['kom' => 'komoditas_hewan'],
            'I' => ['kom' => 'komoditas_ikan'],
            'T' => ['kom' => 'komoditas_tumbuhan']
        ];
        $target = $tableMap[$f['karantina'] ?? 'T'] ?? $tableMap['T'];

        $this->db->from('ptk p')
            ->join('ptk_komoditas pkom', 'p.id = pkom.ptk_id')
            ->join('master_upt mu', 'p.kode_satpel = mu.id', 'left')
            ->join($target['kom'] . " kom", 'pkom.komoditas_id = kom.id', 'left');

        $this->applyDateFilter('p.tgl_dok_permohonan', $f);
        $this->applyFilters($f);
        
        return $this->db->count_all_results();
    }
    public function getByIds($ids, $karantina = 'T', $sortBy = 'tgl_dok', $sortOrder = 'DESC')
    {
        if (empty($ids)) return [];
        $sortMap = [
            'sumber'   => 'sumber',
            'no_aju'   => 'p.no_aju',
            'tgl_aju'  => 'p.tgl_aju',
            'no_dok'   => 'no_dok',
            'tgl_dok'  => 'tgl_dok',
        ];
        $orderBy = $sortMap[$sortBy] ?? 'tgl_dok';
        $order = strtoupper($sortOrder) === 'ASC' ? 'ASC' : 'DESC';

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

        $this->db->group_by('p.id')
                ->order_by($orderBy, $order); 
                
        return $this->db->get()->result_array();
    }

   
}