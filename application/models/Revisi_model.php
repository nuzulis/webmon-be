<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'models/BaseModelStrict.php';

class Revisi_model extends BaseModelStrict
{
    private function getPelepasanTable($karantina)
    {
        $map = [
            'H' => 'pn_pelepasan_kh',
            'I' => 'pn_pelepasan_ki',
            'T' => 'pn_pelepasan_kt',
        ];
        return $map[$karantina] ?? '';
    }

    /* ================= STEP 1 — AMBIL ID ================= */
    public function getIds($f, $limit, $offset)
    {
        $table = $this->getPelepasanTable($f['karantina']);
        if ($table === '') return [];

        $this->db->select('p.id, MAX(p8.tanggal) AS last_tanggal', false)
            ->from('ptk p')
            ->join("$table p8", 'p.id = p8.ptk_id')
            ->where('p.is_batal', '0')
            ->where('p8.nomor_seri IS NOT NULL', null, false)
            ->where("p8.nomor_seri != '*******'", null, false)
            ->where("p8.deleted_at != '1970-01-01 08:00:00'", null, false);

        // Filter UPT & Permohonan
        if (!empty($f['upt']) && $f['upt'] !== 'all') {
            $this->db->where('p.upt_id', $f['upt']);
        }
        if (!empty($f['permohonan'])) {
            $this->db->where('p.jenis_permohonan', $f['permohonan']);
        }

        // Filter Tanggal pada tabel Pelepasan
        if (!empty($f['start_date']) && !empty($f['end_date'])) {
            $this->db->where('p8.tanggal >=', $f['start_date']);
            $this->db->where('p8.tanggal <=', $f['end_date'] . ' 23:59:59');
        }

        $this->db->group_by('p.id')
                 ->order_by('last_tanggal', 'DESC')
                 ->limit($limit, $offset);

        $res = $this->db->get()->result_array();
        return array_column($res, 'id');
    }

    /* ================= STEP 2 — DATA DETAIL ================= */
public function getByIds($ids, $karantina = null)
{
    if (empty($ids)) return [];

    $table = $this->getPelepasanTable($karantina);
    if ($table === '') return [];

    $this->db->select("
        p.id,
        ANY_VALUE(IF(p.tssm_id IS NOT NULL, 'SSM', 'PTK')) AS sumber,
        ANY_VALUE(p.no_aju) AS no_aju,
        ANY_VALUE(p.no_dok_permohonan) AS no_dok_permohonan,
        ANY_VALUE(p.tgl_dok_permohonan) AS tgl_dok_permohonan,
        ANY_VALUE(mu.nama) AS upt,
        ANY_VALUE(mu.nama_satpel) AS nama_satpel,
        
        -- Info Dokumen yang Direvisi
        ANY_VALUE(p8.nomor) AS no_dok,
        ANY_VALUE(p8.nomor_seri) AS nomor_seri,
        ANY_VALUE(p8.tanggal) AS tgl_dok,
        ANY_VALUE(p8.alasan_delete) AS alasan_delete,
        ANY_VALUE(p8.deleted_at) AS deleted_at,
        
        -- Petugas
        ANY_VALUE(mp1.nama) AS penandatangan,
        ANY_VALUE(mp2.nama) AS yang_menghapus
    ", false);

    $this->db->from('ptk p')
        ->join("$table p8", 'p.id = p8.ptk_id')
        ->join('master_upt mu', 'p.kode_satpel = mu.id')
        ->join('master_pegawai mp1', 'p8.user_ttd_id = mp1.id', 'left')
        ->join('master_pegawai mp2', 'p8.user_delete = mp2.id', 'left');

    // --- PERBAIKAN DI SINI ---
    // Gunakan manual escaping untuk menghindari bug preg_match pada array besar
    $quoted_ids = array_map(function($id) {
        return $this->db->escape($id);
    }, $ids);
    
    $ids_string = implode(',', $quoted_ids);
    $this->db->where("p.id IN ($ids_string)", NULL, FALSE); 
    // -------------------------

    $this->db->where("p8.deleted_at != '1970-01-01 08:00:00'", null, false)
        ->group_by('p.id')
        ->order_by('tgl_dok', 'DESC');

    return $this->db->get()->result_array();
}
    public function countAll($f)
    {
        $table = $this->getPelepasanTable($f['karantina']);
        if ($table === '') return 0;

        $this->db->select('COUNT(DISTINCT p.id) AS total', false)
            ->from('ptk p')
            ->join("$table p8", 'p.id = p8.ptk_id')
            ->where('p.is_batal', '0')
            ->where("p8.deleted_at != '1970-01-01 08:00:00'", null, false);

        if (!empty($f['upt']) && $f['upt'] !== 'all') {
            $this->db->where('p.upt_id', $f['upt']);
        }
        if (!empty($f['permohonan'])) {
            $this->db->where('p.jenis_permohonan', $f['permohonan']);
        }

        if (!empty($f['start_date']) && !empty($f['end_date'])) {
            $this->db->where('p8.tanggal >=', $f['start_date']);
            $this->db->where('p8.tanggal <=', $f['end_date'] . ' 23:59:59');
        }

        $row = $this->db->get()->row();
        return $row ? (int) $row->total : 0;
    }
}