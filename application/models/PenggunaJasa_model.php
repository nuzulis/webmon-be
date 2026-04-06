<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'core/BaseModelStrict.php';

class PenggunaJasa_model extends BaseModelStrict
{
    public function __construct()
    {
        parent::__construct();
    }

    public function getAll(array $f): array
    {
        $sql = "
            SELECT
                pj.id,
                ANY_VALUE(u.id)                  AS uid,
                ANY_VALUE(pj.user_id)            AS user_id,
                ANY_VALUE(u.name)                AS pemohon,
                ANY_VALUE(pre.jenis_perusahaan)  AS jenis_perusahaan,
                ANY_VALUE(pj.nama_perusahaan)    AS nama_perusahaan,
                ANY_VALUE(pj.jenis_identitas)    AS jenis_identitas,
                ANY_VALUE(pj.nomor_identitas)    AS nomor_identitas,
                ANY_VALUE(pj.nitku)              AS nitku,
                ANY_VALUE(r.master_upt_id)       AS master_upt_id,
                ANY_VALUE(pj.lingkup_aktifitas)  AS lingkup_aktifitas,
                ANY_VALUE(pj.rerata_frekuensi)   AS rerata_frekuensi,
                ANY_VALUE(pj.daftar_komoditas)   AS daftar_komoditas,
                ANY_VALUE(pj.tempat_karantina)   AS tempat_karantina,
                ANY_VALUE(pj.status_kepemilikan) AS status_kepemilikan,
                ANY_VALUE(mu.nama)               AS upt,
                ANY_VALUE(pj.email)              AS email,
                ANY_VALUE(pj.nomor_registrasi)   AS nomor_registrasi,
                MAX(r.created_at)                AS tgl_registrasi,
                ANY_VALUE(r.blockir)             AS blockir
            FROM dbregptk.pj_barantins pj
            JOIN  dbregptk.registers r           ON pj.id = r.pj_barantin_id
            LEFT JOIN dbregptk.users u           ON pj.user_id = u.id
            LEFT JOIN dbregptk.pre_registers pre ON r.pre_register_id = pre.id
            LEFT JOIN barantin.master_upt mu     ON mu.id = r.master_upt_id
            WHERE r.status = 'DISETUJUI'
        ";

        $params = [];
        $this->applyFilter($f, $sql, $params);
        $sql .= " GROUP BY pj.id ORDER BY MAX(r.created_at) DESC";

        $this->db->reconnect();
        $query = $this->db->query($sql, $params);
        return $query ? $query->result_array() : [];
    }

    public function getFullData(array $f): array
    {
        return $this->getAll($f);
    }

    private function applyFilter(array $f, string &$sql, array &$params): void
    {
        if (!empty($f['upt']) && !in_array(strtolower($f['upt']), ['all', 'semua', 'undefined', '1000'], true)) {
            $sql     .= " AND r.master_upt_id = ?";
            $params[] = $f['upt'];
        }

        if (!empty($f['permohonan']) && strtolower($f['permohonan']) !== 'all') {
            $mapping = [
                'IM' => 'Import',
                'EX' => 'Export',
                'DK' => 'Domestik Keluar',
                'DM' => 'Domestik Masuk',
            ];
            $term     = $mapping[$f['permohonan']] ?? $f['permohonan'];
            $sql     .= " AND pj.lingkup_aktifitas LIKE ?";
            $params[] = '%' . $term . '%';
        }
    }

    public function get_profil_lengkap($id)
    {
        $this->db->select("
            pj.id,
            ANY_VALUE(u.id) AS uid,
            ANY_VALUE(pre.pemohon) as pemohon,
            ANY_VALUE(pre.jenis_perusahaan) AS tipe_kantor,
            ANY_VALUE(pj.jenis_perusahaan) as jenis_perusahaan,
            ANY_VALUE(pj.nama_perusahaan) as nama_perusahaan,
            ANY_VALUE(pj.jenis_identitas) as jenis_identitas,
            ANY_VALUE(pj.nomor_identitas) as nomor_identitas,
            ANY_VALUE(pj.nitku) as nitku,
            ANY_VALUE(pj.alamat) as alamat,
            ANY_VALUE(pj.email) as email,
            ANY_VALUE(pj.telepon) as telepon,
            ANY_VALUE(mu.nama) AS upt,
            ANY_VALUE(pj.lingkup_aktifitas) as lingkup_aktifitas,
            ANY_VALUE(pj.daftar_komoditas) as daftar_komoditas,
            ANY_VALUE(pj.rerata_frekuensi) as rerata_frekuensi,
            ANY_VALUE(pj.tempat_karantina) as tempat_karantina,
            ANY_VALUE(pj.status_kepemilikan) as status_kepemilikan,
            MAX(r.created_at) AS tgl_registrasi,
            ANY_VALUE(r.blockir) as blockir,
            ANY_VALUE(r.status) AS status_registrasi
        ", false);

        $this->db->from('dbregptk.registers r')
            ->join('dbregptk.pj_barantins pj', 'r.pj_barantin_id = pj.id')
            ->join('dbregptk.users u', 'pj.user_id = u.id', 'left')
            ->join('dbregptk.pre_registers pre', 'r.pre_register_id = pre.id', 'left')
            ->join('barantin.master_upt mu', 'mu.id = r.master_upt_id', 'left')
            ->where('pj.id', $id)
            ->group_by('pj.id');

        $res = $this->db->get();
        return $res ? $res->row_array() : null;
    }

    public function get_history_ptk($pj_id)
    {
        $this->db->select('id, no_dok_permohonan, tgl_dok_permohonan, jenis_karantina, jenis_permohonan, nama_pengirim, nama_pemohon')
            ->from('ptk')
            ->where('pengguna_jasa_id', $pj_id)
            ->where('is_batal', '0')
            ->order_by('tgl_dok_permohonan', 'DESC')
            ->limit(10);

        return $this->db->get()->result_array();
    }
}
