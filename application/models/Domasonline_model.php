<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'core/BaseModelStrict.php';

class Domasonline_model extends BaseModelStrict
{
    protected $db_excel;

    public function __construct()
    {
        parent::__construct();
        $this->db_excel = $this->load->database('excel', TRUE);
    }

    private function getTable($karantina) {
        $k = strtoupper(substr($karantina, -1)); 
        return match ($k) {
            'H' => 'pn_pelepasan_kh',
            'I' => 'pn_pelepasan_ki',
            'T' => 'pn_pelepasan_kt',
            default => '',
        };
    }

public function getAll(array $f): array
    {
        $ids = $this->fetchIds($f);
        return empty($ids) ? [] : $this->getByIds($ids);
    }

    private function fetchIds(array $f): array
    {
        $table = $this->getTable($f['karantina']);
        if ($table === '') return [];
        $this->db->select('p.id', false)
            ->from('ptk p')
            ->join("$table p8", "p.id = p8.ptk_id")
            ->join('master_pelabuhan mp', 'p.pelabuhan_bongkar_id = mp.id', 'left')
            ->join('master_upt target_upt', 'mp.kode_upt = target_upt.id', 'left');

        $this->applyManualFilter($f);
        $this->db->group_by('p.id');

        return array_column($this->db->get()->result_array(), 'id');
    }

    public function getIds(array $f, int $limit, int $offset): array
    {
        $table = $this->getTable($f['karantina']);
        if ($table === '') return [];
        $this->db->select('p.id', false)
            ->from('ptk p')
            ->join("$table p8", "p.id = p8.ptk_id")
            ->join('master_pelabuhan mp', 'p.pelabuhan_bongkar_id = mp.id', 'left')
            ->join('master_upt target_upt', 'mp.kode_upt = target_upt.id', 'left');

        $this->applyManualFilter($f);
        $this->db->group_by('p.id')
                 ->order_by('MAX(p8.tanggal)', 'DESC')
                 ->limit($limit, $offset);

        return array_column($this->db->get()->result_array(), 'id');
    }

    public function getByIds($ids) {
        if (empty($ids)) return [];

        $CI =& get_instance();
        $karantina = $CI->input->get('karantina', true) ?: 'kh';
        $table = $this->getTable($karantina);
        $kar = strtoupper(substr($karantina, -1));
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
            GROUP_CONCAT(DISTINCT kom.nama SEPARATOR '<br>') AS komoditas,
            GROUP_CONCAT(pkom.volume_lain SEPARATOR '<br>') AS volume,
            GROUP_CONCAT(DISTINCT ms.nama SEPARATOR '<br>') AS satuan,
            IF(MAX(dm.id) IS NOT NULL, 'DITERIMA', 'BELUM DITERIMA') AS status_penerimaan,
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
            ->join('ptk dm', 'p.id = dm.ptk_asal_id', 'left')
            ->where_in('p.id', $ids)
            ->group_by('p.id')
            ->order_by('MAX(p8.tanggal)', 'DESC');

        return $this->db->get()->result_array();
    }

    private function applyManualFilter($f, $db = null) {
        $db = $db ?? $this->db;

        if (!empty($f['upt']) && !in_array(strtolower($f['upt']), ['all','semua'])) {
            $prefix = substr($f['upt'], 0, 2);
            $db->like("target_upt.kode_upt", $prefix, 'after');
        }

        if (!empty($f['start_date']) && !empty($f['end_date'])) {
            $db->where("p8.tanggal >=", $f['start_date'] . ' 00:00:00');
            $db->where("p8.tanggal <=", $f['end_date'] . ' 23:59:59');
        }

        $db->where('p.jenis_permohonan', 'DK');
        $db->where('p.is_verifikasi', '1');
        $db->where('p.is_batal', '0');
        $db->where('p8.deleted_at', '1970-01-01 08:00:00');
    }

    public function getFullData($f)
    {
        $table = $this->getTable($f['karantina']);
        if ($table === '') return [];

        $kar = strtoupper(substr($f['karantina'], -1));
        $tabel_kom = "komoditas_" . ($kar == 'H' ? 'hewan' : ($kar == 'I' ? 'ikan' : 'tumbuhan'));

        $this->db_excel->select("
            p.id, p.no_aju, p.no_dok_permohonan, p.tgl_dok_permohonan,
            p8.nomor AS nkt, p8.tanggal AS tanggal_lepas,
            mu_asal.nama AS upt_asal, mu_asal.nama_satpel AS satpel_asal,
            target_upt.nama AS upt_tujuan, target_upt.nama_satpel AS satpel_tujuan,
            p.nama_pemohon, p.nama_pengirim, p.nama_penerima,
            p.alamat_pemohon, p.alamat_pengirim, p.alamat_penerima,
            mn3.nama AS kota_asal, mn4.nama AS kota_tujuan,
            kom.nama AS komoditas,
            pkom.volume_lain AS volume,
            ms.nama AS satuan,
            CASE WHEN dm.id IS NOT NULL THEN 'DITERIMA' ELSE 'BELUM DITERIMA' END AS status_penerimaan,
            dm.no_dok_permohonan AS no_dok_dm,
            dm.tgl_dok_permohonan AS tgl_dok_dm
        ", false);

        $this->db_excel->from('ptk p')
            ->join("$table p8", "p.id = p8.ptk_id")
            ->join('master_upt mu_asal', 'p.kode_satpel = mu_asal.id', 'left')
            ->join('ptk_komoditas pkom', "p.id = pkom.ptk_id AND pkom.deleted_at = '1970-01-01 08:00:00'", 'left')
            ->join('master_satuan ms', 'pkom.satuan_lain_id = ms.id', 'left')
            ->join("$tabel_kom kom", 'pkom.komoditas_id = kom.id', 'left')
            ->join('master_pelabuhan mp', 'p.pelabuhan_bongkar_id = mp.id', 'left')
            ->join('master_upt target_upt', 'mp.kode_upt = target_upt.id', 'left')
            ->join('master_kota_kab mn3', 'p.kota_kab_asal_id = mn3.id', 'left')
            ->join('master_kota_kab mn4', 'p.kota_kab_tujuan_id = mn4.id', 'left')
            ->join('ptk dm', 'p.id = dm.ptk_asal_id', 'left');

        $this->applyManualFilter($f, $this->db_excel);

        return $this->db_excel->order_by('p8.tanggal', 'DESC')
                              ->order_by('p.id', 'ASC')
                              ->get()->result_array();
    }
}