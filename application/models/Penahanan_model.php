<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'core/BaseModelStrict.php';

class Penahanan_model extends BaseModelStrict
{
    private $alasanMap = [
        'alasan1' => 'Media pembawa tidak dilaporkan kepada pejabat karantina pada saat pemasukan/pengeluaran',
        'alasan2' => 'Tidak disertai Keterangan Mutasi/keterangan tidak terkontaminasi/catatan suhu untuk media pembawa yang dipersyaratkan',
        'alasan3' => 'Tidak disertai dokumen karantina dan/atau dokumen lain yang dipersyaratkan saat tiba di tempat pemasukan',
    ];

    public function __construct()
    {
        parent::__construct();
    }

    private function getKomTable(string $kar): string
    {
        return match ($kar) {
            'I' => 'komoditas_ikan',
            'T' => 'komoditas_tumbuhan',
            default => 'komoditas_hewan',
        };
    }

    public function getAll(array $f): array
    {
        $komTable = $this->getKomTable($f['karantina'] ?? 'H');

        $sql = "
            SELECT
                p.id,
                MAX(p.no_aju)          AS no_aju,
                MAX(p5.nomor)          AS no_p5,
                MAX(p5.tanggal)        AS tgl_p5,
                MAX(mu.nama)           AS upt,
                MAX(mu.nama_satpel)    AS nama_satpel,
                MAX(mp.nama)           AS petugas,
                MAX(p.nama_pengirim)   AS nama_pengirim,
                MAX(p.nama_penerima)   AS nama_penerima,
                GROUP_CONCAT(DISTINCT k.nama        SEPARATOR '<br>') AS komoditas,
                GROUP_CONCAT(DISTINCT pk.volumeP5   SEPARATOR '<br>') AS volume,
                GROUP_CONCAT(DISTINCT ms.nama       SEPARATOR '<br>') AS satuan,
                MAX(p5.alasan1) AS alasan1,
                MAX(p5.alasan2) AS alasan2,
                MAX(p5.alasan3) AS alasan3
            FROM pn_penahanan p5
            JOIN ptk p              ON p5.ptk_id = p.id
            LEFT JOIN master_upt mu ON p.kode_satpel = mu.id
            LEFT JOIN master_pegawai mp ON p5.user_ttd_id = mp.id
            LEFT JOIN ptk_komoditas pk
                ON p.id = pk.ptk_id AND pk.deleted_at = '1970-01-01 08:00:00'
            LEFT JOIN $komTable k   ON pk.komoditas_id = k.id
            LEFT JOIN master_satuan ms ON pk.satuan_lain_id = ms.id
            WHERE p5.dokumen_karantina_id = 26
              AND p5.deleted_at  = '1970-01-01 08:00:00'
              AND p.is_verifikasi = '1'
              AND p.is_batal      = '0'
        ";

        $params = [];
        $this->applyFilter($f, $sql, $params);
        $sql .= " GROUP BY p.id ORDER BY MAX(p5.tanggal) DESC";

        $this->db->reconnect();
        $query = $this->db->query($sql, $params);
        $rows  = $query ? $query->result_array() : [];

        foreach ($rows as &$row) {
            $row['alasan_string'] = $this->buildAlasanString($row);
        }

        return $rows;
    }

    public function getFullData(array $f): array
    {
        $komTable = $this->getKomTable($f['karantina'] ?? 'H');

        $sql = "
            SELECT
                p.id, p.no_aju,
                p5.nomor    AS no_p5,
                p5.tanggal  AS tgl_p5,
                mu.nama     AS upt,
                p.nama_pengirim,
                p.nama_penerima,
                mn1.nama    AS asal,
                mn2.nama    AS tujuan,
                mp.nama     AS petugas,
                k.nama      AS komoditas,
                pk.volumeP5 AS volume,
                ms.nama     AS satuan,
                p5.alasan1, p5.alasan2, p5.alasan3
            FROM pn_penahanan p5
            JOIN ptk p              ON p5.ptk_id = p.id
            LEFT JOIN master_upt mu ON p.kode_satpel = mu.id
            LEFT JOIN master_pegawai mp  ON p5.user_ttd_id = mp.id
            LEFT JOIN master_negara mn1  ON p.negara_asal_id = mn1.id
            LEFT JOIN master_negara mn2  ON p.negara_tujuan_id = mn2.id
            JOIN ptk_komoditas pk
                ON p.id = pk.ptk_id AND pk.deleted_at = '1970-01-01 08:00:00'
            JOIN $komTable k  ON pk.komoditas_id = k.id
            LEFT JOIN master_satuan ms ON pk.satuan_lain_id = ms.id
            WHERE p5.dokumen_karantina_id = 26
              AND p5.deleted_at  = '1970-01-01 08:00:00'
              AND p.is_verifikasi = '1'
              AND p.is_batal      = '0'
        ";

        $params = [];
        $this->applyFilter($f, $sql, $params);
        $sql .= " ORDER BY p5.tanggal DESC";

        $this->db->reconnect();
        $query = $this->db->query($sql, $params);
        $rows  = $query ? $query->result_array() : [];

        foreach ($rows as &$row) {
            $row['alasan_string'] = $this->buildAlasanString($row);
        }

        return $rows;
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
            $params[] = strtoupper($f['karantina']);
        }

        if (!empty($f['start_date'])) {
            $sql     .= " AND p5.tanggal >= ?";
            $params[] = $f['start_date'] . ' 00:00:00';
        }

        if (!empty($f['end_date'])) {
            $sql     .= " AND p5.tanggal <= ?";
            $params[] = $f['end_date'] . ' 23:59:59';
        }
    }

    private function buildAlasanString(array $row): string
    {
        $out = [];
        foreach ($this->alasanMap as $field => $label) {
            if (!empty($row[$field]) && $row[$field] === '1') {
                $out[] = "- {$label}";
            }
        }
        return $out ? implode(PHP_EOL, $out) : '-';
    }
}
