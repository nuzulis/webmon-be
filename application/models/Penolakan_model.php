<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'core/BaseModelStrict.php';

class Penolakan_model extends BaseModelStrict
{
    private array $alasanMap = [
        'alasan1' => 'Tidak dapat melengkapi dokumen persyaratan dalam waktu yang ditetapkan',
        'alasan2' => 'Persyaratan dokumen lain tidak dapat dipenuhi',
        'alasan3' => 'Berasal dari negara/daerah/tempat yang dilarang',
        'alasan4' => 'Berasal dari daerah wabah',
        'alasan5' => 'Jenis media pembawa dilarang',
        'alasan6' => 'Sanitasi tidak baik',
        'alasan7' => 'Ditemukan HPHK/HPIK/OPTK',
        'alasan8' => 'Tidak bebas OPTK',
    ];

    protected $db_excel;

    public function __construct()
    {
        parent::__construct();
        $this->db_excel = $this->load->database('excel', TRUE);
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
                ANY_VALUE(p.tssm_id)              AS tssm_id,
                ANY_VALUE(p.jenis_permohonan)     AS jenis_permohonan,
                ANY_VALUE(p.tgl_dok_permohonan)   AS tgl_dok_perm,
                MAX(p.no_dok_permohonan)           AS no_dok_permohonan,
                MAX(p6.nomor)                      AS nomor_penolakan,
                MAX(p6.tanggal)                    AS tgl_penolakan,
                MAX(mu.nama_satpel)                AS nama_satpel,
                REPLACE(REPLACE(MAX(mt.nama),
                    'Balai Karantina Hewan, Ikan, dan Tumbuhan', 'BKHIT'),
                    'Balai Besar Karantina Hewan, Ikan, dan Tumbuhan', 'BBKHIT') AS upt,
                MAX(mp.nama)                       AS petugas,
                MAX(p.nama_pengirim)               AS nama_pengirim,
                MAX(p.nama_penerima)               AS nama_penerima,
                MAX(mn1.nama)                      AS asal,
                MAX(mn2.nama)                      AS tujuan,
                MAX(mn3.nama)                      AS kota_asal,
                MAX(mn4.nama)                      AS kota_tujuan,
                GROUP_CONCAT(DISTINCT k.nama        SEPARATOR '<br>') AS komoditas,
                GROUP_CONCAT(DISTINCT pk.kode_hs    SEPARATOR '<br>') AS hs,
                GROUP_CONCAT(DISTINCT pk.volumeP6   SEPARATOR '<br>') AS volume,
                GROUP_CONCAT(DISTINCT ms.nama       SEPARATOR '<br>') AS satuan,
                MAX(p6.alasan1)    AS alasan1,  MAX(p6.alasan2) AS alasan2,
                MAX(p6.alasan3)    AS alasan3,  MAX(p6.alasan4) AS alasan4,
                MAX(p6.alasan5)    AS alasan5,  MAX(p6.alasan6) AS alasan6,
                MAX(p6.alasan7)    AS alasan7,  MAX(p6.alasan8) AS alasan8,
                MAX(p6.alasan_lain) AS alasan_lain
            FROM ptk p
            JOIN  pn_penolakan p6      ON p.id = p6.ptk_id
            JOIN  master_pegawai mp    ON p6.user_ttd_id = mp.id
            JOIN  master_upt mu        ON p.kode_satpel = mu.id
            JOIN  master_upt mt        ON p.upt_id = mt.id
            LEFT JOIN ptk_komoditas pk ON p.id = pk.ptk_id AND pk.deleted_at = '1970-01-01 08:00:00'
            LEFT JOIN $komTable k      ON pk.komoditas_id = k.id
            LEFT JOIN master_satuan ms ON pk.satuan_lain_id = ms.id
            LEFT JOIN master_negara mn1     ON p.negara_asal_id = mn1.id
            LEFT JOIN master_negara mn2     ON p.negara_tujuan_id = mn2.id
            LEFT JOIN master_kota_kab mn3   ON p.kota_kab_asal_id = mn3.id
            LEFT JOIN master_kota_kab mn4   ON p.kota_kab_tujuan_id = mn4.id
            WHERE p.is_verifikasi          = '1'
              AND p.is_batal               = '0'
              AND p6.deleted_at            = '1970-01-01 08:00:00'
              AND p6.dokumen_karantina_id != '32'
        ";

        $params = [];
        $this->applyFilter($f, $sql, $params);
        $sql .= " GROUP BY p.id ORDER BY MAX(p6.tanggal) DESC";

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
                p.jenis_permohonan,
                p.no_dok_permohonan,
                p.tgl_dok_permohonan,
                p6.nomor    AS nomor_penolakan,
                p6.tanggal  AS tgl_penolakan,
                mu.nama_satpel,
                REPLACE(REPLACE(mt.nama,
                    'Balai Karantina Hewan, Ikan, dan Tumbuhan', 'BKHIT'),
                    'Balai Besar Karantina Hewan, Ikan, dan Tumbuhan', 'BBKHIT') AS upt,
                p.nama_pengirim,
                p.nama_penerima,
                mp.nama       AS petugas,
                k.nama        AS komoditas,
                pk.kode_hs    AS hs,
                pk.volumeP6   AS volume,
                ms.nama       AS satuan,
                COALESCE(p6.alasan1, '0') AS alasan1,
                COALESCE(p6.alasan2, '0') AS alasan2,
                COALESCE(p6.alasan3, '0') AS alasan3,
                COALESCE(p6.alasan4, '0') AS alasan4,
                COALESCE(p6.alasan5, '0') AS alasan5,
                COALESCE(p6.alasan6, '0') AS alasan6,
                COALESCE(p6.alasan7, '0') AS alasan7,
                COALESCE(p6.alasan8, '0') AS alasan8,
                COALESCE(p6.alasan_lain, '0') AS alasan_lain,
                mn1.nama AS asal,
                mn2.nama AS tujuan,
                mn3.nama AS kota_asal,
                mn4.nama AS kota_tujuan
            FROM ptk p
            JOIN  pn_penolakan p6    ON p.id = p6.ptk_id
            JOIN  master_pegawai mp  ON p6.user_ttd_id = mp.id
            JOIN  master_upt mu      ON p.kode_satpel = mu.id
            JOIN  master_upt mt      ON p.upt_id = mt.id
            JOIN  ptk_komoditas pk   ON p.id = pk.ptk_id AND pk.deleted_at = '1970-01-01 08:00:00'
            JOIN  $komTable k        ON pk.komoditas_id = k.id
            LEFT JOIN master_satuan ms    ON pk.satuan_lain_id = ms.id
            LEFT JOIN master_negara mn1   ON p.negara_asal_id = mn1.id
            LEFT JOIN master_negara mn2   ON p.negara_tujuan_id = mn2.id
            LEFT JOIN master_kota_kab mn3 ON p.kota_kab_asal_id = mn3.id
            LEFT JOIN master_kota_kab mn4 ON p.kota_kab_tujuan_id = mn4.id
            WHERE p.is_verifikasi          = '1'
              AND p.is_batal               = '0'
              AND p6.deleted_at            = '1970-01-01 08:00:00'
              AND p6.dokumen_karantina_id != '32'
        ";

        $params = [];
        $this->applyFilter($f, $sql, $params);
        $sql .= " ORDER BY p6.tanggal DESC, p.id ASC";

        $this->db_excel->reconnect();
        $query = $this->db_excel->query($sql, $params);
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
            $sql     .= " AND p6.tanggal >= ?";
            $params[] = $f['start_date'] . ' 00:00:00';
        }

        if (!empty($f['end_date'])) {
            $sql     .= " AND p6.tanggal <= ?";
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
