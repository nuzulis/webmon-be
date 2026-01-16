<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'models/BaseModelStrict.php';

class PeriksaLapangan_model extends BaseModelStrict
{
    /* ================= STEP 1 — AMBIL ID ================= */
    public function getIds($f, $limit, $offset)
    {
        $this->db->select('p.id, MAX(p1a.tanggal) AS last_tgl', false)
            ->from('ptk p')
            ->join('ptk_surtug_header p1a', 'p.id = p1a.ptk_id')
            ->where([
                'p.is_verifikasi' => '1',
                'p.is_batal'      => '0',
                'p.status_ptk'    => '1',
                'p1a.deleted_at'  => '1970-01-01 08:00:00',
            ]);

        $this->applyCommonFilter($f, 'p');

        if (empty($f['upt']) || $f['upt'] === 'all') {
            $this->db->where('p.upt_id <>', '1000');
        }

        $this->applyDateFilter('p.tgl_dok_permohonan', $f);

        $this->db->group_by('p.id')
                 ->order_by('last_tgl', 'DESC')
                 ->limit($limit, $offset);

        $res = $this->db->get()->result_array();
        return array_column($res, 'id');
    }

    /* ================= STEP 2 — DATA UTAMA ================= */
    public function getByIds($ids)
    {
        if (empty($ids)) return [];

        $this->db->select("
            p.id,
            p.tssm_id, p.no_aju, p.no_dok_permohonan, p.tgl_dok_permohonan,
            p1a.nomor AS no_p1a, p1a.tanggal AS tgl_p1a,
            
            -- Alias UPT yang disingkat
            REPLACE(REPLACE(mu.nama, 
                'Balai Karantina Hewan, Ikan, dan Tumbuhan', 'BKHIT'), 
                'Balai Besar Karantina Hewan, Ikan, dan Tumbuhan', 'BBKHIT') AS upt,
            mu.nama_satpel AS nama_satpel,

            ot.mulai, ot.selesai,
            
            -- Kalkulasi Durasi
            TIMESTAMPDIFF(MINUTE, ot.mulai, IFNULL(ot.selesai, NOW())) AS durasi_menit,
            CONCAT(
                FLOOR(TIMESTAMPDIFF(MINUTE, ot.mulai, IFNULL(ot.selesai, NOW())) / 1440), ' hari ',
                FLOOR(MOD(TIMESTAMPDIFF(MINUTE, ot.mulai, IFNULL(ot.selesai, NOW())), 1440) / 60), ' jam ',
                MOD(TIMESTAMPDIFF(MINUTE, ot.mulai, IFNULL(ot.selesai, NOW())), 60), ' menit'
            ) AS durasi_text,

            -- Penentuan Status Proses
            CASE 
                WHEN ot.mulai IS NOT NULL AND ot.selesai IS NULL THEN 'PROSES'
                WHEN ot.selesai IS NOT NULL THEN 'SELESAI'
                ELSE 'BELUM MULAI'
            END AS status_proses,

            ot.keterangan,
            ohp.target, ohp.metode
        ", false);

        $this->db->from('ptk p')
            ->join('ptk_surtug_header p1a', 'p.id = p1a.ptk_id')
            ->join('master_upt mu', 'p.kode_satpel = mu.id')
            
            /* Subquery untuk mendapatkan waktu mulai dan selesai dari riwayat petugas */
            ->join("
                (
                    SELECT
                        r.surtug_header_id,
                        MIN(CASE WHEN r.status IN ('berangkat','mulai') THEN r.time END) AS mulai,
                        MAX(CASE WHEN r.status = 'selesai' THEN r.time END) AS selesai,
                        SUBSTRING_INDEX(
                            GROUP_CONCAT(r.keterangan ORDER BY r.time DESC SEPARATOR ' || '),
                            ' || ', 1
                        ) AS keterangan
                    FROM ptk_surtug_riwayat r
                    GROUP BY r.surtug_header_id
                ) ot
            ", 'ot.surtug_header_id = p1a.id', 'left', false)
            
            ->join('officer_hasil_periksa ohp', 'ohp.id_surat_tugas = p1a.id', 'left')
            ->where_in('p.id', $ids)
            ->order_by('tgl_p1a', 'DESC');

        return $this->db->get()->result_array();
    }

    /* ================= TOTAL DATA ================= */
    public function countAll($f)
    {
        $this->db->select('COUNT(DISTINCT p.id) AS total', false)
            ->from('ptk p')
            ->join('ptk_surtug_header p1a', 'p.id = p1a.ptk_id')
            ->where([
                'p.is_verifikasi' => '1',
                'p.is_batal'      => '0',
                'p.status_ptk'    => '1',
                'p1a.deleted_at'  => '1970-01-01 08:00:00',
            ]);

        $this->applyCommonFilter($f, 'p');

        if (empty($f['upt']) || $f['upt'] === 'all') {
            $this->db->where('p.upt_id <>', '1000');
        }

        $this->applyDateFilter('p.tgl_dok_permohonan', $f);

        $row = $this->db->get()->row();
        return $row ? (int) $row->total : 0;
    }
}