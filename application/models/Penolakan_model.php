<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'core/BaseModelStrict.php';

class Penolakan_model extends BaseModelStrict
{
    private array $alasanMap = [
        'alasan1' => 'Tidak dapat melengkapi dokumen persyaratan dalam waktu yang ditetapkan',
        'alasan2' => 'Persyaratan dokumen lain tidak dapat dipenuhi',
        'alasan3' => 'Berasal dari negara/daerah/tempat yang dilarang',
        'alasan4' => 'Berasal dari daerah wabah',
        'alasan5' => 'Jenis media pembawa dilarang',
        'alasan6' => 'Sanitasi tidak baik',
        'alasan7' => 'Ditemukan HPHK/HPIK/OPTK',
        'alasan8' => 'Tidak bebas OPTK',
    ];
    public function __construct()
    {
        parent::__construct();
    }


    public function getIds($f, $limit, $offset)
    {
        $this->db->select('p.id, MAX(p6.tanggal) as max_tanggal', false)
            ->from('ptk p')
            ->join('pn_penolakan p6', 'p.id = p6.ptk_id')
            ->join('master_upt mu', 'p.kode_satpel = mu.id', 'left');
        $hasSearch = !empty($f['search']);
        $needPegawaiJoin = $hasSearch || ($f['sort_by'] === 'petugas');

        if ($needPegawaiJoin) {
            $this->db->join('master_pegawai mp', 'p6.user_ttd_id = mp.id', 'left');
        }

        if ($hasSearch) {
            $this->db->join('ptk_komoditas pk', "p.id = pk.ptk_id AND pk.deleted_at = '1970-01-01 08:00:00'", 'left');
            $kar = strtoupper($f['karantina'] ?? 'H');
            $kom = $kar === 'H' ? 'komoditas_hewan'
                 : ($kar === 'I' ? 'komoditas_ikan' : 'komoditas_tumbuhan');
            $this->db->join("$kom k", 'pk.komoditas_id = k.id', 'left');
        }

        $this->applyManualFilter($f, $needPegawaiJoin, $hasSearch);
        $sortMap = [
            'nomor_penolakan' => 'MAX(p6.nomor)',
            'tgl_penolakan'   => 'MAX(p6.tanggal)',
            'no_dok'          => 'MAX(p.no_dok_permohonan)',
            'upt'             => 'MAX(mu.nama)',
            'nama_satpel'     => 'MAX(mu.nama_satpel)',
            'petugas'         => 'MAX(mp.nama)',
        ];

        $this->applySorting(
            $f['sort_by'] ?? null,
            $f['sort_order'] ?? 'DESC',
            $sortMap,
            ['MAX(p6.tanggal)', 'DESC']
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
        $kom = $kar === 'H' ? 'komoditas_hewan'
             : ($kar === 'I' ? 'komoditas_ikan' : 'komoditas_tumbuhan');

        $this->db->select("
            p.id,
            MAX(p.no_dok_permohonan) AS no_dok_permohonan,
            MAX(p6.nomor) AS nomor_penolakan,
            MAX(p6.tanggal) AS tgl_penolakan,
            MAX(mu.nama) AS upt,
            MAX(mu.nama_satpel) AS nama_satpel,
            MAX(mp.nama) AS petugas,
            GROUP_CONCAT(DISTINCT k.nama SEPARATOR '<br>') AS komoditas,
            GROUP_CONCAT(DISTINCT pk.volumeP6 SEPARATOR '<br>') AS volume,
            GROUP_CONCAT(DISTINCT ms.nama SEPARATOR '<br>') AS satuan,
            MAX(p6.alasan1) AS alasan1,
            MAX(p6.alasan2) AS alasan2,
            MAX(p6.alasan3) AS alasan3,
            MAX(p6.alasan4) AS alasan4,
            MAX(p6.alasan5) AS alasan5,
            MAX(p6.alasan6) AS alasan6,
            MAX(p6.alasan7) AS alasan7,
            MAX(p6.alasan8) AS alasan8,
            MAX(p6.alasan_lain) AS alasan_lain
        ", false);

        $this->db->from('ptk p')
            ->join('pn_penolakan p6', 'p.id = p6.ptk_id')
            ->join('master_upt mu', 'p.kode_satpel = mu.id', 'left')
            ->join('master_pegawai mp', 'p6.user_ttd_id = mp.id', 'left')
            ->join('ptk_komoditas pk', "p.id = pk.ptk_id AND pk.deleted_at = '1970-01-01 08:00:00'", 'left')
            ->join("$kom k", 'pk.komoditas_id = k.id', 'left')
            ->join('master_satuan ms', 'pk.satuan_lain_id = ms.id', 'left');

        $this->db->where_in('p.id', $ids);
        $this->db->group_by('p.id');
        $this->db->order_by('tgl_penolakan', 'DESC');

        $rows = $this->db->get()->result_array();

        foreach ($rows as &$r) {
            $r['alasan_string'] = $this->buildAlasan($r);
        }

        return $rows;
    }

    public function countAll($f)
    {
        $this->db->select('COUNT(DISTINCT p.id) total', false)
            ->from('ptk p')
            ->join('pn_penolakan p6', 'p.id = p6.ptk_id')
            ->join('master_upt mu', 'p.kode_satpel = mu.id', 'left');
        $hasSearch = !empty($f['search']);
        $needPegawaiJoin = $hasSearch;

        if ($needPegawaiJoin) {
            $this->db->join('master_pegawai mp', 'p6.user_ttd_id = mp.id', 'left');
        }

        if ($hasSearch) {
            $this->db->join('ptk_komoditas pk', "p.id = pk.ptk_id AND pk.deleted_at = '1970-01-01 08:00:00'", 'left');
            $kar = strtoupper($f['karantina'] ?? 'H');
            $kom = $kar === 'H' ? 'komoditas_hewan'
                 : ($kar === 'I' ? 'komoditas_ikan' : 'komoditas_tumbuhan');
            $this->db->join("$kom k", 'pk.komoditas_id = k.id', 'left');
        }

        $this->applyManualFilter($f, $needPegawaiJoin, $hasSearch);

        return (int)($this->db->get()->row()->total ?? 0);
    }

    public function getFullData($f)
    {
        $kar = strtoupper($f['karantina'] ?? 'H');
        $kom = $kar === 'H' ? 'komoditas_hewan'
             : ($kar === 'I' ? 'komoditas_ikan' : 'komoditas_tumbuhan');

        $this->db->select("
            p.id,
            p.no_dok_permohonan,
            p.tgl_dok_permohonan,
            p6.nomor   AS nomor_penolakan,
            p6.tanggal AS tgl_penolakan,
            mu.nama    AS upt,
            mu.nama_satpel,
            p.nama_pengirim,
            p.nama_penerima,
            mp.nama AS petugas,
            k.nama  AS komoditas,
            pk.kode_hs AS hs,
            pk.volumeP6 AS volume,
            ms.nama AS satuan,

            p6.alasan1,p6.alasan2,p6.alasan3,p6.alasan4,
            p6.alasan5,p6.alasan6,p6.alasan7,p6.alasan8,
            p6.alasan_lain
        ", false);

        $this->db->from('ptk p')
            ->join('pn_penolakan p6', 'p.id=p6.ptk_id')
            ->join('master_upt mu', 'p.kode_satpel=mu.id', 'left')
            ->join('master_pegawai mp', 'p6.user_ttd_id=mp.id', 'left')
            ->join('ptk_komoditas pk', 'p.id=pk.ptk_id')
            ->join("$kom k", 'pk.komoditas_id=k.id')
            ->join('master_satuan ms', 'pk.satuan_lain_id=ms.id', 'left');

        $this->db->where([
            'p.is_verifikasi' => '1',
            'p.is_batal'      => '0',
            'p6.deleted_at'   => '1970-01-01 08:00:00',
            'pk.deleted_at'   => '1970-01-01 08:00:00'
        ]);

        if (!empty($f['upt']) && !in_array(strtolower($f['upt']), ['all', 'semua', 'undefined'], true)) {
            $field = (strlen($f['upt']) <= 4) ? 'p.upt_id' : 'p.kode_satpel';
            $this->db->where($field, $f['upt']);
        }

        if (!empty($f['karantina'])) {
            $this->db->where('p.jenis_karantina', $f['karantina']);
        }
        if (!empty($f['start_date'])) {
            $this->db->where('p6.tanggal >=', $f['start_date'] . ' 00:00:00');
        }
        if (!empty($f['end_date'])) {
            $this->db->where('p6.tanggal <=', $f['end_date'] . ' 23:59:59');
        }

        $rows = $this->db->order_by('p6.tanggal','DESC')
            ->get()
            ->result_array();

        foreach ($rows as &$r) {
            $r['alasan_string'] = $this->buildAlasan($r);
        }

        return $rows;
    }

    private function applyManualFilter($f, $hasPegawaiJoin = true, $hasKomoditasJoin = false)
    {
        $this->db->where([
            'p.is_verifikasi' => '1',
            'p.is_batal'      => '0',
            'p6.deleted_at'   => '1970-01-01 08:00:00'
        ]);

        if (!empty($f['upt']) && !in_array(strtolower($f['upt']), ['all', 'semua', 'undefined'], true)) {
            $field = (strlen($f['upt']) <= 4) ? 'p.upt_id' : 'p.kode_satpel';
            $this->db->where($field, $f['upt']);
        }
        if (!empty($f['karantina'])) {
            $this->db->where('p.jenis_karantina', $f['karantina']);
        }
        if (!empty($f['lingkup']) && !in_array(strtolower($f['lingkup']), ['all', 'semua'])) {
            $this->db->where('p.jenis_permohonan', strtoupper($f['lingkup']));
        }
        if (!empty($f['start_date'])) {
            $this->db->where('p6.tanggal >=', $f['start_date'] . ' 00:00:00');
        }
        if (!empty($f['end_date'])) {
            $this->db->where('p6.tanggal <=', $f['end_date'] . ' 23:59:59');
        }
        if (!empty($f['search'])) {
            $searchColumns = [
                'p.no_aju',
                'p6.nomor',
                'p.no_dok_permohonan',
                'mu.nama',
                'mu.nama_satpel',
                'p.nama_pengirim',
                'p.nama_penerima',
                'p6.alasan_lain',
            ];
            if ($hasPegawaiJoin) {
                $searchColumns[] = 'mp.nama';
            }
            if ($hasKomoditasJoin) {
                $searchColumns[] = 'k.nama';
                $searchColumns[] = 'pk.nama_umum_tercetak';
                $searchColumns[] = 'pk.kode_hs';
            }

            $this->applyGlobalSearch($f['search'], $searchColumns);
        }
    }
    private function buildAlasan(array $r): string
    {
        $out = [];
        foreach ($this->alasanMap as $k => $v) {
            if (!empty($r[$k]) && $r[$k] === '1') {
                $out[] = "- {$v}";
            }
        }
        if (!empty($r['alasan_lain']) && $r['alasan_lain'] !== '0') {
            $out[] = "Lain-lain: " . $r['alasan_lain'];
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