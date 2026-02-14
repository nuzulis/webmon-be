<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'core/BaseModelStrict.php';

class Penahanan_model extends BaseModelStrict
{
    private $alasanMap = [
        'alasan1' => "Media pembawa tidak dilaporkan kepada pejabat karantina pada saat pemasukan/pengeluaran",
        'alasan2' => "Tidak disertai Keterangan Mutasi/keterangan tidak terkontaminasi/catatan suhu untuk media pembawa yang dipersyaratkan",
        'alasan3' => "Tidak disertai dokumen karantina dan/atau dokumen lain yang dipersyaratkan saat tiba di tempat pemasukan"
    ];

    public function __construct()
    {
        parent::__construct();
    }

    public function getIds($f, $limit, $offset)
    {
        $this->db->select('p.id, MAX(p5.tanggal) as max_tanggal', false)
            ->from('pn_penahanan p5')
            ->join('ptk p', 'p5.ptk_id = p.id')
            ->join('master_upt mu', 'p.kode_satpel = mu.id', 'left');
        $hasSearch = !empty($f['search']);
        $needPegawaiJoin = $hasSearch || ($f['sort_by'] === 'petugas');

        if ($needPegawaiJoin) {
            $this->db->join('master_pegawai mp', 'p5.user_ttd_id = mp.id', 'left');
        }

        if ($hasSearch) {
            $this->db->join('ptk_komoditas pk', "p.id = pk.ptk_id AND pk.deleted_at = '1970-01-01 08:00:00'", 'left');
            $kar = strtoupper($f['karantina'] ?? 'H');
            $kom = 'komoditas_' . ($kar === 'H' ? 'hewan' : ($kar === 'I' ? 'ikan' : 'tumbuhan'));
            $this->db->join("$kom k", 'pk.komoditas_id = k.id', 'left');
        }

        $this->applyManualFilter($f, $needPegawaiJoin, $hasSearch);
        $sortMap = [
            'no_p5'      => 'MAX(p5.nomor)',
            'tgl_p5'     => 'MAX(p5.tanggal)',
            'upt'        => 'MAX(mu.nama)',
            'nama_satpel' => 'MAX(mu.nama_satpel)',
            'petugas'    => 'MAX(mp.nama)',
            'nama_pengirim' => 'MAX(p.nama_pengirim)',
            'nama_penerima' => 'MAX(p.nama_penerima)',
        ];

        $this->applySorting(
            $f['sort_by'] ?? null,
            $f['sort_order'] ?? 'DESC',
            $sortMap,
            ['MAX(p5.tanggal)', 'DESC']
        );

        $this->db->group_by('p.id');
        $this->db->limit($limit, $offset);

        return array_column($this->db->get()->result_array(), 'id');
    }

    public function getByIds($ids)
    {
        if (empty($ids)) return [];
        $CI =& get_instance();
        $kar = strtoupper($CI->input->get('karantina', TRUE) ?? 'H');
        $kom = 'komoditas_' . ($kar === 'H' ? 'hewan' : ($kar === 'I' ? 'ikan' : 'tumbuhan'));

        $this->db->select("
            p.id,
            MAX(p.no_aju) AS no_aju,
            MAX(p5.nomor) AS no_p5,
            MAX(p5.tanggal) AS tgl_p5,
            MAX(mu.nama) AS upt,
            MAX(mu.nama_satpel) AS nama_satpel,
            MAX(mp.nama) AS petugas,
            MAX(p.nama_pengirim) AS nama_pengirim,
            MAX(p.nama_penerima) AS nama_penerima,

            GROUP_CONCAT(DISTINCT k.nama SEPARATOR '<br>') AS komoditas,
            GROUP_CONCAT(DISTINCT pk.volumeP5 SEPARATOR '<br>') AS volume,
            GROUP_CONCAT(DISTINCT ms.nama SEPARATOR '<br>') AS satuan,

            MAX(p5.alasan1) AS alasan1,
            MAX(p5.alasan2) AS alasan2,
            MAX(p5.alasan3) AS alasan3
        ", false);

        $this->db->from('pn_penahanan p5')
            ->join('ptk p', 'p5.ptk_id = p.id')
            ->join('master_upt mu', 'p.kode_satpel = mu.id', 'left')
            ->join('master_pegawai mp', 'p5.user_ttd_id = mp.id', 'left')
            ->join('ptk_komoditas pk', "p.id = pk.ptk_id AND pk.deleted_at = '1970-01-01 08:00:00'", 'left')
            ->join("$kom k", 'pk.komoditas_id = k.id', 'left')
            ->join('master_satuan ms', 'pk.satuan_lain_id = ms.id', 'left');

        $this->db->where_in('p.id', $ids);
        $this->db->group_by('p.id');
        $this->db->order_by('tgl_p5', 'DESC');

        $rows = $this->db->get()->result_array();

        foreach ($rows as &$row) {
            $row['alasan_string'] = $this->buildAlasanString($row);
        }

        return $rows;
    }

    public function countAll($f)
    {
        $this->db->select('COUNT(DISTINCT p.id) AS total', false)
            ->from('pn_penahanan p5')
            ->join('ptk p', 'p5.ptk_id = p.id')
            ->join('master_upt mu', 'p.kode_satpel = mu.id', 'left');
        $hasSearch = !empty($f['search']);
        $needPegawaiJoin = $hasSearch;

        if ($needPegawaiJoin) {
            $this->db->join('master_pegawai mp', 'p5.user_ttd_id = mp.id', 'left');
        }

        if ($hasSearch) {
            $this->db->join('ptk_komoditas pk', "p.id = pk.ptk_id AND pk.deleted_at = '1970-01-01 08:00:00'", 'left');
            $kar = strtoupper($f['karantina'] ?? 'H');
            $kom = 'komoditas_' . ($kar === 'H' ? 'hewan' : ($kar === 'I' ? 'ikan' : 'tumbuhan'));
            $this->db->join("$kom k", 'pk.komoditas_id = k.id', 'left');
        }

        $this->applyManualFilter($f, $needPegawaiJoin, $hasSearch);

        return (int) ($this->db->get()->row()->total ?? 0);
    }

    public function getFullData($f)
    {
        $kar = strtoupper($f['karantina'] ?? 'H');
        $kom = 'komoditas_' . ($kar === 'H' ? 'hewan' : ($kar === 'I' ? 'ikan' : 'tumbuhan'));

        $this->db->select("
            p.id, p.no_aju,
            p5.nomor AS no_p5, p5.tanggal AS tgl_p5,
            mu.nama AS upt,
            p.nama_pengirim, p.nama_penerima,
            mn1.nama AS asal, mn2.nama AS tujuan,
            mp.nama AS petugas,
            k.nama AS komoditas,
            pk.volumeP5 AS volume,
            ms.nama AS satuan,
            p5.alasan1, p5.alasan2, p5.alasan3
        ", false);

        $this->db->from('pn_penahanan p5')
            ->join('ptk p', 'p5.ptk_id = p.id')
            ->join('master_upt mu', 'p.kode_satpel = mu.id', 'left')
            ->join('master_pegawai mp', 'p5.user_ttd_id = mp.id', 'left')
            ->join('master_negara mn1', 'p.negara_asal_id = mn1.id', 'left')
            ->join('master_negara mn2', 'p.negara_tujuan_id = mn2.id', 'left')
            ->join('ptk_komoditas pk', 'p.id = pk.ptk_id')
            ->join("$kom k", 'pk.komoditas_id = k.id')
            ->join('master_satuan ms', 'pk.satuan_lain_id = ms.id', 'left');

        $this->db->where([
            'p5.dokumen_karantina_id' => 26,
            'p5.deleted_at'           => '1970-01-01 08:00:00',
            'p.is_batal'              => '0',
            'pk.deleted_at'           => '1970-01-01 08:00:00'
        ]);

        $this->applyInternalFilter($f);

        if (!empty($f['start_date'])) {
            $this->db->where('p5.tanggal >=', $f['start_date'] . ' 00:00:00');
        }
        if (!empty($f['end_date'])) {
            $this->db->where('p5.tanggal <=', $f['end_date'] . ' 23:59:59');
        }

        $rows = $this->db->order_by('p5.tanggal', 'DESC')->get()->result_array();

        foreach ($rows as &$row) {
            $row['alasan_string'] = $this->buildAlasanString($row);
        }

        return $rows;
    }

    private function applyManualFilter($f, $hasPegawaiJoin = true, $hasKomoditasJoin = false)
    {
        $this->db->where([
            'p5.dokumen_karantina_id' => 26,
            'p5.deleted_at'           => '1970-01-01 08:00:00',
            'p.is_verifikasi'         => '1',
            'p.is_batal'              => '0'
        ]);
        $this->applyInternalFilter($f);
        if (!empty($f['start_date'])) {
            $this->db->where('p5.tanggal >=', $f['start_date'] . ' 00:00:00');
        }
        if (!empty($f['end_date'])) {
            $this->db->where('p5.tanggal <=', $f['end_date'] . ' 23:59:59');
        }
        if (!empty($f['search'])) {
            $searchColumns = [
                'p5.nomor',
                'p.no_aju',
                'mu.nama',
                'mu.nama_satpel',
                'p.nama_pengirim',
                'p.nama_penerima',
            ];
            if ($hasPegawaiJoin) {
                $searchColumns[] = 'mp.nama';
            }
            if ($hasKomoditasJoin) {
                $searchColumns[] = 'k.nama';
            }

            $this->applyGlobalSearch($f['search'], $searchColumns);
        }
    }

    private function applyInternalFilter($f)
    {
        if (!empty($f['upt']) && !in_array(strtolower($f['upt']), ['all', 'semua', 'undefined'], true)) {
            $field = (strlen($f['upt']) <= 4) ? 'p.upt_id' : 'p.kode_satpel';
            $this->db->where($field, $f['upt']);
        }
        if (!empty($f['karantina'])) {
            $this->db->where('p.jenis_karantina', $f['karantina']);
        }
        $lingkup = $f['lingkup'] ?? ($f['permohonan'] ?? '');

    if (!empty($lingkup) && !in_array(strtolower($lingkup), ['all', 'semua', ''])) {
        $this->db->where('p.jenis_permohonan', strtoupper($lingkup));
    }
    }

    private function buildAlasanString($row)
    {
        $out = [];
        foreach ($this->alasanMap as $field => $label) {
            if (!empty($row[$field]) && $row[$field] === '1') {
                $out[] = "- {$label}";
            }
        }
        return $out ? implode(PHP_EOL, $out) : '-';
    }

    public function getList($f, $limit, $offset)
    {
        $ids = $this->getIds($f, $limit, $offset);
        return $this->getByIds($ids);
    }

    public function getExportByFilter($f)
    {
        return $this->getFullData($f);
    }
}