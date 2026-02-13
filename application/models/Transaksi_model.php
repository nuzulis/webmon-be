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
        // 1. Base Filter (Sama dengan PeriksaAdmin)
        $this->db->where([
            'p.is_verifikasi' => '1',
            'p.is_batal'      => '0',
            'p.deleted_at'    => '1970-01-01 08:00:00'
        ]);

        // 2. Filter UPT
        if (!empty($f['upt']) && !in_array(strtolower($f['upt']), ['all', 'semua', 'undefined'])) {
            $field = (strlen($f['upt']) <= 4) ? "p.upt_id" : "p.kode_satpel";
            $this->db->where($field, $f['upt']);
        }

        // 3. Filter Karantina
        if (!empty($f['karantina']) && !in_array(strtolower($f['karantina']), ['all', ''])) {
            $this->db->where('p.jenis_karantina', substr(strtoupper($f['karantina']), 0, 1));
        }

        // 4. Filter Permohonan (Lingkup)
        $lingkup = $f['lingkup'] ?? ($f['permohonan'] ?? '');
        if (!empty($lingkup) && !in_array(strtolower($lingkup), ['all', 'semua', ''])) {
            $this->db->where('p.jenis_permohonan', strtoupper($lingkup));
        }

        // 5. Filter Tanggal (Gunakan applyDateFilter dari Base atau manual)
        if (!empty($f['start_date']) && !empty($f['end_date'])) {
            $this->db->where('p.tgl_dok_permohonan >=', $f['start_date']);
            $this->db->where('p.tgl_dok_permohonan <=', $f['end_date']);
        }

        // 6. Global Search (Semua Kolom seperti PeriksaAdmin)
        if (!empty($f['search'])) {
            $s = $this->db->escape_like_str($f['search']);
            $this->db->group_start();
                $this->db->like('p.no_aju', $s);
                $this->db->or_like('p.no_dok_permohonan', $s);
                $this->db->or_like('p.nama_pengirim', $s);
                $this->db->or_like('p.nama_penerima', $s);

                // Search Komoditas via EXISTS agar COUNT tetap akurat
                $this->db->or_where("EXISTS (
                    SELECT 1 FROM ptk_komoditas sk 
                    WHERE sk.ptk_id = p.id 
                    AND sk.nama_umum_tercetak LIKE '%$s%'
                )", null, false);

                // Search UPT
                $this->db->or_where("EXISTS (
                    SELECT 1 FROM master_upt mu WHERE mu.id = p.kode_satpel 
                    AND (mu.nama LIKE '%$s%' OR mu.nama_satpel LIKE '%$s%')
                )", null, false);
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

        $tableMap = [
            'H' => ['kom' => 'komoditas_hewan'],
            'I' => ['kom' => 'komoditas_ikan'],
            'T' => ['kom' => 'komoditas_tumbuhan']
        ];
        $target = $tableMap[$karantina] ?? $tableMap['T'];

        $this->db->select("
            p.id,
            IF(p.tssm_id IS NOT NULL, 'SSM', 'PTK') AS sumber,
            p.no_aju, p.tgl_aju,
            p.no_dok_permohonan AS no_dok, p.tgl_dok_permohonan AS tgl_dok,
            mu.nama AS upt, mu.nama_satpel AS satpel,
            p.nama_pengirim AS pengirim, p.nama_penerima AS penerima,
            CONCAT(COALESCE(mn1.nama,''), ' - ', COALESCE(mn3.nama,'')) AS asal_kota,
            CONCAT(COALESCE(mn2.nama,''), ' - ', COALESCE(mn4.nama,'')) AS tujuan_kota,
            p.nama_tempat_pemeriksaan AS tempat_periksa,
            p.tgl_pemeriksaan AS tgl_periksa,
            GROUP_CONCAT(pkom.nama_umum_tercetak SEPARATOR '<br>') AS komoditas,
            GROUP_CONCAT(pkom.kode_hs SEPARATOR '<br>') AS hs,
            GROUP_CONCAT(pkom.volume_lain SEPARATOR '<br>') AS volume,
            ms.nama AS satuan
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
                
        return $this->db->get()->result_array();
    }
}