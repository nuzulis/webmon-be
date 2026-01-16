<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'models/BaseModelStrict.php';

class Penahanan_model extends BaseModelStrict
{
    // Map alasan sesuai struktur P5 (Penahanan)
    private array $alasanMap = [
        'alasan1' => "Media pembawa tidak dilaporkan kepada pejabat karantina pada saat pemasukan/pengeluaran",
        'alasan2' => "Tidak disertai Keterangan Mutasi/keterangan tidak terkontaminasi/catatan suhu untuk media pembawa yang dipersyaratkan",
        'alasan3' => "Tidak disertai dokumen karantina dan/atau dokumen lain yang dipersyaratkan saat tiba di tempat pemasukan"
    ];

    public function getIds($f, $limit, $offset)
    {
        $this->db->select('p.id, MAX(p5.tanggal) AS last_tgl', false)
            ->from('ptk p')
            ->join('pn_penahanan p5', 'p.id = p5.ptk_id')
            ->where([
                'p.is_verifikasi'           => '1',
                'p.is_batal'                => '0',
                'p5.deleted_at'             => '1970-01-01 08:00:00',
                'p5.dokumen_karantina_id'   => '26',
            ]);

        $this->applyCommonFilter($f, 'p');
        $this->applyDateFilter('p5.tanggal', $f);

        $this->db->group_by('p.id')
                 ->order_by('last_tgl', 'DESC')
                 ->limit($limit, $offset);

        $res = $this->db->get()->result_array();
        return array_column($res, 'id');
    }

    public function getByIds($ids, $is_export = false)
    {
        if (empty($ids)) return [];

        $karantina = strtoupper($this->input->get('karantina', true));
        $tabel_kom = "komoditas_" . ($karantina == 'H' ? 'hewan' : ($karantina == 'I' ? 'ikan' : 'tumbuhan'));
        $quotedIds = "'" . implode("','", $ids) . "'";

        $select = "
            p.id, p.tssm_id, p.no_aju, p.no_dok_permohonan, p.tgl_dok_permohonan,
            p5.nomor AS no_p5, p5.tanggal AS tgl_p5,
            p5.alasan1, p5.alasan2, p5.alasan3,
            mu.nama AS upt, mu.nama_satpel AS nama_satpel,
            p.nama_pengirim, p.nama_penerima,
            mn1.nama AS asal, mn2.nama AS tujuan,
            mn3.nama AS kota_asal, mn4.nama AS kota_tujuan,
            mp.nama AS petugas, rek.nama AS rekomendasi
        ";

        if ($is_export) {
            $select .= ", kom.nama AS komoditas, pk.nama_umum_tercetak AS tercetak, 
                         pk.kode_hs AS hs, pk.volume_lain AS volume, 
                         pk.volumeP5 AS p5_vol, ms.nama AS satuan";
        } else {
            $select .= ", k.komoditas, k.hs, k.volume, k.satuan";
        }

        $this->db->select($select, false)
            ->from('ptk p')
            ->join('pn_penahanan p5', 'p.id = p5.ptk_id')
            ->join('master_upt mu', 'p.kode_satpel = mu.id')
            ->join('master_pegawai mp', 'p5.user_ttd_id = mp.id', 'left')
            ->join('pn_penahanan bam', "p5.id = bam.pn_penahanan_id AND bam.dokumen_karantina_id = '28'", 'left')
            ->join('master_rekomendasi rek', 'bam.rekomendasi_id = rek.id', 'left')
            ->join('master_negara mn1', 'p.negara_asal_id = mn1.id', 'left')
            ->join('master_negara mn2', 'p.negara_tujuan_id = mn2.id', 'left')
            ->join('master_kota_kab mn3', 'p.kota_kab_asal_id = mn3.id', 'left')
            ->join('master_kota_kab mn4', 'p.kota_kab_tujuan_id = mn4.id', 'left');

        if ($is_export) {
            $this->db->join('ptk_komoditas pk', 'p.id = pk.ptk_id')
                     ->join("$tabel_kom kom", 'pk.komoditas_id = kom.id')
                     ->join('master_satuan ms', 'pk.satuan_lain_id = ms.id', 'left')
                     ->where('pk.deleted_at', '1970-01-01 08:00:00');
        } else {
            $this->db->join("(
                SELECT 
                    pk.ptk_id,
                    GROUP_CONCAT(kom.nama SEPARATOR '<br>') AS komoditas,
                    GROUP_CONCAT(pk.kode_hs SEPARATOR '<br>') AS hs,
                    GROUP_CONCAT(pk.volumeP5 SEPARATOR '<br>') AS volume,
                    GROUP_CONCAT(ms.nama SEPARATOR '<br>') AS satuan
                FROM ptk_komoditas pk
                JOIN $tabel_kom kom ON pk.komoditas_id = kom.id
                LEFT JOIN master_satuan ms ON pk.satuan_lain_id = ms.id
                WHERE pk.deleted_at = '1970-01-01 08:00:00'
                GROUP BY pk.ptk_id
            ) k", 'p.id = k.ptk_id', 'left');
        }

        $this->db->where_in('p.id', $ids)
                 ->where('p5.dokumen_karantina_id', '26')
                 ->order_by('tgl_p5', 'DESC');

        $rows = $this->db->get()->result_array();

        foreach ($rows as &$row) {
            $row['alasan_string'] = $this->buildAlasanString($row);
        }

        return $rows;
    }

    private function buildAlasanString(array $row): string
    {
        $out = [];
        foreach ($this->alasanMap as $field => $label) {
            if (!empty($row[$field]) && $row[$field] == '1') $out[] = "- " . $label;
        }
        return $out ? implode(PHP_EOL, $out) : '-';
    }

    public function countAll($f)
    {
        $this->db->select('COUNT(DISTINCT p.id) AS total', false)
            ->from('ptk p')
            ->join('pn_penahanan p5', 'p.id = p5.ptk_id')
            ->where([
                'p.is_verifikasi'           => '1',
                'p.is_batal'                => '0',
                'p5.deleted_at'             => '1970-01-01 08:00:00',
                'p5.dokumen_karantina_id'   => '26',
            ]);

        $this->applyCommonFilter($f, 'p');
        $this->applyDateFilter('p5.tanggal', $f);

        $row = $this->db->get()->row();
        return $row ? (int)$row->total : 0;
    }
}