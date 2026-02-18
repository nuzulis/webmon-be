<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class M_Ptk_Core extends CI_Model
{

    public function get_ptk_detail($filter, $value)
    {
        $this->db->select("
            p.id, p.tssm_id,p.no_aju, p.no_dok_permohonan,
            p.tgl_aju, p.tgl_dok_permohonan,
            mu.nama AS upt, 
            mu.nama_satpel AS satpel,
            p.nama_pemohon, p.alamat_pemohon,
            p.nama_pengirim, p.alamat_pengirim,
            p.nama_penerima, p.alamat_penerima,
            p.jenis_karantina, p.npwp15,
            p.nama_tempat_pemeriksaan, p.alamat_tempat_pemeriksaan,
            mn1.nama AS negara_asal,
            mn2.nama AS negara_tujuan,
            pel_muat.nama AS pelabuhan_asal,
            pel_bongkar.nama AS pelabuhan_tujuan,
            moda.nama AS moda
        ");
        $this->db->from('ptk p');
        $this->db->join('master_upt mu','p.kode_satpel=mu.id','left');
        $this->db->join('master_negara mn1','p.negara_asal_id=mn1.id','left');
        $this->db->join('master_negara mn2','p.negara_tujuan_id=mn2.id','left');
        $this->db->join('master_pelabuhan pel_muat','p.pelabuhan_muat_id=pel_muat.id','left');
        $this->db->join('master_pelabuhan pel_bongkar','p.pelabuhan_bongkar_id=pel_bongkar.id','left');
        $this->db->join('master_moda_alat_angkut moda','p.moda_alat_angkut_terakhir_id=moda.id','left');

        if ($filter === 'AJU') {
            $this->db->where('p.no_aju', $value);
        } else {
            $this->db->where('p.id', $value);
        }

        return $this->db->get()->row_array();
    }

    public function get_komoditas($ptk_id, $kar)
    {
        $this->db->select("
            klas.deskripsi AS klasifikasi,
            kom.nama AS komoditas,
            p.nama_umum_tercetak,
            p.nama_latin_tercetak,
            p.kode_hs,
            p.kode_hs10,
            p.volume_bruto,
            p.volume_netto,
            p.volume_lain,
            ms.nama AS satuan,
            p.jantanP1, p.betinaP1, 
            p.jantanP2, p.betinaP2,
            p.jantanP3, p.betinaP3, 
            p.jantanP4, p.betinaP4,
            p.jantanP5, p.betinaP5, 
            p.jantanP6, p.betinaP6,
            p.jantanP7, p.betinaP7, 
            p.jantanP8, p.betinaP8,
            p.volumeP1, p.nettoP1,
            p.volumeP2, p.nettoP2,
            p.volumeP3, p.nettoP3,
            p.volumeP4, p.nettoP4,
            p.volumeP5, p.nettoP5,
            p.volumeP6, p.nettoP6,
            p.volumeP7, p.nettoP7,
            p.volumeP8, p.nettoP8
        ");
        $this->db->from('ptk_komoditas p');
        $this->db->join('master_satuan ms', 'ms.id = p.satuan_lain_id');

        switch ($kar) {
            case 'H':
                $this->db->join('komoditas_hewan kom', 'kom.id = p.komoditas_id');
                $this->db->join('klasifikasi_hewan klas', 'klas.id = p.klasifikasi_id');
                break;

            case 'I':
                $this->db->join('komoditas_ikan kom', 'kom.id = p.komoditas_id');
                $this->db->join('klasifikasi_ikan klas', 'klas.id = p.klasifikasi_id');
                break;

            case 'T':
                $this->db->join('komoditas_tumbuhan kom', 'kom.id = p.komoditas_id');
                $this->db->join('klasifikasi_tumbuhan klas', 'klas.id = p.klasifikasi_id');
                break;
        }

        $this->db->where('p.ptk_id', $ptk_id);
        $this->db->where('p.deleted_at', '1970-01-01 08:00:00');

        $rows = $this->db->get()->result_array();

        foreach ($rows as &$r) {
            $r['hs_final'] = ($r['kode_hs'] === '0000')
                ? $r['kode_hs10']
                : $r['kode_hs'];
        }

        return $rows;
}


    public function get_dokumen($ptk_id)
{
    $rows = $this->db
        ->select("
            p.id,
            p.no_dokumen,
            p.tanggal_dokumen,
            mjd.nama AS jenis_dok,
            mk.nama AS kota,
            mn.nama AS negara,
            p.jenis_dokumen_id,
            ptk.tssm_id,
            p.efile
        ")
        ->from('ptk_dokumen p')
        ->join('ptk', 'ptk.id = p.ptk_id') 
        ->join('master_jenis_dokumen mjd', 'mjd.id = p.jenis_dokumen_id')
        ->join('master_kota_kab mk', 'mk.id = p.kota_kab_asal_id', 'left')
        ->join('master_negara mn', 'mn.id = p.negara_asal_id', 'left')
        ->where('p.ptk_id', $ptk_id)
        ->get()
        ->result_array();

    foreach ($rows as &$r) {
        $r['penerbit'] = $r['kota'] ?: $r['negara'];
        if ($r['jenis_dokumen_id'] === '104') {
            $r['efile_url'] = $r['efile'];
        } else {
            $r['efile_url'] = empty($r['tssm_id'])
                ? "https://api.karantinaindonesia.go.id/barantin-sys/" . $r['efile']
                : $r['efile'];
        }
    }

    return $rows;
}


    public function get_kontainer($ptk_id)
    {
        return $this->db
            ->from('ptk_kontainer')
            ->where('ptk_id',$ptk_id)
            ->where('deleted_at','1970-01-01 08:00:00')
            ->get()->result_array();
    }
}
