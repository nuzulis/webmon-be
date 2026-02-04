<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class PermohonanExport_model extends CI_Model {

    public function getExportData($f) {

        $this->db->select("
            p.id,
            p.tssm_id,
            p.kode_satpel,
            p.nama_tempat_pemeriksaan,
            mu.nama AS upt,
            mu.nama_satpel AS satpel,
            klas.deskripsi AS klasifikasi,
            kom.nama AS komoditas,
            pkom.nama_umum_tercetak,
            pkom.kode_hs AS hs,
            pkom.volumeP1 AS p1,
            pkom.volumeP2 AS p2,
            pkom.volumeP3 AS p3,
            pkom.volumeP4 AS p4,
            pkom.volumeP5 AS p5,
            ms.nama AS satuan,
            mh.level_risiko AS risiko,
            mn1.nama AS asal,
            mn2.nama AS tujuan,
            mn3.nama AS kota_asal,
            mn4.nama AS kota_tujuan,
            p.no_aju,
            p.tgl_aju,
            p.no_dok_permohonan,
            p.tgl_dok_permohonan,
            p.nama_pemohon,
            p.alamat_pemohon,
            p.nomor_identitas_pemohon,
            p.nama_pengirim,
            p.alamat_pengirim,
            p.nomor_identitas_pengirim,
            p.nama_penerima,
            p.alamat_penerima,
            p.nomor_identitas_penerima,
            ANY_VALUE(p1b.waktu_periksa) AS tgl_periksa
        ", false);

        $this->db->from('ptk p');
        $this->db->join('ptk_komoditas pkom', 'p.id = pkom.ptk_id');
        $this->db->join('master_upt mu', 'p.kode_satpel = mu.id');
        $this->db->join('master_satuan ms', 'pkom.satuan_lain_id = ms.id', 'left');
        $this->db->join('master_hs mh', 'pkom.kode_hs = mh.kode', 'left');
        $this->db->join('master_negara mn1', 'p.negara_asal_id = mn1.id', 'left');
        $this->db->join('master_negara mn2', 'p.negara_tujuan_id = mn2.id', 'left');
        $this->db->join('master_kota_kab mn3', 'p.kota_kab_asal_id = mn3.id', 'left');
        $this->db->join('master_kota_kab mn4', 'p.kota_kab_tujuan_id = mn4.id', 'left');
        $this->db->join('pn_fisik_kesehatan p1b', 'p.id = p1b.ptk_id', 'left');
        if ($f['karantina'] === 'kh') {
            $this->db->join('komoditas_hewan kom', 'pkom.komoditas_id = kom.id');
            $this->db->join('klasifikasi_hewan klas', 'pkom.klasifikasi_id = klas.id');
        } elseif ($f['karantina'] === 'ki') {
            $this->db->join('komoditas_ikan kom', 'pkom.komoditas_id = kom.id');
            $this->db->join('klasifikasi_ikan klas', 'pkom.klasifikasi_id = klas.id');
        } else {
            $this->db->join('komoditas_tumbuhan kom', 'pkom.komoditas_id = kom.id');
            $this->db->join('klasifikasi_tumbuhan klas', 'pkom.klasifikasi_id = klas.id');
        }
        $this->db->where([
            'p.is_verifikasi' => '1',
            'p.is_batal'      => '0',
            'pkom.deleted_at' => '1970-01-01 08:00:00',
        ]);

        if ($f['upt'] && $f['upt'] !== 'all') {
            $this->db->where('p.upt_id', $f['upt']);
        }

        if ($f['permohonan']) {
            $this->db->where('p.jenis_permohonan', $f['permohonan']);
        }

        if ($f['start_date'] && $f['end_date']) {
            $this->db->where('p.tgl_dok_permohonan >=', $f['start_date']);
            $this->db->where(
                'p.tgl_dok_permohonan <',
                date('Y-m-d', strtotime('+1 day', strtotime($f['end_date'])))
            );
        }

        $this->db->group_by('p.id');
        return $this->db->get()->result();
    }
}
