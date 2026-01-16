<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'models/BaseModelStrict.php';

class Elab_model extends BaseModelStrict
{
    /* =====================================================
     * STEP 1 — AMBIL ID PTK SAJA
     * PERBAIKAN: Hapus 'array', 'int', dan ': array'
     * ===================================================== */
    public function getIds($f, $limit, $offset)
    {
        $this->db->select('ptk.id, MAX(pr.tanggal) AS last_tgl', false)
            ->from('barantin.ptk ptk')
            ->join('`elab-barantin`.penerimaan pr', 'pr.ptkId = ptk.id', 'left')
            ->where([
                'ptk.is_verifikasi' => '1',
                'ptk.is_batal'      => '0',
                'ptk.status_ptk'    => '1',
            ]);

        // Gunakan filter scope otomatis dari Induk
        $this->applyCommonFilter($f);

        // FILTER TANGGAL e-LAB
        if (method_exists($this, 'applyDateFilter')) {
            $this->applyDateFilter('pr.tanggal', $f);
        }

        $this->db->group_by('ptk.id')
                 ->order_by('last_tgl', 'DESC')
                 ->limit($limit, $offset);

        return array_column(
            $this->db->get()->result_array(),
            'id'
        );
    }

    /* =====================================================
     * STEP 2 — DATA DETAIL e-LAB
     * PERBAIKAN: Hapus 'array' dan ': array'
     * ===================================================== */
    public function getByIds($ids)
{
    if (empty($ids)) return [];

    $this->db->select("
        ptk.id AS ptk_id,
        upt.nama AS upt,
        upt.nama_satpel AS satpel,
        pr.eksDataNomor AS no_ba_sampling,
        pr.nomor AS no_verifikasi,
        pr.tanggal AS tgl_verifikasi,
        pd.komoditasUmum AS komoditas,
        tu.deskripsi AS nama_target_uji,
        muji.deskripsi AS nama_metode_uji,
        sd.hasil AS hasil_uji,
        hul.kesimpulan AS kesimpulan,
        hul.namaTtd AS petugas
    ", false);

    $this->db->from('barantin.ptk ptk')
        ->join('`elab-barantin`.penerimaan pr', 'pr.ptkId = ptk.id', 'left')
        ->join('`elab-barantin`.hasil_uji_lab hul', 'hul.penerimaanId = pr.id', 'left')
        ->join('`elab-barantin`.penerimaan_detil pd', 'pd.penerimaanId = pr.id', 'left')
        ->join('`elab-barantin`.sampel_detil sd', 'sd.penerimaanId = pr.id', 'left')
        ->join('`elab-barantin`.target_uji tu', 'tu.id = sd.targetUjiId', 'left')
        ->join('`elab-barantin`.metode_uji muji', 'muji.id = sd.metodeUjiId', 'left')
        ->join('barantin.master_upt upt', 'ptk.kode_satpel = upt.id', 'left');

    $this->db->where_in('ptk.id', $ids);
    
    // Perbaikan: Tambahkan semua kolom non-agregasi ke GROUP BY
    // Atau jika datanya memang unik per baris sampel, 
    // Anda bisa mengelompokkan berdasarkan primary key tabel-tabel terkait
    $this->db->group_by([
        'pr.id', 
        'ptk.id', 
        'upt.nama', 
        'upt.nama_satpel', 
        'pd.id', 
        'sd.id', 
        'tu.id', 
        'muji.id', 
        'hul.id'
    ]);

    $this->db->order_by('pr.tanggal', 'DESC');

    return $this->db->get()->result_array();
}

    /* =====================================================
     * TOTAL DATA (PAGINATION)
     * PERBAIKAN: Hapus 'array' dan ': int'
     * ===================================================== */
    public function countAll($f)
    {
        $this->db->select('COUNT(DISTINCT ptk.id) AS total', false)
            ->from('barantin.ptk ptk')
            ->join('`elab-barantin`.penerimaan pr', 'pr.ptkId = ptk.id', 'left')
            ->where([
                'ptk.is_verifikasi' => '1',
                'ptk.is_batal'      => '0',
                'ptk.status_ptk'    => '1',
            ]);

        $this->applyCommonFilter($f);

        if (method_exists($this, 'applyDateFilter')) {
            $this->applyDateFilter('pr.tanggal', $f);
        }

        $res = $this->db->get()->row();
        return $res ? (int) $res->total : 0;
    }
}