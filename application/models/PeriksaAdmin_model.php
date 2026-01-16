<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'models/BaseModelStrict.php';

class PeriksaAdmin_model extends BaseModelStrict
{
    /* =====================================================
     * STEP 1 — AMBIL ID SAJA (LIST AMAN & CEPAT)
     * ===================================================== */
    public function getIds($filter, $limit, $offset)
    {
        $this->db
            ->select('p.id')
            ->from('ptk p');

        // Filter umum (UPT, Karantina, Permohonan)
        $this->applyCommonFilter($filter);

        // Filter tanggal (opsional)
        $this->applyDateFilter('p.created_at', $filter);

        return array_column(
            $this->db
                ->limit($limit, $offset)
                ->get()
                ->result_array(),
            'id'
        );
    }

    public function getByIds($ids, $is_export = false)
    {
        if (empty($ids)) return [];

        $this->db->select("
            p.id, p.no_aju, p.no_dok_permohonan, p.tgl_dok_permohonan,
            p1a.nomor AS no_p1a, p1a.tanggal AS tgl_p1a,
            REPLACE(REPLACE(mu.nama, 'Balai Karantina Hewan, Ikan, dan Tumbuhan','BKHIT'), 'Balai Besar Karantina Hewan, Ikan, dan Tumbuhan','BBKHIT') AS upt,
            mu.nama_satpel AS nama_satpel,
            p.nama_pengirim, p.nama_penerima,
            mn1.nama AS asal, mn3.nama AS kota_asal,
            mn2.nama AS tujuan, mn4.nama AS kota_tujuan
        ", false);

        // Jika export, kita ambil detail komoditasnya
        if ($is_export) {
            $this->db->select("
                pkom.nama_umum_tercetak AS tercetak,
                pkom.volume_lain AS volume,
                ms.nama AS satuan,
                pkom.kode_hs AS hs
            ");
            $this->db->join('ptk_komoditas pkom', 'p.id = pkom.ptk_id');
            $this->db->join('master_satuan ms', 'pkom.satuan_lain_id = ms.id', 'left');
            $this->db->where('pkom.deleted_at', '1970-01-01 08:00:00');
        }

        $this->db->from('ptk p')
            ->join('pn_administrasi p1a', 'p.id = p1a.ptk_id')
            ->join('master_upt mu', 'p.kode_satpel = mu.id')
            ->join('master_negara mn1', 'p.negara_asal_id = mn1.id', 'left')
            ->join('master_negara mn2', 'p.negara_tujuan_id = mn2.id', 'left')
            ->join('master_kota_kab mn3', 'p.kota_kab_asal_id = mn3.id', 'left')
            ->join('master_kota_kab mn4', 'p.kota_kab_tujuan_id = mn4.id', 'left')
            ->where_in('p.id', $ids);

        if (!$is_export) {
            $this->db->group_by('p.id');
        }
        
        $this->db->order_by('p1a.tanggal', 'DESC');
        return $this->db->get()->result_array();
    }
    
    public function countAll($filter)
    {
        $this->db
            ->select('COUNT(DISTINCT p.id) AS total', false)
            ->from('ptk p')
            ->join('pn_administrasi p1a', 'p.id = p1a.ptk_id')
            ->join('ptk_komoditas kom', 'p.id = kom.ptk_id')
            ->join('pn_fisik_kesehatan fisik', 'p.id = fisik.ptk_id', 'left')
            ->where([
                'p.is_verifikasi' => '1',
                'p.is_batal'      => '0',
                'p.status_ptk'    => '1',
                'p1a.deleted_at'  => '1970-01-01 08:00:00',
            ]);

        $this->db->where("
            kom.volumeP1 IS NULL
            AND kom.volumeP3 IS NULL
            AND kom.volumeP4 IS NULL
            AND kom.volumeP5 IS NULL
            AND kom.volumeP6 IS NULL
            AND kom.volumeP7 IS NULL
            AND kom.volumeP8 IS NULL
            AND fisik.ptk_id IS NULL
        ", null, false);

        $this->applyCommonFilter($filter);
        $this->applyDateFilter('p1a.tanggal', $filter);

        return (int) $this->db->get()->row()->total;
    }
}
