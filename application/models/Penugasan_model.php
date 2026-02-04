<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Penugasan_model extends CI_Model
{
    public function getPaginated(array $f, int $page = 1, int $perPage = 10): array
    {
        $offset = ($page - 1) * $perPage;
        $this->_baseQuery($f);
        $total = $this->db->count_all_results('', false);
        $this->db->limit($perPage, $offset);
        $data = $this->db->get()->result_array();

        return [
            'data'  => $data,
            'total' => $total,
        ];
    }

        public function getForExport(array $f): array
    {
        $this->db->select("
            p.no_aju, p.no_dok_permohonan, p.tgl_dok_permohonan,
            p.jenis_karantina,
            mu.nama_satpel AS satpel,
            h.nomor AS nomor_surtug, h.tanggal AS tgl_surtug,
            mp1.nama AS nama_petugas, mp1.nip AS nip_petugas,
            mpn.nama AS jenis_tugas,
            mp2.nama AS penandatangan, mp2.nip AS nip_ttd,
            mn1.nama AS negara_asal, mn3.nama AS daerah_asal,
            mn2.nama AS negara_tujuan, mn4.nama AS daerah_tujuan,
            pkom.nama_umum_tercetak, pkom.kode_hs,
            pkom.volumeP1, pkom.volumeP2, pkom.volumeP3, pkom.volumeP4,
            pkom.volumeP5, pkom.volumeP6, pkom.volumeP7, pkom.volumeP8,
            ms.nama AS nama_satuan
        ", false);

        $this->db->from('ptk p');
        $this->db->join('master_upt mu', 'p.kode_satpel = mu.id');
        $this->db->join('ptk_surtug_header h', 'p.id = h.ptk_id');
        $this->db->join('ptk_surtug_petugas pp', 'h.id = pp.ptk_surtug_header_id');
        $this->db->join('ptk_surtug_penugasan pnp', 'pp.id = pnp.ptk_surtug_petugas_id');
        $this->db->join('master_penugasan mpn', 'pnp.penugasan_id = mpn.id');
        $this->db->join('master_pegawai mp1', 'pp.petugas_id = mp1.id');
        $this->db->join('master_pegawai mp2', 'h.penanda_tangan_id = mp2.id');
        $this->db->join('ptk_komoditas pkom', 'p.id = pkom.ptk_id', 'left');
        $this->db->join('master_satuan ms', 'pkom.satuan_lain_id = ms.id', 'left');
        $this->db->join('master_negara mn1', 'p.negara_asal_id = mn1.id', 'left');
        $this->db->join('master_negara mn2', 'p.negara_tujuan_id = mn2.id', 'left');
        $this->db->join('master_kota_kab mn3', 'p.kota_kab_asal_id = mn3.id', 'left');
        $this->db->join('master_kota_kab mn4', 'p.kota_kab_tujuan_id = mn4.id', 'left');

        $this->db->where([
            'p.is_verifikasi' => '1',
            'p.is_batal'      => '0',
            'h.deleted_at'    => '1970-01-01 08:00:00'
        ]);

        if (!empty($f['petugas'])) {
            $this->db->where('pp.petugas_id', $f['petugas']);
        } else if (!empty($f['karantina'])) {
            $this->db->where('p.jenis_karantina', strtoupper($f['karantina']));
        }
        $this->db->select("
            CASE 
                WHEN p.jenis_karantina = 'H' THEN kh.nama 
                WHEN p.jenis_karantina = 'I' THEN ki.nama 
                WHEN p.jenis_karantina = 'T' THEN kt.nama 
                ELSE '-' 
            END AS nama_komoditas
        ", false);

        $this->db->join('komoditas_hewan kh', 'pkom.komoditas_id = kh.id AND p.jenis_karantina = "H"', 'left');
        $this->db->join('komoditas_ikan ki', 'pkom.komoditas_id = ki.id AND p.jenis_karantina = "I"', 'left');
        $this->db->join('komoditas_tumbuhan kt', 'pkom.komoditas_id = kt.id AND p.jenis_karantina = "T"', 'left');
        if (!empty($f['upt']) && $f['upt'] !== 'all') {
            $this->db->where('p.upt_id', $f['upt']);
        }
        if (!empty($f['start_date']) && !empty($f['end_date'])) {
            $this->db->where("h.tanggal >=", $f['start_date'] . ' 00:00:00');
            $this->db->where("h.tanggal <=", $f['end_date'] . ' 23:59:59');
        }
        $this->db->order_by('p.id', 'ASC');
        
        $query = $this->db->get();
        
        if (!$query) {
            $error = $this->db->error();
            log_message('error', 'SQL Error: ' . $error['message']);
            return [];
        }

        return $query->result_array();
    }

    protected function _baseQuery(array $f): void
    {
        $this->db->select("
            h.nomor                AS nomor_surtug,
            h.tanggal              AS tgl_surtug,
            p.no_dok_permohonan,
            p.tgl_dok_permohonan,
            p.jenis_karantina,
            mu.nama                AS upt,
            mu.nama_satpel         AS satpel,
            mp1.nama               AS nama_petugas,
            mp1.nip                AS nip_petugas,
            mp2.nama               AS penandatangan,
            mp2.nip                AS nip_ttd,
            mpn.nama               AS jenis_tugas
        ", false);

        $this->db->from('ptk p');
        $this->db->join('master_upt mu', 'p.upt_id = mu.id');
        $this->db->join('ptk_surtug_header h', 'p.id = h.ptk_id');
        $this->db->join('ptk_surtug_petugas pp', 'h.id = pp.ptk_surtug_header_id');
        $this->db->join('ptk_surtug_penugasan pnp', 'pp.id = pnp.ptk_surtug_petugas_id');
        $this->db->join('master_penugasan mpn', 'pnp.penugasan_id = mpn.id');
        $this->db->join('master_pegawai mp1', 'pp.petugas_id = mp1.id');
        $this->db->join('master_pegawai mp2', 'h.penanda_tangan_id = mp2.id');
        $this->db->where([
            'p.is_verifikasi' => '1',
            'p.is_batal'      => '0',
            'h.deleted_at'    => '1970-01-01 08:00:00'
        ]);

        if (!empty($f['petugas'])) {
            $this->db->where('pp.petugas_id', $f['petugas']);
        }
        else if (!empty($f['karantina'])) {
            $this->db->where('p.jenis_karantina', strtoupper($f['karantina']));
        }

        if (!empty($f['upt']) && $f['upt'] !== 'all') {
            $cleanUpt = substr($f['upt'], 0, 2) . '00';
            $this->db->where('p.upt_id', $cleanUpt);
        }
        if (!empty($f['start_date']) && !empty($f['end_date'])) {
            $this->db->where(
                "DATE(h.tanggal) BETWEEN '{$f['start_date']}' AND '{$f['end_date']}'",
                null,
                false
            );
        }

        $this->db->order_by('h.tanggal', 'DESC');
    }
}
