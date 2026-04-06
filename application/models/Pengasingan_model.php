<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'core/BaseModelStrict.php';

class Pengasingan_model extends BaseModelStrict
{
    public function __construct()
    {
        parent::__construct();
    }

    public function getAll(array $f): array
    {
        $sql = "
            SELECT
                p.id,
                MAX(mu.nama)          AS upt,
                MAX(mu.nama_satpel)   AS satpel,
                MAX(ps.nama_tempat)   AS tempat,
                MAX(ps.tgl_singmat_awal)  AS mulai,
                MAX(ps.tgl_singmat_akhir) AS selesai,
                GROUP_CONCAT(DISTINCT ps.komoditas_cetak ORDER BY ps.id SEPARATOR '|') AS komoditas_list,
                GROUP_CONCAT(DISTINCT ps.jumlah_mp       ORDER BY ps.id SEPARATOR '|') AS jumlah_list,
                GROUP_CONCAT(DISTINCT ps.satuan          ORDER BY ps.id SEPARATOR '|') AS satuan_list,
                GROUP_CONCAT(DISTINCT psd.pengamatan_ke  ORDER BY psd.pengamatan_ke SEPARATOR '|') AS pengamatan_list,
                GROUP_CONCAT(DISTINCT mr.nama            ORDER BY psd.pengamatan_ke SEPARATOR '|') AS rekomendasi_list,
                MAX(psd.nomor)           AS nomor_ngasmat,
                MAX(psd.tgl_pengamatan)  AS tgl_ngasmat,
                MAX(psd.gejala)          AS tanda
            FROM ptk p
            JOIN  pn_singmat ps              ON p.id = ps.ptk_id
            LEFT JOIN master_upt mu          ON p.kode_satpel = mu.id
            LEFT JOIN pn_singmat_detil psd   ON ps.id = psd.pn_singmat_id
            LEFT JOIN master_rekomendasi mr  ON psd.rekomendasi_id = mr.id
            WHERE p.is_verifikasi = '1'
              AND p.is_batal      = '0'
        ";

        $params = [];
        $this->applyFilter($f, $sql, $params);
        $sql .= " GROUP BY p.id ORDER BY MAX(ps.tgl_singmat_awal) DESC";

        $this->db->reconnect();
        $query = $this->db->query($sql, $params);
        return $query ? $query->result_array() : [];
    }

    public function getFullData(array $f): array
    {
        $sql = "
            SELECT
                p.id,
                mu.nama              AS upt,
                mu.nama_satpel       AS satpel,
                ps.komoditas_cetak   AS komoditas,
                ps.nama_tempat       AS tempat,
                ps.tgl_singmat_awal  AS mulai,
                ps.tgl_singmat_akhir AS selesai,
                ps.target            AS targets,
                ps.jumlah_mp         AS jumlah,
                ps.satuan            AS satuan,
                psd.nomor            AS nomor_ngasmat,
                psd.tanggal          AS tgl_tk2,
                psd.pengamatan_ke    AS pengamatan,
                psd.tgl_pengamatan   AS tgl_ngasmat,
                psd.gejala           AS tanda,
                mr.nama              AS rekom,
                mrk.nama             AS rekom_lanjut,
                psd.busuk            AS bus,
                psd.rusak            AS rus,
                psd.mati             AS dead,
                mperlakuan.nama      AS ttd,
                mp.nama              AS ttd1,
                mpeg.nama            AS inputer,
                psd.created_at       AS tgl_input
            FROM ptk p
            JOIN  pn_singmat ps              ON p.id = ps.ptk_id
            LEFT JOIN master_upt mu          ON p.kode_satpel = mu.id
            LEFT JOIN pn_singmat_detil psd   ON ps.id = psd.pn_singmat_id
            LEFT JOIN master_rekomendasi mr  ON psd.rekomendasi_id = mr.id
            LEFT JOIN master_rekomendasi mrk ON psd.rekomendasi_lanjut = mrk.id
            LEFT JOIN master_pegawai mperlakuan ON psd.user_ttd1_id = mperlakuan.id
            LEFT JOIN master_pegawai mp      ON psd.user_ttd2_id = mp.id
            LEFT JOIN master_pegawai mpeg    ON psd.user_id = mpeg.id
            WHERE p.is_verifikasi = '1'
              AND p.is_batal      = '0'
        ";

        $params = [];
        $this->applyFilter($f, $sql, $params);
        $sql .= " ORDER BY p.id ASC, psd.pengamatan_ke ASC";

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
            $params[] = strtoupper($f['karantina']);
        }

        $lingkup = $f['lingkup'] ?? '';
        if (!empty($lingkup) && !in_array(strtolower($lingkup), ['all', 'semua'], true)) {
            $sql     .= " AND p.jenis_permohonan = ?";
            $params[] = strtoupper($lingkup);
        }

        if (!empty($f['start_date'])) {
            $sql     .= " AND ps.tgl_singmat_awal >= ?";
            $params[] = $f['start_date'] . ' 00:00:00';
        }

        if (!empty($f['end_date'])) {
            $sql     .= " AND ps.tgl_singmat_awal <= ?";
            $params[] = $f['end_date'] . ' 23:59:59';
        }
    }
}
