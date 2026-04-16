<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'core/BaseModelStrict.php';

class Perlakuan_model extends BaseModelStrict
{
    protected $db_excel;

    public function __construct()
    {
        parent::__construct();
        $this->db_excel = $this->load->database('excel', TRUE);
    }

    public function getAll(array $filter): array
    {
        $karantina = $filter['karantina'] ?? null;

        $this->db->select("
            p.id,
            ANY_VALUE(p.no_aju) AS no_aju,
            ANY_VALUE(p4.nomor) AS no_p4,
            ANY_VALUE(p4.tanggal) AS tgl_p4,
            ANY_VALUE(p4.nama_tempat) AS lokasi_perlakuan,
            ANY_VALUE(p4.alasan_perlakuan) AS alasan_perlakuan,
            ANY_VALUE(p4.metode_perlakuan) AS metode,
            ANY_VALUE(p4.tgl_perlakuan_mulai) AS mulai,
            ANY_VALUE(p4.tgl_perlakuan_selesai) AS selesai,
            ANY_VALUE(p4.nama_operator) AS nama_operator,

            ANY_VALUE(mu.nama) AS upt,
            ANY_VALUE(mu.nama_satpel) AS nama_satpel,
            ANY_VALUE(mr.nama) AS rekom,
            ANY_VALUE(mp.deskripsi) AS tipe,
            ANY_VALUE(p.nama_pengirim) AS nama_pengirim,
            ANY_VALUE(p.nama_penerima) AS nama_penerima,

            GROUP_CONCAT(DISTINCT kom.nama SEPARATOR '<br>') AS komoditas,
            GROUP_CONCAT(DISTINCT pkom.volumeP8 SEPARATOR '<br>') AS volume,
            GROUP_CONCAT(DISTINCT ms.nama SEPARATOR '<br>') AS satuan
        ", false);

        $this->db->from('ptk p')
            ->join('pn_perlakuan p4', 'p.id = p4.ptk_id')
            ->join('ptk_komoditas pkom', 'p.id = pkom.ptk_id', 'left')
            ->join('master_satuan ms', 'pkom.satuan_lain_id = ms.id', 'left')
            ->join('master_upt mu', 'p.kode_satpel = mu.id', 'left')
            ->join('master_rekomendasi mr', 'p4.rekomendasi_id = mr.id', 'left')
            ->join('master_perlakuan mp', 'p4.tipe_perlakuan_id = mp.id', 'left');

        if ($karantina === 'H') {
            $this->db->join('komoditas_hewan kom', 'pkom.komoditas_id = kom.id', 'left');
        } elseif ($karantina === 'I') {
            $this->db->join('komoditas_ikan kom', 'pkom.komoditas_id = kom.id', 'left');
        } else {
            $this->db->join('komoditas_tumbuhan kom', 'pkom.komoditas_id = kom.id', 'left');
        }

        $this->applyFilter($filter);

        $this->db->group_by('p.id')
                 ->order_by('MAX(p4.tanggal)', 'DESC');

        return $this->db->get()->result_array();
    }

    public function getForExcel(array $filter): array
    {
        $karantina = $filter['karantina'] ?? null;

        $this->db_excel->select("
            p.id,
            p.no_aju,
            p4.nomor AS no_p4,
            p4.tanggal AS tgl_p4,
            mu.nama AS upt,
            mu.nama_satpel,
            p4.nama_tempat AS lokasi_perlakuan,
            p4.metode_perlakuan AS metode,
            p.nama_pengirim,
            p.nama_penerima,

            kom.nama AS komoditas,
            pkom.volumeP8 AS volume,
            ms.nama AS satuan,

            p4.alasan_perlakuan,
            mp.deskripsi AS tipe,
            p4.tgl_perlakuan_mulai AS mulai,
            p4.tgl_perlakuan_selesai AS selesai,
            mr.nama AS rekom,
            p4.nama_operator
        ", false);

        $this->db_excel->from('ptk p')
            ->join('pn_perlakuan p4', 'p.id = p4.ptk_id')
            ->join('ptk_komoditas pkom', 'p.id = pkom.ptk_id')
            ->join('master_satuan ms', 'pkom.satuan_lain_id = ms.id', 'left')
            ->join('master_upt mu', 'p.kode_satpel = mu.id', 'left')
            ->join('master_rekomendasi mr', 'p4.rekomendasi_id = mr.id', 'left')
            ->join('master_perlakuan mp', 'p4.tipe_perlakuan_id = mp.id', 'left');

        if ($karantina === 'H') {
            $this->db_excel->join('komoditas_hewan kom', 'pkom.komoditas_id = kom.id');
        } elseif ($karantina === 'I') {
            $this->db_excel->join('komoditas_ikan kom', 'pkom.komoditas_id = kom.id');
        } else {
            $this->db_excel->join('komoditas_tumbuhan kom', 'pkom.komoditas_id = kom.id');
        }

        $this->applyFilter($filter, $this->db_excel);

        $this->db_excel->order_by('p.id, pkom.id');

        return $this->db_excel->get()->result_array();
    }

    private function applyFilter(array $filter, $db = null): void
    {
        $db = $db ?? $this->db;

        $db->where([
            'p.is_verifikasi'         => '1',
            'p.is_batal'              => '0',
            'p4.deleted_at'           => '1970-01-01 08:00:00',
            'p4.dokumen_karantina_id' => '23',
        ]);

        if (isset($filter['upt']) && $filter['upt'] != '' && !in_array(strtolower($filter['upt']), ['semua', 'all'])) {
            $db->like('p.kode_satpel', substr($filter['upt'], 0, 2), 'after');
        }
        if (!empty($filter['karantina'])) {
            $db->where('p.jenis_karantina', $filter['karantina']);
        }
        if (!empty($filter['lingkup']) && !in_array(strtolower($filter['lingkup']), ['all', 'semua'])) {
            $db->where('p.jenis_permohonan', strtoupper($filter['lingkup']));
        }
        if (!empty($filter['start_date'])) {
            $db->where('p4.tanggal >=', $filter['start_date'] . ' 00:00:00');
        }
        if (!empty($filter['end_date'])) {
            $db->where('p4.tanggal <=', $filter['end_date'] . ' 23:59:59');
        }
    }
}
