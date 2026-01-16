<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'models/BaseModelStrict.php';

class Nnc_model extends BaseModelStrict
{
    /**
     * Harus kompatibel dengan BaseModelStrict::getIds($f, $limit, $offset)
     */
    public function getIds($f, $limit, $offset)
    {
        $this->db->select('p.id, MAX(p6.tanggal) AS last_tgl', false)
            ->from('ptk p')
            ->join('pn_penolakan p6', 'p.id = p6.ptk_id')
            ->where([
                'p.is_verifikasi' => '1',
                'p.is_batal'      => '0',
                'p6.deleted_at'   => '1970-01-01 08:00:00',
                'p6.dokumen_karantina_id' => '32', // NNC
            ]);

        $this->applyCommonFilter($f);
        $this->applyDateFilter('p6.tanggal', $f);

        $this->db->group_by('p.id')
                 ->order_by('last_tgl', 'DESC')
                 ->limit($limit, $offset);

        $res = $this->db->get()->result_array();
        return array_column($res, 'id');
    }

   public function getByIds($ids)
{
    if (empty($ids)) return [];

    $karantina = strtoupper($this->input->get('karantina', true));
    $tabel_kom = "komoditas_" . ($karantina == 'H' ? 'hewan' : ($karantina == 'I' ? 'ikan' : 'tumbuhan'));

   
    $this->db->select("
        p.id, p.tssm_id, p.no_aju, p.no_dok_permohonan, p.tgl_dok_permohonan,
        mu.nama_satpel, mt.nama AS upt,
        p6.nomor AS nomor_nnc, p6.tanggal AS tgl_nnc,
        p6.information, p6.consignment, p6.consignment_detil, p6.kepada,
        p6.specify1, p6.specify2, p6.specify3, p6.specify4, p6.specify5,
        mp.nama AS petugas,
        p.nama_pengirim, p.nama_penerima, p.jenis_permohonan,
        mn1.nama AS asal, mn2.nama AS tujuan,
        mn3.nama AS kota_asal, mn4.nama AS kota_tujuan,
        kom.nama AS komoditas_single, 
        pkom.volumeP1 AS volume_single, 
        pkom.volumeP6 AS volume_p6_single,
        ms.nama AS satuan_single
    ", false);

    $this->db->from('ptk p')
        ->join('pn_penolakan p6', 'p.id = p6.ptk_id')
        ->join('master_pegawai mp', 'p6.user_ttd_id = mp.id')
        ->join('master_upt mu', 'p.kode_satpel = mu.id')
        ->join('master_upt mt', 'p.upt_id = mt.id')
        ->join('ptk_komoditas pkom', 'p.id = pkom.ptk_id')
        ->join($tabel_kom . ' kom', 'pkom.komoditas_id = kom.id')
        ->join('master_satuan ms', 'pkom.satuan_lain_id = ms.id', 'left')
        ->join('master_negara mn1', 'p.negara_asal_id = mn1.id', 'left')
        ->join('master_negara mn2', 'p.negara_tujuan_id = mn2.id', 'left')
        ->join('master_kota_kab mn3', 'p.kota_kab_asal_id = mn3.id', 'left')
        ->join('master_kota_kab mn4', 'p.kota_kab_tujuan_id = mn4.id', 'left');

    $this->db->where_in('p.id', $ids)
             ->where('pkom.deleted_at', '1970-01-01 08:00:00')
             ->order_by('p.id', 'ASC')
             ->order_by('tgl_nnc', 'DESC');

    return $this->db->get()->result_array();
}

  
    public function countAll($f)
    {
        $this->db->select('COUNT(DISTINCT p.id) AS total', false)
            ->from('ptk p')
            ->join('pn_penolakan p6', 'p.id = p6.ptk_id')
            ->where([
                'p.is_verifikasi' => '1',
                'p.is_batal'      => '0',
                'p6.deleted_at'   => '1970-01-01 08:00:00',
                'p6.dokumen_karantina_id' => '32',
            ]);

        $this->applyCommonFilter($f);
        $this->applyDateFilter('p6.tanggal', $f);

        $res = $this->db->get()->row();
        return $res ? (int) $res->total : 0;
    }
}