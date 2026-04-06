<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Carimediapembawa_model extends CI_Model {

    /**
     
     * @param string $keyword
     * @param string $karantina   
     * @param string $upt_id
     * @return array
     */
    public function searchMediaPembawa($keyword, $karantina, $upt_id)
    {
        $mapPelepasan = ['H' => 'kh', 'T' => 'kt', 'I' => 'ki'];
        $tabelPelepasan = $mapPelepasan[$karantina] ?? 'kh';

        $this->db
            ->select("
                ptk.no_dok_permohonan,
                klas.deskripsi   AS klasifikasi,
                kom.nama         AS komoditas,
                p.nama_umum_tercetak,
                p.kode_hs,
                p.volume_bruto,
                p.volume_netto,
                p.volume_lain,
                ms.nama          AS satuan,
                ptk.tgl_dok_permohonan,
                ptk.nama_pemohon,
                ptk.nama_penerima,
                ptk.nama_pengirim,
                ptk.jenis_karantina,
                ptk.jenis_permohonan,
                ptk.upt_id,
                mu.nama          AS nama_upt,
                mu.nama_satpel   AS satpel,
                mn1.nama         AS asal,
                mn2.nama         AS tujuan,
                mn3.nama         AS kota_asal,
                mn4.nama         AS kota_tujuan,
                GROUP_CONCAT(DISTINCT pk.nomor) AS nomor_kontainer,
                GROUP_CONCAT(DISTINCT pk.segel) AS segel,
                COUNT(DISTINCT pk.id) AS jumlah_kontainer,
                tp8.nama         AS nama_ttd_p8,
                p8.nomor         AS nomor_p8,
                p8.nomor_seri    AS nomor_seri_p8,
                p8.tanggal       AS tgl_p8
            ", FALSE)
            ->from('ptk_komoditas p')
            ->join('ptk', 'p.ptk_id = ptk.id')
            ->join('ptk_kontainer pk', 'pk.ptk_id = ptk.id', 'left')
            ->join("pn_pelepasan_" . $tabelPelepasan . " AS p8", "p.ptk_id = p8.ptk_id AND p8.deleted_at = '1970-01-01 08:00:00'", 'left')
            ->join('master_pegawai AS tp8', 'tp8.id = p8.user_ttd_id', 'left')
            ->join('master_satuan ms', 'p.satuan_lain_id = ms.id', 'left')
            ->join('master_upt mu', 'ptk.kode_satpel = mu.id', 'left')
            ->join('master_negara mn1', 'ptk.negara_asal_id = mn1.id', 'left')
            ->join('master_negara mn2', 'ptk.negara_tujuan_id = mn2.id', 'left')
            ->join('master_kota_kab mn3', 'ptk.kota_kab_asal_id = mn3.id', 'left')
            ->join('master_kota_kab mn4', 'ptk.kota_kab_tujuan_id = mn4.id', 'left')
            ->where('p.deleted_at', '1970-01-01 08:00:00')
            ->where('ptk.jenis_karantina', $karantina)
            ->like('p.nama_umum_tercetak', $keyword, 'both');
        if ($karantina === 'H') {
            $this->db
                ->join('komoditas_hewan kom', 'p.komoditas_id = kom.id', 'left')
                ->join('klasifikasi_hewan klas', 'p.klasifikasi_id = klas.id', 'left');
        } elseif ($karantina === 'I') {
            $this->db
                ->join('komoditas_ikan kom', 'p.komoditas_id = kom.id', 'left')
                ->join('klasifikasi_ikan klas', 'p.klasifikasi_id = klas.id', 'left');
        } elseif ($karantina === 'T') {
            $this->db
                ->join('komoditas_tumbuhan kom', 'p.komoditas_id = kom.id', 'left')
                ->join('klasifikasi_tumbuhan klas', 'p.klasifikasi_id = klas.id', 'left');
        }
        if ($upt_id !== "1000") {
            $this->db->where('ptk.upt_id', $upt_id);
        }

        $this->db->group_by('p.id, ptk.no_dok_permohonan, klas.deskripsi, kom.nama,
            p.nama_umum_tercetak, p.kode_hs, p.volume_bruto, p.volume_netto,
            p.volume_lain, ms.nama, ptk.tgl_dok_permohonan, ptk.nama_pemohon,
            ptk.nama_penerima, ptk.nama_pengirim, ptk.jenis_karantina,
            ptk.jenis_permohonan, ptk.upt_id, mu.nama, mu.nama_satpel,
            mn1.nama, mn2.nama, mn3.nama, mn4.nama,
            tp8.nama, p8.nomor, p8.nomor_seri, p8.tanggal');
        $this->db->order_by('ptk.tgl_dok_permohonan', 'DESC');

        return $this->db->get()->result_array();
    }
}
