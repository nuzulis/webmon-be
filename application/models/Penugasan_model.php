<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'core/BaseModelStrict.php';

class Penugasan_model extends BaseModelStrict
{
    public function __construct()
    {
        parent::__construct();
    }

    public function getAll(array $f): array
    {
        $sql = "
            SELECT
                h.id,
                MAX(h.nomor)              AS nomor_surtug,
                MAX(h.tanggal)            AS tgl_surtug,
                MAX(p.no_dok_permohonan)  AS no_dok_permohonan,
                MAX(p.tgl_dok_permohonan) AS tgl_dok_permohonan,
                MAX(mu.nama)              AS upt,
                MAX(mu.nama_satpel)       AS satpel,
                GROUP_CONCAT(DISTINCT mp1.nama ORDER BY mp1.nama SEPARATOR '<br>') AS nama_petugas,
                GROUP_CONCAT(DISTINCT mp1.nip  ORDER BY mp1.nip  SEPARATOR '<br>') AS nip_petugas,
                MAX(mp2.nama)             AS penandatangan,
                MAX(mp2.nip)              AS nip_ttd,
                GROUP_CONCAT(DISTINCT mpn.nama ORDER BY mpn.nama SEPARATOR ', ') AS jenis_tugas
            FROM ptk_surtug_header h
            JOIN  ptk p                       ON h.ptk_id = p.id
            JOIN  master_upt mu               ON p.upt_id = mu.id
            JOIN  ptk_surtug_petugas pp       ON h.id = pp.ptk_surtug_header_id
            JOIN  ptk_surtug_penugasan pnp    ON pp.id = pnp.ptk_surtug_petugas_id
            JOIN  master_penugasan mpn        ON pnp.penugasan_id = mpn.id
            JOIN  master_pegawai mp1          ON pp.petugas_id = mp1.id
            JOIN  master_pegawai mp2          ON h.penanda_tangan_id = mp2.id
            WHERE p.is_verifikasi = '1'
              AND p.is_batal      = '0'
              AND h.deleted_at    = '1970-01-01 08:00:00'
        ";

        $params = [];
        $this->applyFilter($f, $sql, $params);
        $sql .= " GROUP BY h.id ORDER BY MAX(h.tanggal) DESC";

        $this->db->reconnect();
        $query = $this->db->query($sql, $params);
        return $query ? $query->result_array() : [];
    }

    public function getFullData(array $f): array
    {
        $sql = "
            SELECT
                h.id,
                p.no_aju,
                p.no_dok_permohonan,
                p.tgl_dok_permohonan,
                mu.nama_satpel        AS satpel,
                mu.nama               AS upt,
                h.nomor               AS nomor_surtug,
                h.tanggal             AS tgl_surtug,
                mp1.nama              AS nama_petugas,
                mp1.nip               AS nip_petugas,
                mpn.nama              AS jenis_tugas,
                mn1.nama              AS negara_asal,
                mn3.nama              AS daerah_asal,
                mn2.nama              AS negara_tujuan,
                mn4.nama              AS daerah_tujuan,
                pkom.nama_umum_tercetak,
                pkom.kode_hs,
                pkom.volumeP1, pkom.volumeP2, pkom.volumeP3, pkom.volumeP4,
                pkom.volumeP5, pkom.volumeP6, pkom.volumeP7, pkom.volumeP8,
                ms.nama               AS nama_satuan,
                CASE
                    WHEN p.jenis_karantina = 'H' THEN kh.nama
                    WHEN p.jenis_karantina = 'I' THEN ki.nama
                    WHEN p.jenis_karantina = 'T' THEN kt.nama
                    ELSE '-'
                END AS nama_komoditas
            FROM ptk_surtug_header h
            JOIN  ptk p                       ON h.ptk_id = p.id
            JOIN  master_upt mu               ON p.upt_id = mu.id
            JOIN  ptk_surtug_petugas pp       ON h.id = pp.ptk_surtug_header_id
            JOIN  ptk_surtug_penugasan pnp    ON pp.id = pnp.ptk_surtug_petugas_id
            JOIN  master_penugasan mpn        ON pnp.penugasan_id = mpn.id
            JOIN  master_pegawai mp1          ON pp.petugas_id = mp1.id
            LEFT JOIN ptk_komoditas pkom      ON p.id = pkom.ptk_id AND pkom.deleted_at = '1970-01-01 08:00:00'
            LEFT JOIN master_satuan ms        ON pkom.satuan_lain_id = ms.id
            LEFT JOIN master_negara mn1       ON p.negara_asal_id = mn1.id
            LEFT JOIN master_negara mn2       ON p.negara_tujuan_id = mn2.id
            LEFT JOIN master_kota_kab mn3     ON p.kota_kab_asal_id = mn3.id
            LEFT JOIN master_kota_kab mn4     ON p.kota_kab_tujuan_id = mn4.id
            LEFT JOIN komoditas_hewan kh      ON pkom.komoditas_id = kh.id AND p.jenis_karantina = 'H'
            LEFT JOIN komoditas_ikan ki       ON pkom.komoditas_id = ki.id AND p.jenis_karantina = 'I'
            LEFT JOIN komoditas_tumbuhan kt   ON pkom.komoditas_id = kt.id AND p.jenis_karantina = 'T'
            WHERE p.is_verifikasi = '1'
              AND p.is_batal      = '0'
              AND h.deleted_at    = '1970-01-01 08:00:00'
        ";

        $params = [];
        $this->applyFilter($f, $sql, $params);
        $sql .= " ORDER BY h.tanggal DESC";

        $this->db->reconnect();
        $query = $this->db->query($sql, $params);
        return $query ? $query->result_array() : [];
    }

    private function applyFilter(array $f, string &$sql, array &$params): void
    {
        if (!empty($f['petugas'])) {
            $sql     .= " AND pp.petugas_id = ?";
            $params[] = $f['petugas'];
        } elseif (!empty($f['karantina'])) {
            $sql     .= " AND p.jenis_karantina = ?";
            $params[] = $f['karantina'];
        }

        if (!empty($f['upt']) && !in_array(strtolower($f['upt']), ['all', 'semua', '1000'], true)) {
            $sql     .= " AND p.upt_id = ?";
            $params[] = substr($f['upt'], 0, 2) . '00';
        }

        if (!empty($f['start_date'])) {
            $sql     .= " AND h.tanggal >= ?";
            $params[] = $f['start_date'] . ' 00:00:00';
        }

        if (!empty($f['end_date'])) {
            $sql     .= " AND h.tanggal <= ?";
            $params[] = $f['end_date'] . ' 23:59:59';
        }
    }
}
