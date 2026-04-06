<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'core/BaseModelStrict.php';

class Nnc_model extends BaseModelStrict
{
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
                ANY_VALUE(p.tssm_id)             AS tssm_id,
                ANY_VALUE(p.no_aju)              AS no_aju,
                ANY_VALUE(p.no_dok_permohonan)   AS no_dok_permohonan,
                ANY_VALUE(p.tgl_dok_permohonan)  AS tgl_dok_permohonan,
                MAX(p6.nomor)   AS nomor_penolakan,
                MAX(p6.tanggal) AS tgl_penolakan,
                REPLACE(REPLACE(MAX(mt.nama),
                    'Balai Besar Karantina Hewan, Ikan, dan Tumbuhan', 'BBKHIT'),
                    'Balai Karantina Hewan, Ikan, dan Tumbuhan', 'BKHIT') AS upt_raw,
                MAX(mu.nama_satpel) AS nama_satpel,
                MAX(mp.nama)               AS petugas,
                ANY_VALUE(p.nama_pengirim) AS nama_pengirim,
                ANY_VALUE(p.nama_penerima) AS nama_penerima,
                ANY_VALUE(k_data.komoditas_list) AS komoditas,
                ANY_VALUE(k_data.hs_list)        AS hs,
                ANY_VALUE(k_data.volume_list)    AS volume,
                ANY_VALUE(k_data.satuan_list)    AS satuan,
                MAX(p6.alasan1) AS alasan1, MAX(p6.alasan2) AS alasan2,
                MAX(p6.alasan3) AS alasan3, MAX(p6.alasan4) AS alasan4,
                MAX(p6.alasan5) AS alasan5, MAX(p6.alasan6) AS alasan6,
                MAX(p6.alasan7) AS alasan7, MAX(p6.alasan8) AS alasan8,
                MAX(p6.alasan_lain)        AS alasan_lain,
                MAX(p6.specify1)           AS specify1, MAX(p6.specify2) AS specify2,
                MAX(p6.specify3)           AS specify3, MAX(p6.specify4) AS specify4,
                MAX(p6.specify5)           AS specify5,
                MAX(p6.consignment)        AS consignment_desc,
                MAX(p6.consignment_detil)  AS consignment_detil,
                MAX(p6.information)        AS information,
                MAX(p6.kepada)             AS kepada,
                MAX(mn1.nama) AS asal,  MAX(mn3.nama) AS kota_asal,
                MAX(mn2.nama) AS tujuan, MAX(mn4.nama) AS kota_tujuan
            FROM ptk p
            JOIN pn_penolakan p6       ON p.id = p6.ptk_id
            JOIN master_upt mu         ON p.kode_satpel = mu.id
            JOIN master_upt mt         ON p.upt_id = mt.id
            JOIN master_pegawai mp     ON p6.user_ttd_id = mp.id
            LEFT JOIN master_negara mn1     ON p.negara_asal_id = mn1.id
            LEFT JOIN master_negara mn2     ON p.negara_tujuan_id = mn2.id
            LEFT JOIN master_kota_kab mn3   ON p.kota_kab_asal_id = mn3.id
            LEFT JOIN master_kota_kab mn4   ON p.kota_kab_tujuan_id = mn4.id
            LEFT JOIN (
                SELECT pk.ptk_id,
                       GROUP_CONCAT(CONCAT('• ', kt.nama)   SEPARATOR '<br>') AS komoditas_list,
                       GROUP_CONCAT(DISTINCT pk.kode_hs     SEPARATOR '<br>') AS hs_list,
                       GROUP_CONCAT(pk.volumeP6             SEPARATOR '<br>') AS volume_list,
                       GROUP_CONCAT(COALESCE(ms.nama, '-')  SEPARATOR '<br>') AS satuan_list
                FROM ptk_komoditas pk
                JOIN $komTable kt ON pk.komoditas_id = kt.id
                LEFT JOIN master_satuan ms ON pk.satuan_lain_id = ms.id
                WHERE pk.deleted_at = '1970-01-01 08:00:00'
                GROUP BY pk.ptk_id
            ) k_data ON p.id = k_data.ptk_id
            WHERE p.is_verifikasi        = '1'
              AND p.is_batal             = '0'
              AND p6.deleted_at          = '1970-01-01 08:00:00'
              AND p6.dokumen_karantina_id = '32'
        ";

        $params = [];
        $this->applyFilter($f, $sql, $params);
        $sql .= " GROUP BY p.id ORDER BY MAX(p6.tanggal) DESC";

        $this->db->reconnect();
        $query = $this->db->query($sql, $params);
        return $this->formatNncData($query ? $query->result_array() : []);
    }

    public function getFullData(array $f): array
    {
        $komTable = $this->getKomTable($f['karantina'] ?? 'H');

        $sql = "
            SELECT
                p.id, p.tssm_id, p.no_aju, p.no_dok_permohonan, p.tgl_dok_permohonan,
                p6.nomor AS nomor_penolakan, p6.tanggal AS tgl_penolakan,
                p6.kepada, p6.consignment AS consignment_desc,
                p6.consignment_detil, p6.information,
                REPLACE(REPLACE(mt.nama,
                    'Balai Besar Karantina Hewan, Ikan, dan Tumbuhan', 'BBKHIT'),
                    'Balai Karantina Hewan, Ikan, dan Tumbuhan', 'BKHIT') AS upt_raw,
                mu.nama_satpel, p.nama_pengirim, p.nama_penerima, mp.nama AS petugas,
                kt.nama AS komoditas, pk.volumeP6 AS volume, ms.nama AS satuan, pk.kode_hs,
                p6.alasan1, p6.alasan2, p6.alasan3, p6.alasan4,
                p6.alasan5, p6.alasan6, p6.alasan7, p6.alasan8, p6.alasan_lain,
                COALESCE(p6.specify1, '') AS specify1, COALESCE(p6.specify2, '') AS specify2,
                COALESCE(p6.specify3, '') AS specify3, COALESCE(p6.specify4, '') AS specify4,
                COALESCE(p6.specify5, '') AS specify5,
                COALESCE(mn1.nama, '') AS asal,   COALESCE(mn3.nama, '') AS kota_asal,
                COALESCE(mn2.nama, '') AS tujuan, COALESCE(mn4.nama, '') AS kota_tujuan
            FROM ptk p
            JOIN pn_penolakan p6    ON p.id = p6.ptk_id
            JOIN master_upt mu      ON p.kode_satpel = mu.id
            JOIN master_upt mt      ON p.upt_id = mt.id
            JOIN master_pegawai mp  ON p6.user_ttd_id = mp.id
            JOIN ptk_komoditas pk   ON p.id = pk.ptk_id AND pk.deleted_at = '1970-01-01 08:00:00'
            JOIN $komTable kt       ON pk.komoditas_id = kt.id
            LEFT JOIN master_satuan ms    ON pk.satuan_lain_id = ms.id
            LEFT JOIN master_negara mn1   ON p.negara_asal_id = mn1.id
            LEFT JOIN master_negara mn2   ON p.negara_tujuan_id = mn2.id
            LEFT JOIN master_kota_kab mn3 ON p.kota_kab_asal_id = mn3.id
            LEFT JOIN master_kota_kab mn4 ON p.kota_kab_tujuan_id = mn4.id
            WHERE p.is_verifikasi        = '1'
              AND p.is_batal             = '0'
              AND p6.deleted_at          = '1970-01-01 08:00:00'
              AND p6.dokumen_karantina_id = '32'
        ";

        $params = [];
        $this->applyFilter($f, $sql, $params);
        $sql .= " ORDER BY p6.tanggal DESC, p.no_aju ASC";

        $this->db->reconnect();
        $query = $this->db->query($sql, $params);
        return $this->formatNncData($query ? $query->result_array() : [], true);
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

        if (!empty($f['lingkup'])) {
            $sql     .= " AND p.jenis_permohonan = ?";
            $params[] = strtoupper($f['lingkup']);
        }

        if (!empty($f['start_date']) && !empty($f['end_date'])) {
            $sql     .= " AND p6.tanggal >= ?";
            $sql     .= " AND p6.tanggal <= ?";
            $params[] = $f['start_date'] . ' 00:00:00';
            $params[] = $f['end_date']   . ' 23:59:59';
        }
    }

    private function formatNncData(array $rows, bool $isExcel = false): array
    {
        $alasanMap = [
            'alasan1' => 'Tidak dapat melengkapi dokumen persyaratan dalam waktu yang ditetapkan',
            'alasan2' => 'Persyaratan dokumen lain tidak dapat dipenuhi',
            'alasan3' => 'Berasal dari negara/daerah/tempat yang dilarang',
            'alasan4' => 'Berasal dari daerah wabah',
            'alasan5' => 'Jenis media pembawa dilarang',
            'alasan6' => 'Sanitasi tidak baik',
            'alasan7' => 'Ditemukan HPHK/HPIK/OPTK',
            'alasan8' => 'Tidak bebas OPTK',
        ];
        $specifyLabels = [
            1 => 'Prohibited goods: ',
            2 => 'Problem with documentation (specify): ',
            3 => 'The goods were infected/infested/contaminated (specify): ',
            4 => 'The goods do not comply with food safety (specify): ',
            5 => 'The goods do not comply with other SPS (specify): ',
        ];
        foreach ($rows as &$r) {
            $messages = [];
            foreach ($alasanMap as $key => $text) {
                if (!empty($r[$key]) && $r[$key] === '1') {
                    $messages[] = $isExcel ? "- $text" : "• $text";
                }
            }
            if (!empty($r['alasan_lain']) && $r['alasan_lain'] !== '0') {
                $messages[] = "Lain-lain: " . $r['alasan_lain'];
            }
            for ($i = 1; $i <= 5; $i++) {
                $val = $r["specify$i"] ?? '';
                if (!empty($val)) {
                    $messages[] = ($isExcel ? $specifyLabels[$i] : "<strong>" . $specifyLabels[$i] . "</strong> ") . htmlspecialchars($val);
                }
            }
            $r['nnc_reason']      = !empty($messages) ? implode($isExcel ? " | " : "<br>", $messages) : '-';
            $r['nnc_reason_text'] = strip_tags(str_replace('<br>', ' | ', $r['nnc_reason']));
            $r['consignment_full'] = "The " . ($r['consignment_desc'] ?? 'specified') . " lot was: " . ($r['information'] ?? 'Rejected');
            $r['upt_full']         = ($r['upt_raw'] ?? '') . ' - ' . ($r['nama_satpel'] ?? '');
        }
        return $rows;
    }
}
