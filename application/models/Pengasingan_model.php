<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'models/BaseModelStrict.php';

class Pengasingan_model extends BaseModelStrict
{
    private function localFilter($f, $alias = 'p')
    {
        if (!empty($f['upt']) && !in_array($f['upt'], ['Semua', 'all', '1000'])) {
            $this->db->where($alias . '.kode_satpel', $f['upt']);
        }

        if (!empty($f['karantina']) && !in_array($f['karantina'], ['Semua', 'all'])) {
            $this->db->where($alias . '.jenis_karantina', $f['karantina']); 
        }
        if (!empty($f['lingkup']) && !in_array($f['lingkup'], ['Semua', 'all']))  {
            $this->db->where($alias . '.jenis_permohonan', $f['lingkup']); 
        }
    }
  
    public function getIds($f, $limit, $offset)
    {
        $this->db->reset_query();

        $this->db->select('p.id')
            ->from('ptk p')
            ->join('pn_singmat ps', 'p.id = ps.ptk_id', 'inner')
            ->where([
                'p.is_verifikasi' => '1',
                'p.is_batal'      => '0',
            ]);

        if (!empty($f['upt']) && !in_array($f['upt'], ['Semua','all','1000'])) {
            $this->db->where('p.kode_satpel', $f['upt']);
        }

        if (!empty($f['karantina'])) {
            $this->db->where('p.jenis_karantina', $f['karantina']);
        }

        if (!empty($f['lingkup'])) {
            $this->db->where('p.jenis_permohonan', $f['lingkup']);
        }
        if (!empty($f['start_date'])) {
            $this->db->where('ps.tgl_singmat_awal >=', $f['start_date']);
        }

        if (!empty($f['end_date'])) {
            $this->db->where('ps.tgl_singmat_awal <=', $f['end_date']);
        }

        $this->db->group_by('p.id');
        $this->db->order_by('p.created_at', 'DESC');
        $this->db->limit($limit, $offset);

        $result = $this->db->get()->result_array();
        
        return array_column($result, 'id');
    }

    public function getByIds($ids, $is_export = false)
    {
        if (empty($ids)) {
            log_message('debug', 'getByIds: Empty IDs array');
            return [];
        }


        if ($is_export) {
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
                psd.busuk AS bus, 
                psd.rusak AS rus, 
                psd.mati AS dead,
                mperlakuan.nama AS ttd, 
                mp.nama AS ttd1, 
                mpeg.nama AS inputer,
                psd.created_at AS tgl_input
            ", false);
        } else {
            $this->db->select("
                p.id, 
                MAX(mu.nama) AS upt, 
                MAX(mu.nama_satpel) AS satpel,
                MAX(ps.nama_tempat) AS tempat,
                MAX(ps.tgl_singmat_awal) AS mulai,
                MAX(ps.tgl_singmat_akhir) AS selesai,
                GROUP_CONCAT(DISTINCT ps.komoditas_cetak ORDER BY ps.id SEPARATOR '|') as komoditas_list,
                GROUP_CONCAT(DISTINCT CONCAT(ps.jumlah_mp) ORDER BY ps.id SEPARATOR '|') as jumlah_list,
                GROUP_CONCAT(DISTINCT ps.satuan ORDER BY ps.id SEPARATOR '|') as satuan_list,
                GROUP_CONCAT(DISTINCT psd.pengamatan_ke ORDER BY psd.pengamatan_ke SEPARATOR '|') as pengamatan_list,
                GROUP_CONCAT(DISTINCT mr.nama ORDER BY psd.pengamatan_ke SEPARATOR '|') as rekomendasi_list,
                MAX(psd.nomor) as nomor_ngasmat,
                MAX(psd.tgl_pengamatan) as tgl_ngasmat,
                MAX(psd.gejala) as tanda
            ", false);
        }

        $this->db->from('ptk p')
            ->join('pn_singmat ps', 'p.id = ps.ptk_id', 'left')
            ->join('master_upt mu', 'p.kode_satpel = mu.id', 'left')
            ->join('pn_singmat_detil psd', 'ps.id = psd.pn_singmat_id', 'left')
            ->join('master_rekomendasi mr', 'psd.rekomendasi_id = mr.id', 'left')
            ->join('master_rekomendasi mrk', 'psd.rekomendasi_lanjut = mrk.id', 'left')
            ->join('master_pegawai mperlakuan', 'psd.user_ttd1_id = mperlakuan.id', 'left')
            ->join('master_pegawai mp', 'psd.user_ttd2_id = mp.id', 'left')
            ->join('master_pegawai mpeg', 'psd.user_id = mpeg.id', 'left');

        $this->db->where_in('p.id', $ids);

        if (!$is_export) {
            $this->db->group_by('p.id');
            $this->db->order_by('p.id', 'ASC'); 
        } else {
            $this->db->order_by('p.id', 'ASC');
            $this->db->order_by('psd.pengamatan_ke', 'ASC');
        }

        $result = $this->db->get()->result_array();
        return $result;
    }

    public function countAll($f)
    {
        $this->db->select('COUNT(DISTINCT p.id) AS total', false)
            ->from('ptk p')
            ->join('pn_singmat ps', 'p.id = ps.ptk_id', 'inner')
            ->where([
                'p.is_verifikasi' => '1',
                'p.is_batal'      => '0',
            ]);

        $this->localFilter($f, 'p');

        if (!empty($f['start_date'])) {
            $this->db->where('ps.tgl_singmat_awal >=', $f['start_date']);
        }

        if (!empty($f['end_date'])) {
            $this->db->where('ps.tgl_singmat_awal <=', $f['end_date']);
        }

        $row = $this->db->get()->row();
        
        return $row ? (int) $row->total : 0;
    }
    public function getAllIdsForExport($f)
{
    $this->db->reset_query();

    $this->db->select('DISTINCT p.id', false)
        ->from('ptk p')
        ->join('pn_singmat ps', 'ps.ptk_id = p.id', 'inner')
        ->where([
            'p.is_verifikasi' => '1',
            'p.is_batal'      => '0',
        ]);

    if (!empty($f['upt']) && !in_array($f['upt'], ['Semua','all','1000'])) {
        $this->db->where('p.kode_satpel', $f['upt']);
    }

    if (!empty($f['karantina'])) {
        $this->db->where('p.jenis_karantina', $f['karantina']);
    }

    if (!empty($f['lingkup'])) {
        $this->db->where('p.jenis_permohonan', $f['lingkup']);
    }

    if (!empty($f['start_date'])) {
        $this->db->where('DATE(ps.tgl_singmat_awal) >=', $f['start_date']);
    }

    if (!empty($f['end_date'])) {
        $this->db->where('DATE(ps.tgl_singmat_awal) <=', $f['end_date']);
    }

    return array_column(
        $this->db->get()->result_array(),
        'id'
    );
}

}