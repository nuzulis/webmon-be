<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'core/BaseModelStrict.php';

class Pemusnahan_model extends BaseModelStrict
{
    private array $alasanMap = [
        'alasan1' => 'Media Pembawa adalah jenis yang dilarang pemasukannya',
        'alasan2' => 'Media pembawa rusak/busuk',
        'alasan3' => 'Berasal dari negara/daerah yang sedang tertular/berjangkit wabah HPHK/HPIK/OPTK',
        'alasan4' => 'Tidak dapat disembuhkan/dibebaskan dari HPHK/HPIK/OPTK/OPT negara tujuan setelah diberi Perlakuan',
        'alasan5' => 'Tidak dikeluarkan dari wilayah negara RI dalam waktu 3 hari setelah penolakan',
        'alasan6' => 'Tidak memenuhi persyaratan keamanan dan mutu pangan/pakan',
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
                MAX(mus.nomor)              AS nomor,
                MAX(mus.tanggal)            AS tgl_p7,
                MAX(mu.nama)                AS nama_upt,
                MAX(mu.nama_satpel)         AS nama_satpel,
                MAX(bam.petugas_pelaksana)  AS petugas,
                MAX(p.nama_pengirim)              AS nama_pengirim,
                MAX(p.nama_penerima)              AS nama_penerima,
                MAX(mn1.nama_en)                  AS negara_asal,
                MAX(kab1.nama)                    AS kota_kab_asal,
                MAX(mn2.nama_en)                  AS negara_tujuan,
                MAX(kab2.nama)                    AS kota_kab_tujuan,
                MAX(bam.nip_pelaksana)            AS nip_pelaksana,
                GROUP_CONCAT(DISTINCT k.nama         SEPARATOR '<br>') AS komoditas,
                GROUP_CONCAT(DISTINCT pk.kode_hs     SEPARATOR '<br>') AS hs,
                GROUP_CONCAT(DISTINCT pk.volume_lain SEPARATOR '<br>') AS volume,
                GROUP_CONCAT(DISTINCT pk.volumeP7    SEPARATOR '<br>') AS p7,
                GROUP_CONCAT(DISTINCT ms.nama        SEPARATOR '<br>') AS satuan,
                MAX(mus.alasan1)     AS alasan1,
                MAX(mus.alasan2)     AS alasan2,
                MAX(mus.alasan3)     AS alasan3,
                MAX(mus.alasan4)     AS alasan4,
                MAX(mus.alasan5)     AS alasan5,
                MAX(mus.alasan6)     AS alasan6,
                MAX(mus.alasan_lain) AS alasan_lain
            FROM pn_pemusnahan mus
            JOIN ptk p              ON mus.ptk_id = p.id
            JOIN master_upt mu      ON p.kode_satpel = mu.id
            LEFT JOIN ptk_komoditas pk
                ON p.id = pk.ptk_id AND pk.deleted_at = '1970-01-01 08:00:00'
            LEFT JOIN $komTable k   ON pk.komoditas_id = k.id
            LEFT JOIN master_satuan ms   ON pk.satuan_lain_id = ms.id
            LEFT JOIN pn_pemusnahan bam
                ON mus.id = bam.pn_pemusnahan_id AND bam.dokumen_karantina_id = '36'
            JOIN master_negara mn1   ON p.negara_asal_id = mn1.id
            JOIN master_negara mn2   ON p.negara_tujuan_id = mn2.id
            LEFT JOIN master_kota_kab kab1 ON p.kota_kab_asal_id = kab1.id
            LEFT JOIN master_kota_kab kab2 ON p.kota_kab_tujuan_id = kab2.id
            WHERE mus.deleted_at          = '1970-01-01 08:00:00'
              AND mus.dokumen_karantina_id = '35'
              AND p.is_batal              = '0'
        ";

        $params = [];
        $this->applyFilter($f, $sql, $params);
        $sql .= " GROUP BY p.id ORDER BY MAX(mus.tanggal) DESC";

        $this->db->reconnect();
        $query = $this->db->query($sql, $params);
        $rows  = $query ? $query->result_array() : [];

        foreach ($rows as &$r) {
            $r['alasan_string'] = $this->buildAlasan($r);
        }

        return $rows;
    }

    public function getFullData(array $f): array
    {
        $komTable = $this->getKomTable($f['karantina'] ?? 'H');

        $sql = "
            SELECT
                p.id,
                p.tssm_id,
                mus.nomor,
                mus.tanggal             AS tgl_p7,
                mu.nama                 AS nama_upt,
                mu.nama_satpel,
                p.nama_pengirim,
                p.nama_penerima,
                mn1.nama_en             AS negara_asal,
                kab1.nama               AS kota_kab_asal,
                mn2.nama_en             AS negara_tujuan,
                kab2.nama               AS kota_kab_tujuan,
                bam.tempat_musnah       AS tempat,
                bam.metode_musnah       AS metode,
                bam.petugas_pelaksana   AS petugas,
                bam.nip_pelaksana,
                kom.nama                AS komoditas,
                pkom.nama_umum_tercetak AS tercetak,
                pkom.kode_hs            AS hs,
                pkom.volume_lain        AS volume,
                pkom.volumeP7           AS p7,
                ms.nama                 AS satuan,
                mus.alasan1, mus.alasan2, mus.alasan3,
                mus.alasan4, mus.alasan5, mus.alasan6,
                mus.alasan_lain
            FROM pn_pemusnahan mus
            JOIN ptk p          ON mus.ptk_id = p.id
            JOIN master_upt mu  ON p.kode_satpel = mu.id
            JOIN master_negara mn1    ON p.negara_asal_id = mn1.id
            JOIN master_negara mn2    ON p.negara_tujuan_id = mn2.id
            LEFT JOIN master_kota_kab kab1 ON p.kota_kab_asal_id = kab1.id
            LEFT JOIN master_kota_kab kab2 ON p.kota_kab_tujuan_id = kab2.id
            LEFT JOIN pn_pemusnahan bam
                ON mus.id = bam.pn_pemusnahan_id AND bam.dokumen_karantina_id = '36'
            JOIN ptk_komoditas pkom
                ON p.id = pkom.ptk_id AND pkom.deleted_at = '1970-01-01 08:00:00'
            JOIN $komTable kom ON pkom.komoditas_id = kom.id
            LEFT JOIN master_satuan ms ON pkom.satuan_lain_id = ms.id
            WHERE mus.deleted_at          = '1970-01-01 08:00:00'
              AND mus.dokumen_karantina_id = '35'
              AND p.is_batal              = '0'
        ";

        $params = [];
        $this->applyFilter($f, $sql, $params);
        $sql .= " ORDER BY mus.tanggal DESC";

        $this->db->reconnect();
        $query = $this->db->query($sql, $params);
        $rows  = $query ? $query->result_array() : [];

        foreach ($rows as &$r) {
            $r['alasan_string'] = $this->buildAlasan($r);
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
            $sql     .= " AND mus.tanggal >= ?";
            $params[] = $f['start_date'] . ' 00:00:00';
        }

        if (!empty($f['end_date'])) {
            $sql     .= " AND mus.tanggal <= ?";
            $params[] = $f['end_date'] . ' 23:59:59';
        }
    }

    private function buildAlasan(array $r): string
    {
        $out = [];
        foreach ($this->alasanMap as $k => $v) {
            if (!empty($r[$k]) && $r[$k] === '1') {
                $out[] = "- {$v}";
            }
        }
        if (!empty($r['alasan_lain']) && $r['alasan_lain'] !== '0') {
            $out[] = "Lain-lain: " . $r['alasan_lain'];
        }
        return $out ? implode(PHP_EOL, $out) : '-';
    }
}
