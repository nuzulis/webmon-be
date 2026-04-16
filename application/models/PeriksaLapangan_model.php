<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'core/BaseModelStrict.php';

class PeriksaLapangan_model extends BaseModelStrict
{
    protected $db_excel;

    public function __construct()
    {
        parent::__construct();
        $this->db_excel = $this->load->database('excel', TRUE);
    }

    public function getAll(array $f): array
    {
        $sql = "
            SELECT
                p.id,
                ANY_VALUE(p.no_aju)              AS no_aju,
                ANY_VALUE(p.no_dok_permohonan)   AS no_dok_permohonan,
                ANY_VALUE(p.tgl_dok_permohonan)  AS tgl_dok_permohonan,
                ANY_VALUE(p.jenis_karantina)     AS jenis_karantina,
                REPLACE(REPLACE(MAX(mu.nama),
                    'Balai Besar Karantina Hewan, Ikan, dan Tumbuhan', 'BBKHIT'),
                    'Balai Karantina Hewan, Ikan, dan Tumbuhan', 'BKHIT')  AS nama_upt,
                MAX(mu.nama_satpel)              AS nama_satpel,
                GROUP_CONCAT(DISTINCT p1a.nomor  SEPARATOR '\n') AS nomor_surtug,
                GROUP_CONCAT(DISTINCT mp.nama    SEPARATOR '\n') AS nama_petugas,
                GROUP_CONCAT(DISTINCT
                    CASE
                        WHEN p.jenis_karantina = 'H' THEN kh.nama
                        WHEN p.jenis_karantina = 'I' THEN ki.nama
                        WHEN p.jenis_karantina = 'T' THEN kt.nama
                        ELSE '-'
                    END
                SEPARATOR '\n') AS komoditas,
                GROUP_CONCAT(DISTINCT sl.locationName SEPARATOR '\n') AS lokasi_periksa,
                MAX(ohp.tgl_periksa)             AS tgl_periksa_terakhir,
                GROUP_CONCAT(DISTINCT ohp.temuan SEPARATOR ' | ') AS ringkasan_temuan
            FROM ptk p
            JOIN  ptk_surtug_header p1a      ON p.id = p1a.ptk_id
            JOIN  officer_hasil_periksa ohp  ON ohp.id_surat_tugas = p1a.id
            LEFT JOIN ptk_surtug_lokasi sl   ON p1a.id = sl.ptk_surtug_header_id
            LEFT JOIN ptk_komoditas pk       ON ohp.id_komoditas = pk.id
            LEFT JOIN komoditas_hewan kh     ON pk.komoditas_id = kh.id AND p.jenis_karantina = 'H'
            LEFT JOIN komoditas_ikan ki      ON pk.komoditas_id = ki.id AND p.jenis_karantina = 'I'
            LEFT JOIN komoditas_tumbuhan kt  ON pk.komoditas_id = kt.id AND p.jenis_karantina = 'T'
            LEFT JOIN master_pegawai mp      ON ohp.id_petugas = mp.id
            LEFT JOIN master_upt mu          ON p.kode_satpel = mu.id
            WHERE p.is_verifikasi = '1'
              AND p.is_batal      = '0'
              AND p.deleted_at    = '1970-01-01 08:00:00'
              AND p1a.deleted_at  = '1970-01-01 08:00:00'
        ";

        $params = [];
        $this->applyFilter($f, $sql, $params);
        $sql .= " GROUP BY p.id ORDER BY MAX(ohp.tgl_periksa) DESC";

        $this->db->reconnect();
        $query = $this->db->query($sql, $params);
        return $query ? $query->result_array() : [];
    }

    public function getFullData(array $f): array
    {
        $sqlMulai   = "(SELECT MIN(time) FROM ptk_surtug_riwayat WHERE surtug_header_id = p1a.id AND status = 'mulai')";
        $sqlSelesai = "(SELECT MAX(time) FROM ptk_surtug_riwayat WHERE surtug_header_id = p1a.id AND status = 'selesai')";
        $sqlLog     = "(SELECT keterangan FROM ptk_surtug_riwayat WHERE surtug_header_id = p1a.id ORDER BY time DESC LIMIT 1)";

        $sql = "
            SELECT
                p.id,
                p.no_aju,
                p.no_dok_permohonan,
                p.tgl_dok_permohonan,
                p1a.nomor    AS no_surtug,
                p1a.tanggal  AS tgl_surtug,
                mu.nama      AS upt_nama,
                mu.nama_satpel,
                mp.nama      AS nama_petugas,
                mp.nip       AS nip_petugas,
                ohp.tgl_periksa,
                ohp.target,
                ohp.metode,
                ohp.temuan,
                ohp.catatan,
                $sqlMulai AS mulai,
                $sqlSelesai AS selesai,
                TIMESTAMPDIFF(MINUTE, $sqlMulai, $sqlSelesai) AS durasi_menit,
                $sqlLog AS keterangan_log,
                CASE
                    WHEN p.jenis_karantina = 'H' THEN kh.nama
                    WHEN p.jenis_karantina = 'I' THEN ki.nama
                    WHEN p.jenis_karantina = 'T' THEN kt.nama
                    ELSE '-'
                END AS nama_komoditas
            FROM ptk p
            JOIN  ptk_surtug_header p1a      ON p.id = p1a.ptk_id
            JOIN  officer_hasil_periksa ohp  ON ohp.id_surat_tugas = p1a.id
            LEFT JOIN ptk_komoditas pk       ON ohp.id_komoditas = pk.id
            LEFT JOIN komoditas_hewan kh     ON pk.komoditas_id = kh.id AND p.jenis_karantina = 'H'
            LEFT JOIN komoditas_ikan ki      ON pk.komoditas_id = ki.id AND p.jenis_karantina = 'I'
            LEFT JOIN komoditas_tumbuhan kt  ON pk.komoditas_id = kt.id AND p.jenis_karantina = 'T'
            LEFT JOIN master_pegawai mp      ON ohp.id_petugas = mp.id
            LEFT JOIN master_upt mu          ON p.kode_satpel = mu.id
            WHERE p.is_verifikasi = '1'
              AND p.is_batal      = '0'
              AND p.deleted_at    = '1970-01-01 08:00:00'
              AND p1a.deleted_at  = '1970-01-01 08:00:00'
        ";

        $params = [];
        $this->applyFilter($f, $sql, $params);
        $sql .= " ORDER BY ohp.tgl_periksa DESC";

        $this->db_excel->reconnect();
        $query = $this->db_excel->query($sql, $params);
        return $query ? $query->result_array() : [];
    }

    public function getDetailFinal($p_id)
    {
        $this->db->select('psh.id, psh.nomor, psh.tanggal, psh.perihal, psh.status');
        $this->db->from('ptk_surtug_header psh');
        $this->db->join('officer_hasil_periksa ohp', 'psh.id = ohp.id_surat_tugas', 'inner');
        $this->db->where('psh.ptk_id', $p_id);
        $this->db->where('psh.deleted_at', '1970-01-01 08:00:00');
        $this->db->group_by('psh.id');
        $this->db->order_by('MAX(ohp.tgl_periksa)', 'DESC');
        $this->db->limit(1);

        $query  = $this->db->get();
        $surtug = $query->row_array();

        if (!$surtug) {
            $query_fallback = $this->db->select('id, nomor, tanggal, perihal, status')
                ->from('ptk_surtug_header')
                ->where('ptk_id', $p_id)
                ->where('deleted_at', '1970-01-01 08:00:00')
                ->order_by('created_at', 'DESC')
                ->limit(1)
                ->get();
            $surtug = $query_fallback ? $query_fallback->row_array() : null;
        }

        if (!$surtug) return null;

        $id_st = $surtug['id'];

        return [
            'surat_tugas' => $surtug,
            'lokasi'      => $this->db->where('ptk_surtug_header_id', $id_st)->get('ptk_surtug_lokasi')->result_array(),
            'petugas'     => $this->db->select('mp.nama, mp.nip, sp.status')
                                ->from('ptk_surtug_petugas sp')
                                ->join('master_pegawai mp', 'sp.petugas_id = mp.id', 'left')
                                ->where('sp.ptk_surtug_header_id', $id_st)
                                ->get()->result_array(),
            'timeline'    => $this->db->where('surtug_header_id', $id_st)->order_by('time', 'ASC')->get('ptk_surtug_riwayat')->result_array(),
            'hasil'       => $this->db->where('id_surat_tugas', $id_st)->order_by('tgl_periksa', 'ASC')->get('officer_hasil_periksa')->result_array(),
        ];
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
            $sql     .= " AND DATE(ohp.tgl_periksa) >= ?";
            $params[] = $f['start_date'];
        }

        if (!empty($f['end_date'])) {
            $sql     .= " AND DATE(ohp.tgl_periksa) <= ?";
            $params[] = $f['end_date'];
        }
    }
}
