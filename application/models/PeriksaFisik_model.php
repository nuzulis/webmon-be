<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'core/BaseModelStrict.php';

class PeriksaFisik_model extends BaseModelStrict
{
    public function __construct()
    {
        parent::__construct();
    }

    private function getPelepasanTable(string $kar): string
    {
        return match ($kar) {
            'I' => 'pn_pelepasan_ki',
            'T' => 'pn_pelepasan_kt',
            default => 'pn_pelepasan_kh',
        };
    }

    public function getAll(array $f): array
    {
        $pelepasanTable = $this->getPelepasanTable($f['karantina'] ?? 'H');

        $sql = "
            SELECT
                p.id,
                ANY_VALUE(p.no_aju)             AS no_aju,
                ANY_VALUE(p.no_dok_permohonan)  AS no_dok_permohonan,
                ANY_VALUE(p.tgl_dok_permohonan) AS tgl_dok_permohonan,
                MAX(p1b.nomor)                  AS no_p1b,
                MAX(p1b.tanggal)                AS tgl_p1b,
                REPLACE(REPLACE(ANY_VALUE(mu.nama),
                    'Balai Besar Karantina Hewan, Ikan, dan Tumbuhan', 'BBKHIT'),
                    'Balai Karantina Hewan, Ikan, dan Tumbuhan', 'BKHIT') AS upt,
                ANY_VALUE(mu.nama_satpel)       AS nama_satpel,
                ANY_VALUE(p.nama_pengirim)      AS nama_pengirim,
                ANY_VALUE(p.nama_penerima)      AS nama_penerima,
                MAX(mn1.nama)                   AS asal,
                MAX(mn2.nama)                   AS tujuan,
                MAX(mn3.nama)                   AS kota_asal,
                MAX(mn4.nama)                   AS kota_tujuan,
                GROUP_CONCAT(DISTINCT pkom.nama_umum_tercetak SEPARATOR '<br>') AS komoditas
            FROM ptk p
            JOIN  pn_fisik_kesehatan p1b    ON p.id = p1b.ptk_id
            LEFT JOIN ptk_komoditas pkom    ON p.id = pkom.ptk_id AND pkom.deleted_at = '1970-01-01 08:00:00'
            LEFT JOIN master_upt mu         ON p.kode_satpel = mu.id
            LEFT JOIN master_negara mn1     ON p.negara_asal_id = mn1.id
            LEFT JOIN master_negara mn2     ON p.negara_tujuan_id = mn2.id
            LEFT JOIN master_kota_kab mn3   ON p.kota_kab_asal_id = mn3.id
            LEFT JOIN master_kota_kab mn4   ON p.kota_kab_tujuan_id = mn4.id
            WHERE p.is_verifikasi = '1'
              AND p.is_batal      = '0'
              AND p1b.deleted_at  = '1970-01-01 08:00:00'
              AND p.upt_id       != '1000'
              AND NOT EXISTS (
                  SELECT 1 FROM $pelepasanTable p8 WHERE p8.ptk_id = p.id
              )
              AND EXISTS (
                  SELECT 1 FROM ptk_komoditas pk_vol WHERE pk_vol.ptk_id = p.id
                  AND pk_vol.volumeP1 IS NOT NULL
                  AND pk_vol.volumeP3 IS NULL AND pk_vol.volumeP4 IS NULL
                  AND pk_vol.volumeP5 IS NULL AND pk_vol.volumeP6 IS NULL
                  AND pk_vol.volumeP7 IS NULL AND pk_vol.volumeP8 IS NULL
              )
        ";

        $params = [];
        $this->applyFilter($f, $sql, $params);
        $sql .= " GROUP BY p.id ORDER BY MAX(p1b.tanggal) DESC";

        $this->db->reconnect();
        $query = $this->db->query($sql, $params);
        return $query ? $query->result_array() : [];
    }

    public function getFullData(array $f): array
    {
        $pelepasanTable = $this->getPelepasanTable($f['karantina'] ?? 'H');

        $sql = "
            SELECT
                p.id,
                p.no_aju,
                p.no_dok_permohonan,
                p.tgl_dok_permohonan,
                p1b.nomor   AS no_p1b,
                p1b.tanggal AS tgl_p1b,
                mu.nama     AS upt,
                mu.nama_satpel,
                p.nama_pengirim,
                p.nama_penerima,
                mn1.nama    AS asal,
                mn2.nama    AS tujuan,
                mn3.nama    AS kota_asal,
                mn4.nama    AS kota_tujuan,
                pkom.nama_umum_tercetak,
                pkom.kode_hs        AS hs,
                pkom.volume_lain    AS volume,
                ms.nama             AS satuan
            FROM ptk p
            JOIN  pn_fisik_kesehatan p1b   ON p.id = p1b.ptk_id
            JOIN  ptk_komoditas pkom       ON p.id = pkom.ptk_id AND pkom.deleted_at = '1970-01-01 08:00:00'
            LEFT JOIN master_upt mu        ON p.kode_satpel = mu.id
            LEFT JOIN master_satuan ms     ON pkom.satuan_lain_id = ms.id
            LEFT JOIN master_negara mn1    ON p.negara_asal_id = mn1.id
            LEFT JOIN master_negara mn2    ON p.negara_tujuan_id = mn2.id
            LEFT JOIN master_kota_kab mn3  ON p.kota_kab_asal_id = mn3.id
            LEFT JOIN master_kota_kab mn4  ON p.kota_kab_tujuan_id = mn4.id
            WHERE p.is_verifikasi = '1'
              AND p.is_batal      = '0'
              AND p1b.deleted_at  = '1970-01-01 08:00:00'
              AND p.upt_id       != '1000'
              AND NOT EXISTS (
                  SELECT 1 FROM $pelepasanTable p8 WHERE p8.ptk_id = p.id
              )
              AND EXISTS (
                  SELECT 1 FROM ptk_komoditas pk_vol WHERE pk_vol.ptk_id = p.id
                  AND pk_vol.volumeP1 IS NOT NULL
                  AND pk_vol.volumeP3 IS NULL AND pk_vol.volumeP4 IS NULL
                  AND pk_vol.volumeP5 IS NULL AND pk_vol.volumeP6 IS NULL
                  AND pk_vol.volumeP7 IS NULL AND pk_vol.volumeP8 IS NULL
              )
        ";

        $params = [];
        $this->applyFilter($f, $sql, $params);
        $sql .= " ORDER BY p1b.tanggal DESC, p.id ASC";

        $this->db->reconnect();
        $query = $this->db->query($sql, $params);
        return $query ? $query->result_array() : [];
    }

    private function applyFilter(array $f, string &$sql, array &$params): void
    {
        if (!empty($f['upt']) && !in_array(strtolower($f['upt']), ['all', 'semua', 'undefined'], true)) {
            $field    = (strlen($f['upt']) <= 4) ? 'p.upt_id' : 'p.kode_satpel';
            $sql     .= " AND $field = ?";
            $params[] = $f['upt'];
        }

        if (!empty($f['karantina'])) {
            $sql     .= " AND p.jenis_karantina = ?";
            $params[] = $f['karantina'];
        }

        $lingkup = $f['lingkup'] ?? '';
        if (!empty($lingkup) && !in_array(strtolower($lingkup), ['all', 'semua', 'undefined'], true)) {
            $sql     .= " AND p.jenis_permohonan = ?";
            $params[] = strtoupper($lingkup);
        }

        if (!empty($f['start_date'])) {
            $sql     .= " AND p1b.tanggal >= ?";
            $params[] = $f['start_date'] . ' 00:00:00';
        }

        if (!empty($f['end_date'])) {
            $sql     .= " AND p1b.tanggal <= ?";
            $params[] = $f['end_date'] . ' 23:59:59';
        }
    }
}
