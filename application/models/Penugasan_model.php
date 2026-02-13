<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'core/BaseModelStrict.php';

class Penugasan_model extends BaseModelStrict
{
    public function __construct()
    {
        parent::__construct();
    }

    public function getIds($f, $limit, $offset)
    {
        $this->db->select('h.id, MAX(h.tanggal) as max_tanggal', false)
            ->from('ptk p')
            ->join('master_upt mu', 'p.upt_id = mu.id')
            ->join('ptk_surtug_header h', 'p.id = h.ptk_id')
            ->join('ptk_surtug_petugas pp', 'h.id = pp.ptk_surtug_header_id')
            ->join('ptk_surtug_penugasan pnp', 'pp.id = pnp.ptk_surtug_petugas_id')
            ->join('master_penugasan mpn', 'pnp.penugasan_id = mpn.id')
            ->join('master_pegawai mp1', 'pp.petugas_id = mp1.id')
            ->join('master_pegawai mp2', 'h.penanda_tangan_id = mp2.id');

        $this->applyManualFilter($f);
        $sortMap = [
            'nomor_surtug'       => 'MAX(h.nomor)',
            'tgl_surtug'         => 'MAX(h.tanggal)',
            'no_dok_permohonan'  => 'MAX(p.no_dok_permohonan)',
            'tgl_dok_permohonan' => 'MAX(p.tgl_dok_permohonan)',
            'upt'                => 'MAX(mu.nama)',
            'satpel'             => 'MAX(mu.nama_satpel)',
            'nama_petugas'       => 'MAX(mp1.nama)',
            'penandatangan'      => 'MAX(mp2.nama)',
            'jenis_tugas'        => 'MAX(mpn.nama)',
        ];

        $this->applySorting(
            $f['sort_by'] ?? null,
            $f['sort_order'] ?? 'DESC',
            $sortMap,
            ['MAX(h.tanggal)', 'DESC']
        );

        $this->db->group_by('h.id');
        $this->db->limit($limit, $offset);

        return array_column($this->db->get()->result_array(), 'id');
    }

    public function getByIds($ids)
    {
        if (empty($ids)) return [];

        $this->db->select("
            h.id,
            h.nomor AS nomor_surtug, 
            h.tanggal AS tgl_surtug,
            p.no_dok_permohonan, 
            p.tgl_dok_permohonan,
            mu.nama AS upt, 
            mu.nama_satpel AS satpel,
            mp1.nama AS nama_petugas, 
            mp1.nip AS nip_petugas,
            mp2.nama AS penandatangan, 
            mp2.nip AS nip_ttd,
            mpn.nama AS jenis_tugas
        ", false);

        $this->db->from('ptk p')
            ->join('master_upt mu', 'p.upt_id = mu.id')
            ->join('ptk_surtug_header h', 'p.id = h.ptk_id')
            ->join('ptk_surtug_petugas pp', 'h.id = pp.ptk_surtug_header_id')
            ->join('ptk_surtug_penugasan pnp', 'pp.id = pnp.ptk_surtug_petugas_id')
            ->join('master_penugasan mpn', 'pnp.penugasan_id = mpn.id')
            ->join('master_pegawai mp1', 'pp.petugas_id = mp1.id')
            ->join('master_pegawai mp2', 'h.penanda_tangan_id = mp2.id');

        $this->db->where_in('h.id', $ids);
        $this->db->order_by('h.tanggal', 'DESC');

        return $this->db->get()->result_array();
    }

    public function countAll($f)
    {
        $this->db->select('COUNT(DISTINCT h.id) AS total', false)
            ->from('ptk p')
            ->join('master_upt mu', 'p.upt_id = mu.id')
            ->join('ptk_surtug_header h', 'p.id = h.ptk_id')
            ->join('ptk_surtug_petugas pp', 'h.id = pp.ptk_surtug_header_id')
            ->join('ptk_surtug_penugasan pnp', 'pp.id = pnp.ptk_surtug_petugas_id')
            ->join('master_penugasan mpn', 'pnp.penugasan_id = mpn.id')
            ->join('master_pegawai mp1', 'pp.petugas_id = mp1.id')
            ->join('master_pegawai mp2', 'h.penanda_tangan_id = mp2.id');

        $this->applyManualFilter($f);

        $row = $this->db->get()->row();
        return $row ? (int) $row->total : 0;
    }

    public function getFullData($f)
    {
        $this->db->select("
            p.no_aju, 
            p.no_dok_permohonan, 
            p.tgl_dok_permohonan,
            mu.nama_satpel AS satpel, 
            mu.nama AS upt,
            h.nomor AS nomor_surtug, 
            h.tanggal AS tgl_surtug,
            mp1.nama AS nama_petugas, 
            mp1.nip AS nip_petugas,
            mpn.nama AS jenis_tugas,
            mn1.nama AS negara_asal, 
            mn3.nama AS daerah_asal,
            mn2.nama AS negara_tujuan, 
            mn4.nama AS daerah_tujuan,
            pkom.nama_umum_tercetak, 
            pkom.kode_hs,
            pkom.volumeP1, pkom.volumeP2, pkom.volumeP3, pkom.volumeP4,
            pkom.volumeP5, pkom.volumeP6, pkom.volumeP7, pkom.volumeP8,
            ms.nama AS nama_satuan,
            CASE 
                WHEN p.jenis_karantina = 'H' THEN kh.nama 
                WHEN p.jenis_karantina = 'I' THEN ki.nama 
                WHEN p.jenis_karantina = 'T' THEN kt.nama 
                ELSE '-' 
            END AS nama_komoditas
        ", false);

        $this->db->from('ptk p')
            ->join('master_upt mu', 'p.upt_id = mu.id')
            ->join('ptk_surtug_header h', 'p.id = h.ptk_id')
            ->join('ptk_surtug_petugas pp', 'h.id = pp.ptk_surtug_header_id')
            ->join('ptk_surtug_penugasan pnp', 'pp.id = pnp.ptk_surtug_petugas_id')
            ->join('master_penugasan mpn', 'pnp.penugasan_id = mpn.id')
            ->join('master_pegawai mp1', 'pp.petugas_id = mp1.id')
            ->join('master_pegawai mp2', 'h.penanda_tangan_id = mp2.id')
            ->join('ptk_komoditas pkom', 'p.id = pkom.ptk_id', 'left')
            ->join('master_satuan ms', 'pkom.satuan_lain_id = ms.id', 'left')
            ->join('master_negara mn1', 'p.negara_asal_id = mn1.id', 'left')
            ->join('master_negara mn2', 'p.negara_tujuan_id = mn2.id', 'left')
            ->join('master_kota_kab mn3', 'p.kota_kab_asal_id = mn3.id', 'left')
            ->join('master_kota_kab mn4', 'p.kota_kab_tujuan_id = mn4.id', 'left')
            ->join('komoditas_hewan kh', 'pkom.komoditas_id = kh.id AND p.jenis_karantina = "H"', 'left')
            ->join('komoditas_ikan ki', 'pkom.komoditas_id = ki.id AND p.jenis_karantina = "I"', 'left')
            ->join('komoditas_tumbuhan kt', 'pkom.komoditas_id = kt.id AND p.jenis_karantina = "T"', 'left');

        $this->applyManualFilter($f);

        $this->db->order_by('h.tanggal', 'DESC');

        return $this->db->get()->result_array();
    }

    private function applyManualFilter($f)
    {
        $this->db->where([
            'p.is_verifikasi' => '1',
            'p.is_batal'      => '0',
            'h.deleted_at'    => '1970-01-01 08:00:00'
        ]);
        if (!empty($f['petugas'])) {
            $this->db->where('pp.petugas_id', $f['petugas']);
        } 
        else if (!empty($f['karantina'])) {
            $kar = strtoupper(trim($f['karantina']));
            $kode = (strlen($kar) > 1) ? substr($kar, -1) : $kar;
            $this->db->where('p.jenis_karantina', $kode);
        }
        if (!empty($f['upt']) && !in_array(strtolower($f['upt']), ['all', 'semua', '1000'])) {
            $cleanUpt = substr($f['upt'], 0, 2) . '00';
            $this->db->where('p.upt_id', $cleanUpt);
        }
        if (!empty($f['start_date']) && !empty($f['end_date'])) {
            $this->db->where('h.tanggal >=', $f['start_date'] . ' 00:00:00');
            $this->db->where('h.tanggal <=', $f['end_date'] . ' 23:59:59');
        }
        if (!empty($f['search'])) {
            $this->applyGlobalSearch($f['search'], [
                'h.nomor',
                'p.no_dok_permohonan',
                'mu.nama',
                'mu.nama_satpel',
                'mp1.nama',
                'mp1.nip',
                'mp2.nama',
                'mpn.nama',
            ]);
        }
    }
    public function getPaginated(array $f, int $page = 1, int $perPage = 10): array
    {
        $offset = ($page - 1) * $perPage;
        
        $ids = $this->getIds($f, $perPage, $offset);
        $data = $this->getByIds($ids);
        $total = $this->countAll($f);

        return ['data' => $data, 'total' => $total];
    }

    public function getForExport(array $f): array
    {
        return $this->getFullData($f);
    }
}