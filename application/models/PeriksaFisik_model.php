<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'models/BaseModelStrict.php';

class PeriksaFisik_model extends BaseModelStrict
{
    /**
     * Map Tabel Pelepasan untuk filter "Belum Bebas"
     */
    private function getPelepasanTable($karantina)
    {
        $map = [
            'H' => 'pn_pelepasan_kh',
            'I' => 'pn_pelepasan_ki',
            'T' => 'pn_pelepasan_kt',
        ];
        return $map[$karantina] ?? '';
    }

    /* ================= STEP 1 — AMBIL ID ================= */
    public function getIds($f, $limit, $offset)
    {
        $this->db->select('p.id, MAX(p1b.tanggal) AS last_tgl', false)
            ->from('ptk p')
            ->join('pn_fisik_kesehatan p1b', 'p.id = p1b.ptk_id')
            ->join('ptk_komoditas kom', 'p.id = kom.ptk_id')
            ->where([
                'p.is_verifikasi' => '1',
                'p.is_batal'      => '0',
                'p1b.deleted_at'  => '1970-01-01 08:00:00',
            ])
            ->where('p.upt_id <>', '1000');

        // Logic Filter Belum Pelepasan
        if (!empty($f['karantina'])) {
            $tbl = $this->getPelepasanTable($f['karantina']);
            if ($tbl) {
                $this->db->join("$tbl p8", 'p.id = p8.ptk_id', 'left')
                         ->where('p8.ptk_id IS NULL', null, false);
            }
        }

        // Filter Tahapan Pemeriksaan (Hanya sampai Fisik)
        $this->db->where("
            kom.volumeP1 IS NOT NULL
            AND kom.volumeP3 IS NULL
            AND kom.volumeP4 IS NULL
            AND kom.volumeP5 IS NULL
            AND kom.volumeP6 IS NULL
            AND kom.volumeP7 IS NULL
            AND kom.volumeP8 IS NULL
        ", null, false);

        $this->applyCommonFilter($f, 'p');
        $this->applyDateFilter('p1b.tanggal', $f);

        $this->db->group_by('p.id')
                 ->order_by('last_tgl', 'DESC')
                 ->limit($limit, $offset);

        $res = $this->db->get()->result_array();
        return array_column($res, 'id');
    }
/* ================= STEP 2 — DATA DETAIL ================= */
    public function getByIds($ids, $is_export = false)
    {
        if (empty($ids)) return [];

        $this->db->select("
            p.id, p.no_aju, p.no_dok_permohonan, p.tgl_dok_permohonan,
            p1b.nomor AS no_p1b, p1b.tanggal AS tgl_p1b,
            mu.nama AS upt, mu.nama_satpel AS nama_satpel,
            p.nama_pengirim, p.nama_penerima,
            mn1.nama AS asal, mn3.nama AS kota_asal,
            mn2.nama AS tujuan, mn4.nama AS kota_tujuan
        ", false);

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
            ->join('pn_fisik_kesehatan p1b', 'p.id = p1b.ptk_id')
            ->join('master_upt mu', 'p.kode_satpel = mu.id')
            ->join('master_negara mn1', 'p.negara_asal_id = mn1.id', 'left')
            ->join('master_negara mn2', 'p.negara_tujuan_id = mn2.id', 'left')
            ->join('master_kota_kab mn3', 'p.kota_kab_asal_id = mn3.id', 'left')
            ->join('master_kota_kab mn4', 'p.kota_kab_tujuan_id = mn4.id', 'left')
            ->where_in('p.id', $ids)
            ->order_by('p1b.tanggal', 'DESC');

        return $this->db->get()->result_array();
    }
    
    public function countAll($f)
    {
        $this->db->select('COUNT(DISTINCT p.id) AS total', false)
            ->from('ptk p')
            ->join('pn_fisik_kesehatan p1b', 'p.id = p1b.ptk_id')
            ->join('ptk_komoditas kom', 'p.id = kom.ptk_id')
            ->where([
                'p.is_verifikasi' => '1',
                'p.is_batal'      => '0',
                'p1b.deleted_at'  => '1970-01-01 08:00:00',
            ])
            ->where('p.upt_id <>', '1000');

        if (!empty($f['karantina'])) {
            $tbl = $this->getPelepasanTable($f['karantina']);
            if ($tbl) {
                $this->db->join("$tbl p8", 'p.id = p8.ptk_id', 'left')
                         ->where('p8.ptk_id IS NULL', null, false);
            }
        }

        $this->db->where("
            kom.volumeP1 IS NOT NULL
            AND kom.volumeP3 IS NULL
            AND kom.volumeP4 IS NULL
            AND kom.volumeP5 IS NULL
            AND kom.volumeP6 IS NULL
            AND kom.volumeP7 IS NULL
            AND kom.volumeP8 IS NULL
        ", null, false);

        $this->applyCommonFilter($f, 'p');
        $this->applyDateFilter('p1b.tanggal', $f);

        $row = $this->db->get()->row();
        return $row ? (int) $row->total : 0;
    }
}