<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'models/BaseModelStrict.php';

class Pengasingan_model extends BaseModelStrict
{
    /* =====================================================
     * STEP 1 — AMBIL ID SAJA (RINGAN)
     * ===================================================== */
    public function getIds($f, $limit, $offset)
    {
        $this->db->select('p.id, MAX(ps.tgl_singmat_awal) AS last_tgl', false)
            ->from('ptk p')
            ->join('pn_singmat ps', 'p.id = ps.ptk_id')
            ->where([
                'p.is_verifikasi' => '1',
                'p.is_batal'      => '0',
            ]);

        // Berikan alias 'p' agar filter karantina/upt bekerja
        $this->applyCommonFilter($f, 'p');
        $this->applyDateFilter('ps.tgl_singmat_awal', $f);

        $this->db->group_by('p.id')
                 ->order_by('last_tgl', 'DESC')
                 ->limit($limit, $offset);

        $res = $this->db->get()->result_array();
        return array_column($res, 'id');
    }

    public function getByIds($ids, $is_export = false)
    {
        if (empty($ids)) return [];

        $this->db->select("
            p.id,
            mu.nama AS upt, 
            mu.nama_satpel AS satpel,
            ps.komoditas_cetak AS komoditas,
            ps.nama_tempat AS tempat,
            ps.tgl_singmat_awal AS mulai,
            ps.tgl_singmat_akhir AS selesai,
            ps.target AS targets,
            ps.jumlah_mp AS jumlah,
            ps.satuan AS satuan,
            psd.nomor AS nomor_ngasmat,
            psd.tanggal AS tgl_tk2,
            psd.pengamatan_ke AS pengamatan,
            psd.tgl_pengamatan AS tgl_ngasmat,
            psd.gejala AS tanda,
            mr.nama AS rekom,
            mrk.nama AS rekom_lanjut,
            psd.busuk AS bus, psd.rusak AS rus, psd.mati AS dead,
            mperlakuan.nama AS ttd,
            mp.nama AS ttd1,
            mpeg.nama AS inputer,
            psd.created_at AS tgl_input
        ", false);

        $this->db->from('ptk p')
            ->join('pn_singmat ps', 'p.id = ps.ptk_id')
            ->join('master_upt mu', 'p.kode_satpel = mu.id')
            ->join('pn_singmat_detil psd', 'ps.id = psd.pn_singmat_id') // Join langsung untuk export detail
            ->join('master_rekomendasi mr', 'psd.rekomendasi_id = mr.id', 'left')
            ->join('master_rekomendasi mrk', 'psd.rekomendasi_lanjut = mrk.id', 'left')
            ->join('master_pegawai mperlakuan', 'psd.user_ttd1_id = mperlakuan.id', 'left')
            ->join('master_pegawai mp', 'psd.user_ttd2_id = mp.id', 'left')
            ->join('master_pegawai mpeg', 'psd.user_id = mpeg.id', 'left');
        $this->db->where_in('p.id', $ids)
                 ->order_by('p.id', 'ASC')
                 ->order_by('psd.pengamatan_ke', 'ASC');

        return $this->db->get()->result_array();
    }


    public function countAll($f)
    {
        $this->db->select('COUNT(DISTINCT p.id) AS total', false)
            ->from('ptk p')
            ->join('pn_singmat ps', 'p.id = ps.ptk_id')
            ->where([
                'p.is_verifikasi' => '1',
                'p.is_batal'      => '0',
            ]);

        $this->applyCommonFilter($f, 'p');
        $this->applyDateFilter('ps.tgl_singmat_awal', $f);

        $row = $this->db->get()->row();
        return $row ? (int) $row->total : 0;
    }
}