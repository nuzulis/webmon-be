<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'models/BaseModelStrict.php';

class Domasonline_model extends BaseModelStrict
{
    private function getTable($karantina) {
        $k = strtoupper(substr($karantina, -1)); 
        return match ($k) {
            'H' => 'pn_pelepasan_kh',
            'I' => 'pn_pelepasan_ki',
            'T' => 'pn_pelepasan_kt',
            default => '',
        };
    }

    public function getList($f, $limit, $offset) {
        $table = $this->getTable($f['karantina']);
        if ($table === '') return [];

        $kar = strtoupper(substr($f['karantina'], -1));
        $tabel_kom = "komoditas_" . ($kar == 'H' ? 'hewan' : ($kar == 'I' ? 'ikan' : 'tumbuhan'));

        $this->db->select("
            p.id, 
            MAX(p.no_aju) AS no_aju,
            MAX(target_upt.nama) AS upt_tujuan,
            MAX(target_upt.nama_satpel) AS satpel_tujuan,
            MAX(mu_asal.nama) AS upt_asal,
            MAX(mu_asal.nama_satpel) AS satpel_asal,
            MAX(p8.nomor) AS nkt, 
            MAX(p8.tanggal) AS tanggal_lepas,
            MAX(p.no_dok_permohonan) AS no_dok_permohonan, 
            MAX(p.tgl_dok_permohonan) AS tgl_dok_permohonan,
            MAX(p.nama_pemohon) AS nama_pemohon, 
            MAX(p.nama_pengirim) AS nama_pengirim, 
            MAX(p.nama_penerima) AS nama_penerima,
            GROUP_CONCAT(kom.nama SEPARATOR '<br>') AS komoditas,
            GROUP_CONCAT(pkom.volume_lain SEPARATOR '<br>') AS volume,
            GROUP_CONCAT(ms.nama SEPARATOR '<br>') AS satuan,
            IF(MAX(dm.id) IS NOT NULL, 'DITERIMA', 'BELUM DITANGGANI') AS status_penerimaan,
            MAX(dm.no_dok_permohonan) AS no_dok_dm,
            MAX(dm.tgl_dok_permohonan) AS tgl_dok_dm
        ", false);

        $this->db->from('ptk p')
            ->join("$table p8", "p.id = p8.ptk_id") 
            ->join('master_upt mu_asal', 'p.kode_satpel = mu_asal.id', 'left')
            ->join('ptk_komoditas pkom', "p.id = pkom.ptk_id AND pkom.deleted_at = '1970-01-01 08:00:00'", 'left')
            ->join('master_satuan ms', 'pkom.satuan_lain_id = ms.id', 'left')
            ->join("$tabel_kom kom", 'pkom.komoditas_id = kom.id', 'left')
            ->join('master_pelabuhan mp', 'p.pelabuhan_bongkar_id = mp.id', 'left')
           ->join('master_upt target_upt', 'mp.kode_upt = target_upt.id', 'left')

            ->join('ptk dm', 'p.id = dm.ptk_asal_id', 'left');

        $this->applyFilters($f); 

        $this->db->group_by('p.id')
                 ->order_by('MAX(p8.tanggal)', 'DESC')
                 ->limit($limit, $offset);

        return $this->db->get()->result_array();
    }

    public function countAll($f) {
    $table = $this->getTable($f['karantina']);
    if (empty($table)) return 0;

    $this->db->from('ptk p');
    $this->db->join("$table p8", "p.id = p8.ptk_id");
    if (!empty($f['search'])) {
        $kar = strtoupper(substr($f['karantina'], -1));
        $tabel_kom = "komoditas_" . ($kar == 'H' ? 'hewan' : ($kar == 'I' ? 'ikan' : 'tumbuhan'));
        $this->db->join('ptk_komoditas pkom', "p.id = pkom.ptk_id AND pkom.deleted_at = '1970-01-01 08:00:00'", 'left');
        $this->db->join("$tabel_kom kom", 'pkom.komoditas_id = kom.id', 'left');
    }

    $this->db->join('master_pelabuhan mp', 'p.pelabuhan_bongkar_id = mp.id', 'left');
    $this->db->join('master_upt target_upt', 'mp.kode_upt = target_upt.kode_upt', 'left');

    $this->applyFilters($f);
    $this->db->select('p.id');
    $this->db->group_by('p.id');
    $query = $this->db->get();
    
    return $query->num_rows();
}

    private function applyFilters($f) {
    if (!empty($f['upt']) && !in_array(strtolower($f['upt']), ['all','semua'])) {
        $prefix = substr($f['upt'], 0, 2);
        $this->db->like("target_upt.kode_upt", $prefix, 'after');
    }

    if (!empty($f['start_date']) && !empty($f['end_date'])) {
        $this->db->where("p8.tanggal >=", $f['start_date'] . ' 00:00:00');
        $this->db->where("p8.tanggal <=", $f['end_date'] . ' 23:59:59');
    }

    if (!empty($f['search'])) {
        $q = $f['search'];
        $this->db->group_start(); // (
            $this->db->like('p8.nomor', $q); 
            $this->db->or_like('p.no_aju', $q);
            $this->db->or_like('p.nama_pengirim', $q);
            $this->db->or_like('p.nama_penerima', $q);
            $this->db->or_like('kom.nama', $q);
        $this->db->group_end(); // )
    }

    $this->db->where([
        'p.jenis_permohonan' => 'DK',
        'p.is_verifikasi'    => '1',
        'p.is_batal'         => '0',
        'p8.deleted_at'      => '1970-01-01 08:00:00'
    ]);
}
    

    public function getExportByFilter($f)
{
    $table = $this->getTable($f['karantina']);
    if ($table === '') return [];

    $kar = strtoupper(substr($f['karantina'], -1));
    $tabel_kom = "komoditas_" . ($kar == 'H' ? 'hewan' : ($kar == 'I' ? 'ikan' : 'tumbuhan'));

    $this->db->select("
        p.id, p.no_aju, p.no_dok_permohonan, p.tgl_dok_permohonan,
        p8.nomor AS nkt, p8.tanggal AS tanggal_lepas,
        mu_asal.nama AS upt_asal, mu_asal.nama_satpel AS satpel_asal,
        target_upt.nama AS upt_tujuan, target_upt.nama_satpel AS satpel_tujuan,
        p.nama_pengirim, p.nama_penerima,
        kom.nama AS komoditas,
        pkom.volume_lain AS volume,
        ms.nama AS satuan,
        CASE 
            WHEN dm.id IS NOT NULL THEN 'DITERIMA'
            ELSE 'BELUM DITANGGANI'
        END AS status_penerimaan,
        dm.no_dok_permohonan AS no_dok_dm,
        dm.tgl_dok_permohonan AS tgl_dok_dm
    ", false);

   $this->db->from('ptk p')
            ->join("$table p8", "p.id = p8.ptk_id") 
            ->join('master_upt mu_asal', 'p.kode_satpel = mu_asal.id', 'left')
            ->join('ptk_komoditas pkom', "p.id = pkom.ptk_id AND pkom.deleted_at = '1970-01-01 08:00:00'", 'left')
            ->join('master_satuan ms', 'pkom.satuan_lain_id = ms.id', 'left')
            ->join("$tabel_kom kom", 'pkom.komoditas_id = kom.id', 'left')
            ->join('master_pelabuhan mp', 'p.pelabuhan_bongkar_id = mp.id', 'left')
           ->join('master_upt target_upt', 'mp.kode_upt = target_upt.id', 'left')

            ->join('ptk dm', 'p.id = dm.ptk_asal_id', 'left');
    $this->applyFilters($f, $table);

    return $this->db->order_by('p8.tanggal', 'DESC')
                    ->order_by('p.id', 'ASC')
                    ->get()->result_array();
}
public function getIds($f, $limit, $offset) { return []; }
    public function getByIds($ids) { return []; }
}