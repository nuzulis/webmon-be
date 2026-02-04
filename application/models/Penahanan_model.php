<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'models/BaseModelStrict.php';

class Penahanan_model extends BaseModelStrict
{
    private $alasanMap = [
        'alasan1' => "Media pembawa tidak dilaporkan kepada pejabat karantina pada saat pemasukan/pengeluaran",
        'alasan2' => "Tidak disertai Keterangan Mutasi/keterangan tidak terkontaminasi/catatan suhu untuk media pembawa yang dipersyaratkan",
        'alasan3' => "Tidak disertai dokumen karantina dan/atau dokumen lain yang dipersyaratkan saat tiba di tempat pemasukan"
    ];

    public function getList($f, $limit, $offset)
    {
        $kar = strtoupper($f['karantina'] ?? 'H');
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

        $this->db->where([
            'p5.dokumen_karantina_id' => 26,
            'p5.deleted_at'           => '1970-01-01 08:00:00',
            'p.is_verifikasi'         => '1',
            'p.is_batal'              => '0'
        ]);

        $this->applyInternalFilter($f);
        $this->applyDateFilter('p5.tanggal', $f);
        $this->db->group_by('p.id')
            ->order_by('tgl_p5', 'DESC')
            ->limit($limit, $offset);

        $rows = $this->db->get()->result_array();

        foreach ($rows as &$row) {
            $row['alasan_string'] = $this->buildAlasanString($row);
        }

        return $rows;
    }

    public function getExportByFilter($f)
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
        $this->applyDateFilter('p5.tanggal', $f);

        return $this->db->order_by('p5.tanggal', 'DESC')->get()->result_array();
    }

    public function countAll($f)
    {
        $this->db->select('COUNT(DISTINCT p.id) AS total')
            ->from('pn_penahanan p5')
            ->join('ptk p', 'p5.ptk_id = p.id')
            ->where([
                'p5.dokumen_karantina_id' => 26,
                'p5.deleted_at'           => '1970-01-01 08:00:00',
                'p.is_verifikasi'         => '1',
                'p.is_batal'              => '0'
            ]);

        $this->applyInternalFilter($f);
        $this->applyDateFilter('p5.tanggal', $f);

        return (int) ($this->db->get()->row()->total ?? 0);
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

    private function applyInternalFilter($f)
    {
        if (!empty($f['upt']) && !in_array(strtolower($f['upt']), ['semua', 'all'], true)) {
            $this->db->like('p.kode_satpel', substr($f['upt'], 0, 2), 'after');
        }
        if (!empty($f['karantina'])) $this->db->where('p.jenis_karantina', $f['karantina']);
        if (!empty($f['permohonan'])) $this->db->where('p.jenis_permohonan', $f['permohonan']);
    }

    public function getIds($f, $limit, $offset){ return []; }
    public function getByIds($ids){ return []; }
}