<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH.'models/BaseModelStrict.php';

class Penolakan_model extends BaseModelStrict
{
    /* =====================================================
     * MAP ALASAN
     * ===================================================== */
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

    /* =====================================================
     * OVERRIDE getList — 1 QUERY CEPAT
     * ===================================================== */
    public function getList($f, $limit, $offset)
    {
        $kar = strtoupper($f['karantina'] ?? 'H');
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
            ->join('master_upt mu', 'p.kode_satpel = mu.id')
            ->join('master_pegawai mp', 'p6.user_ttd_id = mp.id', 'left')
            ->join('ptk_komoditas pk', 'p.id = pk.ptk_id', 'left')
            ->join("$kom k", 'pk.komoditas_id = k.id', 'left')
            ->join('master_satuan ms', 'pk.satuan_lain_id = ms.id', 'left');

        $this->db->where([
            'p.is_verifikasi' => '1',
            'p.is_batal'      => '0',
            'p6.deleted_at'   => '1970-01-01 08:00:00',
            'pk.deleted_at'   => '1970-01-01 08:00:00',
        ]);

        if (!empty($f['upt']) && !in_array(strtolower($f['upt']), ['all','semua'], true)) {
            $this->db->like('p.kode_satpel', substr($f['upt'],0,2), 'after');
        }

        if (!empty($f['karantina'])) {
            $this->db->where('p.jenis_karantina', $f['karantina']);
        }

        if (!empty($f['permohonan'])) {
            $this->db->where('p.jenis_permohonan', $f['permohonan']);
        }

        $this->applyDateFilter('p6.tanggal', $f);

        /* ================= GROUP & PAGE ================= */
        $this->db->group_by('p.id')
            ->order_by('tgl_penolakan', 'DESC')
            ->limit($limit, $offset);

        $rows = $this->db->get()->result_array();

        foreach ($rows as &$r) {
            $r['alasan_string'] = $this->buildAlasan($r);
        }

        return $rows;
    }

    /* =====================================================
     * EXPORT DETAIL — 1 BARIS = 1 KOMODITAS
     * ===================================================== */
    public function getExportByFilter($f)
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
            ->join('master_upt mu', 'p.kode_satpel=mu.id')
            ->join('master_pegawai mp', 'p6.user_ttd_id=mp.id', 'left')
            ->join('ptk_komoditas pk', 'p.id=pk.ptk_id')
            ->join("$kom k", 'pk.komoditas_id=k.id')
            ->join('master_satuan ms', 'pk.satuan_lain_id=ms.id', 'left');

        $this->db->where([
            'p.is_verifikasi'=>'1',
            'p.is_batal'=>'0',
            'p6.deleted_at'=>'1970-01-01 08:00:00',
            'pk.deleted_at'=>'1970-01-01 08:00:00'
        ]);

        if (!empty($f['upt']) && !in_array(strtolower($f['upt']), ['all','semua'], true)) {
            $this->db->like('p.kode_satpel', substr($f['upt'],0,2), 'after');
        }

        if (!empty($f['karantina'])) {
            $this->db->where('p.jenis_karantina', $f['karantina']);
        }

        $this->applyDateFilter('p6.tanggal', $f);

        $rows = $this->db->order_by('p6.tanggal','DESC')
            ->get()
            ->result_array();

        foreach ($rows as &$r) {
            $r['alasan_string'] = $this->buildAlasan($r);
        }

        return $rows;
    }

    /* =====================================================
     * COUNT ALL
     * ===================================================== */
    public function countAll($f)
    {
        $this->db->select('COUNT(DISTINCT p.id) total', false)
            ->from('ptk p')
            ->join('pn_penolakan p6','p.id=p6.ptk_id')
            ->where([
                'p.is_verifikasi'=>'1',
                'p.is_batal'=>'0',
                'p6.deleted_at'=>'1970-01-01 08:00:00'
            ]);

        if (!empty($f['upt']) && !in_array(strtolower($f['upt']), ['all','semua'], true)) {
            $this->db->like('p.kode_satpel', substr($f['upt'],0,2),'after');
        }

        if (!empty($f['karantina'])) {
            $this->db->where('p.jenis_karantina', $f['karantina']);
        }

        $this->applyDateFilter('p6.tanggal', $f);

        return (int)($this->db->get()->row()->total ?? 0);
    }

    /* =====================================================
     * ABSTRACT COMPATIBILITY (TIDAK DIPAKAI)
     * ===================================================== */
    public function getIds($f, $limit, $offset){ return []; }
    public function getByIds($ids){ return []; }

    /* =====================================================
     * BUILD ALASAN STRING
     * ===================================================== */
    private function buildAlasan(array $r): string
    {
        $out = [];
        foreach ($this->alasanMap as $k=>$v) {
            if (!empty($r[$k]) && $r[$k]==='1') {
                $out[] = "- {$v}";
            }
        }
        if (!empty($r['alasan_lain']) && $r['alasan_lain']!=='0') {
            $out[] = "Lain-lain: ".$r['alasan_lain'];
        }
        return $out ? implode(PHP_EOL, $out) : '-';
    }
}
