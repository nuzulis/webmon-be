<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'models/BaseModelStrict.php';

class Monitoring_model extends BaseModelStrict
{
    protected $db_excel;

    public function __construct()
    {
        parent::__construct();
        $this->db_excel = $this->load->database('excel', TRUE);
    }

    private function getTable($karantina)
    {
        return match (strtoupper($karantina)) {
            'H' => 'pn_pelepasan_kh',
            'I' => 'pn_pelepasan_ki',
            'T' => 'pn_pelepasan_kt',
            default => 'pn_pelepasan_kh',
        };
    }

    private function getTableKom($karantina)
    {
        return match (strtoupper($karantina)) {
            'H' => 'komoditas_hewan',
            'I' => 'komoditas_ikan',
            'T' => 'komoditas_tumbuhan',
            default => 'komoditas_hewan',
        };
    }

    public function getAll(array $f): array
    {
        $sql = $this->buildBaseSQL($f['karantina'] ?? 'H');
        $params = [];
        $this->applyFilter($f, $sql, $params);
        $sql .= " GROUP BY p.id ORDER BY MAX(p1b.waktu_periksa) DESC";

        $this->db->reconnect();
        $query = $this->db->query($sql, $params);
        return $this->formatSlaText($query ? $query->result_array() : []);
    }

    private function buildBaseSQL(string $karInput): string
    {
        $tablePelepasan = $this->getTable($karInput);
        $tableKomoditas = $this->getTableKom($karInput);

        return "
            SELECT
                p.id,
                p.no_aju,
                p.no_dok_permohonan as no_dok,
                p.tgl_dok_permohonan,
                p.nama_pengirim,
                p.nama_penerima,
                REPLACE(REPLACE(MAX(mu.nama),
                    'Balai Besar Karantina Hewan, Ikan, dan Tumbuhan', 'BBKHIT'),
                    'Balai Karantina Hewan, Ikan, dan Tumbuhan', 'BKHIT') as upt_raw,
                MAX(mu.nama_satpel) as nama_satpel,
                MAX(p1b.waktu_periksa) as tgl_periksa,
                MAX(p8.tanggal) as tgl_lepas,
                TIMESTAMPDIFF(MINUTE, MAX(p1b.waktu_periksa), MAX(p8.tanggal)) as sla_menit,
                GROUP_CONCAT(DISTINCT kom.nama SEPARATOR ', ') as komoditas,
                GROUP_CONCAT(DISTINCT ms.nama SEPARATOR ', ') as satuan,
                SUM(pkom.volume_lain) as volume,
                CASE
                    WHEN MAX(s8.p8) IS NOT NULL THEN 'Pelepasan'
                    WHEN MAX(s8.p5) IS NOT NULL THEN 'Penahanan'
                    WHEN MAX(s8.p6) IS NOT NULL THEN 'Penolakan'
                    WHEN MAX(s8.p7) IS NOT NULL THEN 'Pemusnahan'
                    ELSE 'Proses'
                END AS status
            FROM ptk p
            LEFT JOIN master_upt mu ON p.kode_satpel = mu.id
            LEFT JOIN pn_fisik_kesehatan p1b ON p.id = p1b.ptk_id
            LEFT JOIN status8p s8 ON p.id = s8.id
            LEFT JOIN $tablePelepasan p8 ON p.id = p8.ptk_id AND p8.deleted_at = '1970-01-01 08:00:00'
            LEFT JOIN ptk_komoditas pkom ON p.id = pkom.ptk_id AND pkom.deleted_at = '1970-01-01 08:00:00'
            LEFT JOIN $tableKomoditas kom ON pkom.komoditas_id = kom.id
            LEFT JOIN master_satuan ms ON pkom.satuan_lain_id = ms.id
            WHERE p.is_verifikasi = '1'
              AND p.is_batal = '0'
        ";
    }

    private function applyFilter(array $f, string &$sql, array &$params): void
    {
        if (!empty($f['upt']) && !in_array(strtolower($f['upt']), ['all', 'semua'], true)) {
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
            $params[] = $f['lingkup'];
        }

        if (!empty($f['start_date']) && !empty($f['end_date'])) {
            $sql     .= " AND p1b.waktu_periksa >= ?";
            $sql     .= " AND p1b.waktu_periksa <= ?";
            $params[] = $f['start_date'] . ' 00:00:00';
            $params[] = $f['end_date']   . ' 23:59:59';
        }
    }

    private function formatSlaText(array $rows): array
    {
        foreach ($rows as &$r) {
            $r['upt_full'] = ($r['upt_raw'] ?? '') . ' - ' . ($r['nama_satpel'] ?? '');
            if (isset($r['sla_menit']) && $r['sla_menit'] !== null) {
                $min = (int) $r['sla_menit'];
                $r['is_warning'] = ($min > 60);
                $h = floor($min / 60);
                $m = $min % 60;
                $r['sla'] = ($min <= 0) ? '0m' : (($h > 0 ? "{$h}j " : "") . "{$m}m");
            } else {
                $r['sla'] = '-';
                $r['is_warning'] = false;
            }
            $r['sla_text'] = $r['sla'];
        }
        return $rows;
    }

    public function getFullData($f)
    {
        $karInput = $f['karantina'] ?? 'H';
        $tablePelepasan = $this->getTable($karInput);
        $tableKomoditas = $this->getTableKom($karInput);
        $this->db_excel->select("
            p.no_aju,
            p.no_dok_permohonan as no_dok,
            p.nama_pengirim,
            p.nama_penerima,
            p.tgl_dok_permohonan,
            p1b.waktu_periksa as tgl_periksa,
            p8.tanggal as tanggal_lepas,
            REPLACE(REPLACE(mu.nama,
                'Balai Besar Karantina Hewan, Ikan, dan Tumbuhan', 'BBKHIT'),
                'Balai Karantina Hewan, Ikan, dan Tumbuhan', 'BKHIT') as upt_raw,
            mu.nama_satpel,

            kom.nama as komoditas,
            pkom.nama_umum_tercetak,
            pkom.volumeP1 as p1, pkom.volumeP2 as p2, pkom.volumeP3 as p3, pkom.volumeP4 as p4,
            pkom.volumeP5 as p5, pkom.volumeP6 as p6, pkom.volumeP7 as p7, pkom.volumeP8 as p8,
            ms.nama as satuan,

            TIMESTAMPDIFF(MINUTE, p1b.waktu_periksa, p8.tanggal) as sla_menit,

            CASE
                WHEN s8.p8 IS NOT NULL THEN 'Pelepasan'
                WHEN s8.p5 IS NOT NULL THEN 'Penahanan'
                WHEN s8.p6 IS NOT NULL THEN 'Penolakan'
                WHEN s8.p7 IS NOT NULL THEN 'Pemusnahan'
                ELSE 'Proses'
            END AS status
        ", false);

        $this->db_excel->from('ptk p')
            ->join('master_upt mu', 'p.kode_satpel = mu.id', 'left')
            ->join('pn_fisik_kesehatan p1b', 'p.id = p1b.ptk_id')
            ->join('status8p s8', 'p.id = s8.id', 'left');

        $this->db_excel->join("$tablePelepasan p8", "p.id = p8.ptk_id AND p8.deleted_at = '1970-01-01 08:00:00'", 'left');
        $this->db_excel->join('ptk_komoditas pkom', "p.id = pkom.ptk_id AND pkom.deleted_at = '1970-01-01 08:00:00'");
        $this->db_excel->join("$tableKomoditas kom", 'pkom.komoditas_id = kom.id');
        $this->db_excel->join('master_satuan ms', 'pkom.satuan_lain_id = ms.id', 'left');

        $this->db_excel->where('p.is_verifikasi', '1');
        $this->db_excel->where('p.is_batal', '0');
        if (!empty($f['upt']) && !in_array(strtolower($f['upt']), ['all', 'semua'], true)) {
            $field = (strlen($f['upt']) <= 4) ? 'p.upt_id' : 'p.kode_satpel';
            $this->db_excel->where($field, $f['upt']);
        }
        if (!empty($f['karantina'])) {
            $this->db_excel->where('p.jenis_karantina', strtoupper($f['karantina']));
        }
        if (!empty($f['lingkup'])) {
            $this->db_excel->where('p.jenis_permohonan', $f['lingkup']);
        }
        if (!empty($f['start_date']) && !empty($f['end_date'])) {
            $this->db_excel->where('p1b.waktu_periksa >=', $f['start_date'] . ' 00:00:00');
            $this->db_excel->where('p1b.waktu_periksa <=', $f['end_date'] . ' 23:59:59');
        }

        $this->db_excel->order_by('p1b.waktu_periksa', 'DESC');
        $this->db_excel->order_by('p.no_aju', 'ASC');

        $rows = $this->db_excel->get()->result_array();
        return $this->formatSlaText($rows);
    }
}
