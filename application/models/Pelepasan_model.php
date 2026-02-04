<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'models/BaseModelStrict.php';

class Pelepasan_model extends BaseModelStrict
{
    private function getTable($karantina)
    {
        return match (strtoupper($karantina)) {
            'H' => 'pn_pelepasan_kh',
            'I' => 'pn_pelepasan_ki',
            'T' => 'pn_pelepasan_kt',
            default => '',
        };
    }

    public function getList($f, $limit, $offset)
    {
        $table = $this->getTable($f['karantina']);
        if ($table === '') return [];

        $kar = strtoupper($f['karantina']);
        $tabel_kom = "komoditas_" . ($kar == 'H' ? 'hewan' : ($kar == 'I' ? 'ikan' : 'tumbuhan'));

        $this->db->select("
            p.id, 
            p8.nomor AS nkt, 
            p8.nomor_seri AS seri, 
            p8.tanggal AS tanggal_lepas,
            mu.nama AS upt, 
            mu.nama_satpel AS satpel,
            p.nama_pemohon,
            p.nama_penerima,
            mn2.nama AS tujuan,
            mn4.nama AS kota_tujuan,
            mn1.nama AS asal, 
            mn3.nama AS kota_asal,
            
            -- Komoditas Grouped
            GROUP_CONCAT(pkom.nama_umum_tercetak SEPARATOR '<br>') AS komoditas,
            GROUP_CONCAT(pkom.kode_hs SEPARATOR '<br>') AS hs,
            GROUP_CONCAT(pkom.volumeP8 SEPARATOR '<br>') AS volume,
            GROUP_CONCAT(pkom.nettoP8 SEPARATOR '<br>') AS volume_netto,
            GROUP_CONCAT(DISTINCT ms.nama SEPARATOR '<br>') AS satuan,

        ", false);

        $this->db->from('ptk p')
            ->join("$table p8", 'p.id = p8.ptk_id')
            ->join('ptk_komoditas pkom', "p.id = pkom.ptk_id AND pkom.deleted_at = '1970-01-01 08:00:00'", 'left')
            ->join("$tabel_kom kom", 'pkom.komoditas_id = kom.id', 'left')
            ->join('master_upt mu', 'p.kode_satpel = mu.id', 'left')
            ->join('master_negara mn1', 'p.negara_asal_id = mn1.id', 'left')
            ->join('master_negara mn2', 'p.negara_tujuan_id = mn2.id', 'left')
            ->join('master_satuan ms', 'pkom.satuan_lain_id = ms.id', 'left')
            ->join('master_kota_kab mn3', 'p.kota_kab_asal_id = mn3.id', 'left')
            ->join('master_kota_kab mn4', 'p.kota_kab_tujuan_id = mn4.id', 'left');

        $this->applyManualFilters($f, $table);

        $this->db->group_by('p.id, p8.nomor, p8.nomor_seri, p8.tanggal')
                 ->order_by('p8.tanggal', 'DESC')
                 ->limit($limit, $offset);

        return $this->db->get()->result_array();
    }
    public function getExportByFilter($f)
    {
        $table = $this->getTable($f['karantina']);
        if ($table === '') return [];

        $kar = strtoupper($f['karantina']);
        $tabel_kom = "komoditas_" . ($kar == 'H' ? 'hewan' : ($kar == 'I' ? 'ikan' : 'tumbuhan'));
        $tabel_klas = "klasifikasi_" . ($kar == 'H' ? 'hewan' : ($kar == 'I' ? 'ikan' : 'tumbuhan'));

        $this->db->select("
            p.id, 
            p.tssm_id, 
            p.no_aju, p.tgl_aju, p.no_dok_permohonan, p.tgl_dok_permohonan,
            p8.nomor AS nkt, p8.nomor_seri AS seri, p8.tanggal AS tanggal_lepas,
            mu.nama AS upt, mu.nama_satpel AS satpel,
            p.nama_tempat_pemeriksaan, p.alamat_tempat_pemeriksaan, p.tgl_pemeriksaan,
            p.nama_pemohon, p.alamat_pemohon, p.nomor_identitas_pemohon,
            p.nama_pengirim, p.alamat_pengirim, p.nomor_identitas_pengirim,
            p.nama_penerima, p.alamat_penerima, p.nomor_identitas_penerima,
            mn1.nama AS asal, mn3.nama AS kota_asal, pel_muat.nama AS pelabuhanasal,
            mn2.nama AS tujuan, mn4.nama AS kota_tujuan, pel_bongkar.nama AS pelabuhantuju,
            moda.nama AS moda, p.nama_alat_angkut_terakhir, p.no_voyage_terakhir,
            mjk.deskripsi AS kemas, pkom.jumlah_kemasan AS total_kemas, p.tanda_khusus,
            klas.deskripsi AS klasifikasi, kom.nama AS komoditas, pkom.nama_umum_tercetak, pkom.kode_hs AS hs,
            pkom.volumeP1 AS p1, pkom.volumeP2 AS p2, pkom.volumeP3 AS p3, pkom.volumeP4 AS p4,
            pkom.volumeP5 AS p5, pkom.volumeP6 AS p6, pkom.volumeP7 AS p7, pkom.volumeP8 AS p8,
            ms.nama AS satuan, pkom.harga_rp,
            
            -- Detail Kontainer untuk Excel
            (SELECT GROUP_CONCAT(CONCAT(nomor, ' (', segel, ')') SEPARATOR '; ') 
             FROM ptk_kontainer WHERE ptk_id = p.id) AS kontainer_string,
             
            -- Detail Dokumen untuk Excel
            (SELECT GROUP_CONCAT(CONCAT(mjd.nama, ':', pdok.no_dokumen) SEPARATOR '; ')
             FROM ptk_dokumen pdok 
             JOIN master_jenis_dokumen mjd ON pdok.jenis_dokumen_id = mjd.id 
             WHERE pdok.ptk_id = p.id) AS dokumen_pendukung_string
        ", false);

       $this->db->from('ptk p')
            ->join("$table p8", 'p.id = p8.ptk_id')
            ->join('ptk_komoditas pkom', 'p.id = pkom.ptk_id')
            ->join("$tabel_kom kom", 'pkom.komoditas_id = kom.id')
            ->join("$tabel_klas klas", 'pkom.klasifikasi_id = klas.id')
            ->join('master_upt mu', 'p.kode_satpel = mu.id')
            ->join('master_satuan ms', 'pkom.satuan_lain_id = ms.id', 'left')
            ->join('master_pelabuhan pel_muat', 'p.pelabuhan_muat_id = pel_muat.id', 'left')
            ->join('master_pelabuhan pel_bongkar', 'p.pelabuhan_bongkar_id = pel_bongkar.id', 'left')
            ->join('master_moda_alat_angkut moda', 'p.moda_alat_angkut_terakhir_id = moda.id', 'left')
            ->join('master_jenis_kemasan mjk', 'p.kemasan_id = mjk.id', 'left')
            ->join('master_negara mn1', 'p.negara_asal_id = mn1.id', 'left')
            ->join('master_negara mn2', 'p.negara_tujuan_id = mn2.id', 'left')
            ->join('master_kota_kab mn3', 'p.kota_kab_asal_id = mn3.id', 'left')
            ->join('master_kota_kab mn4', 'p.kota_kab_tujuan_id = mn4.id', 'left');

        $this->applyManualFilters($f, $table);

        return $this->db->order_by('p8.tanggal', 'DESC')
                        ->order_by('p.id', 'ASC')
                        ->get()->result_array();
    }

    public function countAll($f)
    {
        $table = $this->getTable($f['karantina']);
        if ($table === '') return 0;

        $this->db->from('ptk p')
            ->join("$table p8", 'p.id = p8.ptk_id')
            ->where([
                'p.is_verifikasi' => '1',
                'p.is_batal'      => '0',
                'p8.deleted_at'   => '1970-01-01 08:00:00',
            ])
            ->where("p8.nomor_seri != '*******'", null, false);

        $this->applyManualFilters($f, $table);

        return $this->db->count_all_results();
    }

    private function applyManualFilters($f, $table)
    {
        if (!empty($f['upt']) && !in_array(strtolower($f['upt']), ['all','semua'])) {
            if (strlen($f['upt']) <= 4) {
                $this->db->where("p.upt_id", $f['upt']);
            } else {
                $this->db->where("p.kode_satpel", $f['upt']);
            }
        }

        if (!empty($f['lingkup']) && $f['lingkup'] !== 'all') {
            $this->db->where('p.jenis_permohonan', $f['lingkup']);
        }
        if (!empty($f['start_date']) && !empty($f['end_date'])) {
            $this->db->where("p8.tanggal >=", $f['start_date'] . ' 00:00:00');
            $this->db->where("p8.tanggal <=", $f['end_date'] . ' 23:59:59');
        }
        $this->db->where([
            'p.is_verifikasi' => '1',
            'p.is_batal'      => '0',
            'p8.deleted_at'   => '1970-01-01 08:00:00',
        ])->where("p8.nomor_seri != '*******'", null, false);
    }

    public function getIds($f, $limit, $offset) { return []; }
    public function getByIds($ids) { return []; }
}