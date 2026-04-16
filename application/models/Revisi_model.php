<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require_once APPPATH . 'models/BaseModelStrict.php';

class Revisi_model extends BaseModelStrict
{
    protected $db_excel;

    public function __construct()
    {
        parent::__construct();
        $this->db_excel = $this->load->database('excel', TRUE);
    }

    private function getPelepasanTable(string $karantina): string
    {
        $map = ['H' => 'pn_pelepasan_kh', 'I' => 'pn_pelepasan_ki', 'T' => 'pn_pelepasan_kt'];
        return $map[strtoupper($karantina)] ?? $map['T'];
    }

    public function getAll(array $f): array
    {
        $table = $this->getPelepasanTable($f['karantina'] ?? 'T');
        $wkt   = '1970-01-01 08:00:00';

        $this->db->select("
            p.id,
            ANY_VALUE(IF(p.tssm_id IS NOT NULL, 'SSM', 'PTK')) AS sumber,
            ANY_VALUE(p.no_aju) AS no_aju,
            ANY_VALUE(mu.nama) AS upt,
            ANY_VALUE(mu.nama_satpel) AS nama_satpel,
            ANY_VALUE(p.no_dok_permohonan) AS no_dok_permohonan,
            ANY_VALUE(p.tgl_dok_permohonan) AS tgl_dok_permohonan,
            GROUP_CONCAT(p8.nomor SEPARATOR '<br>') AS no_dok,
            GROUP_CONCAT(p8.nomor_seri SEPARATOR '<br>') AS nomor_seri,
            MAX(p8.tanggal) AS tgl_dok,
            GROUP_CONCAT(p8.deleted_at SEPARATOR '<br>') AS deleted_at,
            GROUP_CONCAT(p8.alasan_delete SEPARATOR '<br>') AS alasan_delete,
            ANY_VALUE(mp1.nama) AS penandatangan,
            ANY_VALUE(mp2.nama) AS yang_menghapus
        ", false)
        ->from('ptk p')
        ->join("$table p8", 'p.id = p8.ptk_id')
        ->join('master_upt mu', 'p.kode_satpel = mu.id', 'left')
        ->join('master_pegawai mp1', 'p8.user_ttd_id = mp1.id', 'left')
        ->join('master_pegawai mp2', 'p8.user_delete = mp2.id', 'left');

        $this->applyFilter($f);

        $this->db->group_by('p.id');
        $this->db->having("SUM(p8.deleted_at = '$wkt') >= 1", null, false);
        $this->db->having("SUM(p8.deleted_at != '$wkt') >= 1", null, false);
        $this->db->order_by('MAX(p8.tanggal)', 'DESC');

        return $this->db->get()->result_array();
    }

    public function getForExcel(array $f): array
    {
        $table = $this->getPelepasanTable($f['karantina'] ?? 'T');
        $wkt   = '1970-01-01 08:00:00';

        $this->db_excel->select("
            p.id,
            IF(p.tssm_id IS NOT NULL, 'SSM', 'PTK') AS sumber,
            p.no_aju,
            mu.nama AS upt,
            mu.nama_satpel,
            p.no_dok_permohonan,
            p.tgl_dok_permohonan,
            p8.nomor AS no_dok,
            p8.nomor_seri,
            p8.tanggal AS tgl_dok,
            p8.deleted_at,
            p8.alasan_delete,
            mp1.nama AS penandatangan,
            mp2.nama AS yang_menghapus
        ", false)
        ->from('ptk p')
        ->join("$table p8", 'p.id = p8.ptk_id')
        ->join('master_upt mu', 'p.kode_satpel = mu.id', 'left')
        ->join('master_pegawai mp1', 'p8.user_ttd_id = mp1.id', 'left')
        ->join('master_pegawai mp2', 'p8.user_delete = mp2.id', 'left');

        $this->applyFilter($f, $this->db_excel);
        $this->db_excel->where("p8.deleted_at != '$wkt'", null, false);
        $this->db_excel->where("EXISTS (SELECT 1 FROM $table px WHERE px.ptk_id = p.id AND px.deleted_at = '$wkt')", null, false);

        $this->db_excel->order_by('p.id', 'DESC')
                       ->order_by('p8.tanggal', 'DESC');

        return $this->db_excel->get()->result_array();
    }

    private function applyFilter(array $f, $db = null): void
    {
        $db = $db ?? $this->db;

        $db->where('p.is_batal', '0');

        if (!empty($f['upt']) && $f['upt'] !== 'all') {
            $db->where('p.upt_id', $f['upt']);
        }

        $lingkup = $f['lingkup'] ?? ($f['permohonan'] ?? '');
        if (!empty($lingkup) && !in_array(strtolower($lingkup), ['all', 'semua', ''])) {
            $db->where('p.jenis_permohonan', strtoupper($lingkup));
        }

        if (!empty($f['start_date'])) {
            $db->where('p8.tanggal >=', $f['start_date']);
        }
        if (!empty($f['end_date'])) {
            $db->where('p8.tanggal <=', $f['end_date'] . ' 23:59:59');
        }

        $db->where('p8.nomor_seri IS NOT NULL', null, false);
        $db->where("p8.nomor_seri != '*******'", null, false);
    }
}
