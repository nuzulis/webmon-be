<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'core/BaseModelStrict.php';

class Pemusnahan_model extends BaseModelStrict
{
    private array $alasanMap = [
        'alasan1' => 'Media Pembawa adalah jenis yang dilarang pemasukannya',
        'alasan2' => 'Media pembawa rusak/busuk',
        'alasan3' => 'Berasal dari negara/daerah wabah HPHK/HPIK/OPTK',
        'alasan4' => 'Tidak dapat disembuhkan/dibebaskan setelah perlakuan',
        'alasan5' => 'Tidak dikeluarkan dari wilayah RI dalam waktu yang ditentukan',
        'alasan6' => 'Tidak memenuhi persyaratan keamanan dan mutu pangan/pakan',
    ];

    public function __construct()
    {
        parent::__construct();
    }

    public function getIds($f, $limit, $offset)
    {
        $this->db->select('p.id, MAX(mus.tanggal) as max_tanggal', false)
            ->from('pn_pemusnahan mus')
            ->join('ptk p', 'mus.ptk_id = p.id')
            ->join('master_upt mu', 'p.kode_satpel = mu.id', 'left');

        $hasSearch = !empty($f['search']);
        $needBamJoin = $hasSearch || ($f['sort_by'] === 'petugas');

        if ($needBamJoin) {
            $this->db->join(
                'pn_pemusnahan bam',
                "mus.id = bam.pn_pemusnahan_id AND bam.dokumen_karantina_id = '36'",
                'left'
            );
        }

        if ($hasSearch) {
            $this->db->join('ptk_komoditas pk', "p.id = pk.ptk_id AND pk.deleted_at = '1970-01-01 08:00:00'", 'left');
            $kar = strtoupper($f['karantina'] ?? 'H');
            $kom = $kar === 'H' ? 'komoditas_hewan'
                 : ($kar === 'I' ? 'komoditas_ikan' : 'komoditas_tumbuhan');
            $this->db->join("$kom k", 'pk.komoditas_id = k.id', 'left');
        }

        $this->applyManualFilter($f, $needBamJoin, $hasSearch);

        $sortMap = [
            'nomor'       => 'MAX(mus.nomor)',
            'tgl_p7'      => 'MAX(mus.tanggal)',
            'nama_upt'    => 'MAX(mu.nama)',
            'nama_satpel' => 'MAX(mu.nama_satpel)',
            'petugas'     => 'MAX(bam.petugas_pelaksana)',
        ];

        $this->applySorting(
            $f['sort_by'] ?? null,
            $f['sort_order'] ?? 'DESC',
            $sortMap,
            ['MAX(mus.tanggal)', 'DESC']
        );

        $this->db->group_by('p.id');
        $this->db->limit($limit, $offset);

        return array_column($this->db->get()->result_array(), 'id');
    }

    public function countAll($f)
    {
        $this->db->select('COUNT(DISTINCT p.id) total', false)
            ->from('pn_pemusnahan mus')
            ->join('ptk p', 'mus.ptk_id = p.id')
            ->join('master_upt mu', 'p.kode_satpel = mu.id', 'left');

        $hasSearch = !empty($f['search']);
        $needBamJoin = $hasSearch;

        if ($needBamJoin) {
            $this->db->join(
                'pn_pemusnahan bam',
                "mus.id = bam.pn_pemusnahan_id AND bam.dokumen_karantina_id = '36'",
                'left'
            );
        }

        if ($hasSearch) {
            $this->db->join('ptk_komoditas pk', "p.id = pk.ptk_id AND pk.deleted_at = '1970-01-01 08:00:00'", 'left');
            $kar = strtoupper($f['karantina'] ?? 'H');
            $kom = $kar === 'H' ? 'komoditas_hewan'
                 : ($kar === 'I' ? 'komoditas_ikan' : 'komoditas_tumbuhan');
            $this->db->join("$kom k", 'pk.komoditas_id = k.id', 'left');
        }

        $this->applyManualFilter($f, $needBamJoin, $hasSearch);

        return (int)($this->db->get()->row()->total ?? 0);
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
            MAX(mus.nomor)   AS nomor,
            MAX(mus.tanggal) AS tgl_p7,
            MAX(mu.nama) AS nama_upt,
            MAX(mu.nama_satpel) AS nama_satpel,
            MAX(bam.petugas_pelaksana) AS petugas,

            GROUP_CONCAT(DISTINCT k.nama SEPARATOR '<br>') AS komoditas,
            GROUP_CONCAT(DISTINCT pk.volume_lain SEPARATOR '<br>') AS volume,
            GROUP_CONCAT(DISTINCT ms.nama SEPARATOR '<br>') AS satuan,

            MAX(mus.alasan1) AS alasan1,
            MAX(mus.alasan2) AS alasan2,
            MAX(mus.alasan3) AS alasan3,
            MAX(mus.alasan4) AS alasan4,
            MAX(mus.alasan5) AS alasan5,
            MAX(mus.alasan6) AS alasan6,
            MAX(mus.alasan_lain) AS alasan_lain
        ", false);

        $this->db->from('pn_pemusnahan mus')
            ->join('ptk p', 'mus.ptk_id = p.id')
            ->join('master_upt mu', 'p.kode_satpel = mu.id', 'left')
            ->join(
                'ptk_komoditas pk',
                "p.id = pk.ptk_id AND pk.deleted_at = '1970-01-01 08:00:00'",
                'left'
            )
            ->join("$kom k", 'pk.komoditas_id = k.id', 'left')
            ->join('master_satuan ms', 'pk.satuan_lain_id = ms.id', 'left')
            ->join(
                'pn_pemusnahan bam',
                "mus.id = bam.pn_pemusnahan_id AND bam.dokumen_karantina_id = '36'",
                'left'
            );

        $this->db->where_in('p.id', $ids);
        $this->db->group_by('p.id');
        $this->db->order_by('tgl_p7', 'DESC');

        $rows = $this->db->get()->result_array();

        foreach ($rows as &$r) {
            $r['alasan_string'] = $this->buildAlasan($r);
        }

        return $rows;
    }

    public function getFullData($f)
    {
        $kar = strtoupper($f['karantina'] ?? 'H');
        $kom = $kar === 'H' ? 'komoditas_hewan'
             : ($kar === 'I' ? 'komoditas_ikan' : 'komoditas_tumbuhan');

        $this->db->select("
            p.id,
            p.tssm_id,
            mus.nomor,
            mus.tanggal AS tgl_p7,

            mu.nama AS nama_upt,
            mu.nama_satpel,

            p.nama_pengirim,
            p.nama_penerima,

            mn1.nama AS negara_asal,
            kab1.nama AS kota_kab_asal,
            mn2.nama AS negara_tujuan,
            kab2.nama AS kota_kab_tujuan,

            bam.tempat_musnah AS tempat,
            bam.metode_musnah AS metode,
            bam.petugas_pelaksana AS petugas,

            kom.nama AS komoditas,
            pkom.nama_umum_tercetak AS tercetak,
            pkom.kode_hs AS hs,
            pkom.volume_lain AS volume,
            pkom.volumeP7 AS p7,
            ms.nama AS satuan,

            mus.alasan1, mus.alasan2, mus.alasan3,
            mus.alasan4, mus.alasan5, mus.alasan6,
            mus.alasan_lain
        ", false);

        $this->db->from('pn_pemusnahan mus')
            ->join('ptk p', 'mus.ptk_id = p.id')
            ->join('master_upt mu', 'p.kode_satpel = mu.id', 'left')
            ->join('master_negara mn1', 'p.negara_asal_id = mn1.id', 'left')
            ->join('master_negara mn2', 'p.negara_tujuan_id = mn2.id', 'left')
            ->join('master_kota_kab kab1', 'p.kota_kab_asal_id = kab1.id', 'left')
            ->join('master_kota_kab kab2', 'p.kota_kab_tujuan_id = kab2.id', 'left')
            ->join(
                'pn_pemusnahan bam',
                "mus.id = bam.pn_pemusnahan_id AND bam.dokumen_karantina_id = '36'",
                'left'
            )
            ->join('ptk_komoditas pkom', 'p.id = pkom.ptk_id')
            ->join("$kom kom", 'pkom.komoditas_id = kom.id')
            ->join('master_satuan ms', 'pkom.satuan_lain_id = ms.id', 'left');

        $this->db->where([
            'mus.dokumen_karantina_id' => '35',
            'mus.deleted_at' => '1970-01-01 08:00:00',
            'p.is_batal' => '0',
            'pkom.deleted_at' => '1970-01-01 08:00:00'
        ]);

        if (!empty($f['upt']) && !in_array(strtolower($f['upt']), ['all', 'semua', 'undefined'], true)) {
            $field = (strlen($f['upt']) <= 4) ? 'p.upt_id' : 'p.kode_satpel';
            $this->db->where($field, $f['upt']);
        }

        if (!empty($f['karantina'])) {
            $this->db->where('p.jenis_karantina', $f['karantina']);
        }
        if (!empty($f['start_date'])) {
            $this->db->where('mus.tanggal >=', $f['start_date'] . ' 00:00:00');
        }
        if (!empty($f['end_date'])) {
            $this->db->where('mus.tanggal <=', $f['end_date'] . ' 23:59:59');
        }

        $rows = $this->db
            ->order_by('mus.tanggal', 'DESC')
            ->get()
            ->result_array();

        foreach ($rows as &$r) {
            $r['alasan_string'] = $this->buildAlasan($r);
        }

        return $rows;
    }

    private function applyManualFilter($f, $hasBamJoin = true, $hasKomoditasJoin = false)
    {
        $this->db->where([
            'mus.deleted_at' => '1970-01-01 08:00:00',
            'mus.dokumen_karantina_id' => '35',
            'p.is_batal' => '0'
        ]);
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
        if (!empty($f['start_date'])) {
            $this->db->where('mus.tanggal >=', $f['start_date'] . ' 00:00:00');
        }
        if (!empty($f['end_date'])) {
            $this->db->where('mus.tanggal <=', $f['end_date'] . ' 23:59:59');
        }
        if (!empty($f['search'])) {
            $searchColumns = [
                'mus.nomor',
                'mu.nama',
                'mu.nama_satpel',
            ];

            if ($hasBamJoin) {
                $searchColumns[] = 'bam.petugas_pelaksana';
                $searchColumns[] = 'bam.tempat_musnah';
                $searchColumns[] = 'bam.metode_musnah';
            }
            if ($hasKomoditasJoin) {
                $searchColumns[] = 'k.nama';
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

    public function getExportByFilter(array $f): array
    {
        return $this->getFullData($f);
    }
}