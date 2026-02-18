<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'core/BaseModelStrict.php';

class PeriksaLapangan_model extends BaseModelStrict
{
    public function __construct()
    {
        parent::__construct();
    }

    public function getIds($f, $limit, $offset)
    {
        $this->db->select('p.id, MAX(ohp.tgl_periksa) as max_tgl', false) 
            ->from('ptk p')
            ->join('ptk_surtug_header p1a', 'p.id = p1a.ptk_id', 'inner')
            ->join('officer_hasil_periksa ohp', 'ohp.id_surat_tugas = p1a.id', 'inner')
            ->join('master_upt mu', 'p.kode_satpel = mu.id', 'left')
            ->join('master_pegawai mp', 'ohp.id_petugas = mp.id', 'left');

        $needKomoditasJoin = !empty($f['search']);
        if ($needKomoditasJoin) {
            $this->db->join('ptk_komoditas pk', 'ohp.id_komoditas = pk.id', 'left');
            $this->db->join('komoditas_hewan kh', "pk.komoditas_id = kh.id AND p.jenis_karantina = 'H'", 'left');
            $this->db->join('komoditas_ikan ki', "pk.komoditas_id = ki.id AND p.jenis_karantina = 'I'", 'left');
            $this->db->join('komoditas_tumbuhan kt', "pk.komoditas_id = kt.id AND p.jenis_karantina = 'T'", 'left');
        }

        $this->applyManualFilter($f, $needKomoditasJoin);
        
        $sortMap = [
            'no_aju'               => 'p.no_aju',
            'no_dok_permohonan'    => 'p.no_dok_permohonan',
            'tgl_dok_permohonan'   => 'p.tgl_dok_permohonan',
            'upt'                  => 'MAX(mu.nama)',
            'nama_satpel'          => 'MAX(mu.nama_satpel)',
            'tgl_periksa_terakhir' => 'max_tgl',
            'nama_petugas'         => 'MAX(mp.nama)'
        ];

        $this->applySorting(
            $f['sort_by'] ?? null,
            $f['sort_order'] ?? 'DESC',
            $sortMap,
            ['max_tgl', 'DESC']
        );

        $this->db->group_by('p.id');
        $this->db->limit($limit, $offset);

        $query = $this->db->get();
        if (!$query) return [];

        return array_column($query->result_array(), 'id');
    }

    public function getByIds($ids)
    {
        if (empty($ids)) return [];

        $this->db->select("
            p.id, p.no_aju, p.no_dok_permohonan, p.tgl_dok_permohonan, p.jenis_karantina,
            REPLACE(REPLACE(MAX(mu.nama), 'Balai Besar Karantina Hewan, Ikan, dan Tumbuhan', 'BBKHIT'), 'Balai Karantina Hewan, Ikan, dan Tumbuhan', 'BKHIT') AS nama_upt,
            MAX(mu.nama_satpel) AS nama_satpel,
            
            GROUP_CONCAT(DISTINCT p1a.nomor SEPARATOR '\n') AS nomor_surtug,
            GROUP_CONCAT(DISTINCT mp.nama SEPARATOR '\n') AS nama_petugas,
            GROUP_CONCAT(DISTINCT 
                CASE 
                    WHEN p.jenis_karantina = 'H' THEN kh.nama
                    WHEN p.jenis_karantina = 'I' THEN ki.nama
                    WHEN p.jenis_karantina = 'T' THEN kt.nama
                    ELSE '-'
                END
            SEPARATOR '\n') AS komoditas,
            GROUP_CONCAT(DISTINCT sl.locationName SEPARATOR '\n') AS lokasi_periksa,
            MAX(ohp.tgl_periksa) AS tgl_periksa_terakhir,
            GROUP_CONCAT(DISTINCT ohp.temuan SEPARATOR ' | ') AS ringkasan_temuan
        ", false);

        $this->db->from('ptk p')
            ->join('ptk_surtug_header p1a', 'p.id = p1a.ptk_id', 'inner')
            ->join('officer_hasil_periksa ohp', 'ohp.id_surat_tugas = p1a.id', 'inner')
            ->join('ptk_surtug_lokasi sl', 'p1a.id = sl.ptk_surtug_header_id', 'left')
            ->join('ptk_komoditas pk', 'ohp.id_komoditas = pk.id', 'left')
            ->join('komoditas_hewan kh', "pk.komoditas_id = kh.id AND p.jenis_karantina = 'H'", 'left')
            ->join('komoditas_ikan ki',  "pk.komoditas_id = ki.id AND p.jenis_karantina = 'I'", 'left')
            ->join('komoditas_tumbuhan kt', "pk.komoditas_id = kt.id AND p.jenis_karantina = 'T'", 'left')
            ->join('master_pegawai mp', 'ohp.id_petugas = mp.id', 'left')
            ->join('master_upt mu', 'p.kode_satpel = mu.id', 'left');

        $this->db->where_in('p.id', $ids);
        $this->db->group_by('p.id');
        $this->db->order_by('tgl_periksa_terakhir', 'DESC');

        return $this->db->get()->result_array();
    }

    public function countAll($f)
    {
        $this->db->select('COUNT(DISTINCT p.id) AS total', false)
            ->from('ptk p')
            ->join('ptk_surtug_header p1a', 'p.id = p1a.ptk_id', 'inner')
            ->join('officer_hasil_periksa ohp', 'ohp.id_surat_tugas = p1a.id', 'inner')
            ->join('master_upt mu', 'p.kode_satpel = mu.id', 'left') 
            ->join('master_pegawai mp', 'ohp.id_petugas = mp.id', 'left');
        $needKomoditasJoin = !empty($f['search']);
        if ($needKomoditasJoin) {
            $this->db->join('ptk_komoditas pk', 'ohp.id_komoditas = pk.id', 'left');
            $this->db->join('komoditas_hewan kh', "pk.komoditas_id = kh.id AND p.jenis_karantina = 'H'", 'left');
            $this->db->join('komoditas_ikan ki', "pk.komoditas_id = ki.id AND p.jenis_karantina = 'I'", 'left');
            $this->db->join('komoditas_tumbuhan kt', "pk.komoditas_id = kt.id AND p.jenis_karantina = 'T'", 'left');
        }

        $this->applyManualFilter($f, $needKomoditasJoin);

        $res = $this->db->get()->row();
        return $res ? (int) $res->total : 0;
    }

    public function getDetailFinal($p_id)
    {
        $this->db->select('psh.id, psh.nomor, psh.tanggal, psh.perihal, psh.status');
        $this->db->from('ptk_surtug_header psh');
        $this->db->join('officer_hasil_periksa ohp', 'psh.id = ohp.id_surat_tugas', 'inner');
        $this->db->where('psh.ptk_id', $p_id);
        $this->db->where('psh.deleted_at', '1970-01-01 08:00:00');
        $this->db->group_by('psh.id');
        $this->db->order_by('MAX(ohp.tgl_periksa)', 'DESC'); 
        $this->db->limit(1);

        $query = $this->db->get();
        $surtug = $query->row_array();
        
        if (!$surtug) {
            $query_fallback = $this->db->select('id, nomor, tanggal, perihal, status')
                ->from('ptk_surtug_header')
                ->where('ptk_id', $p_id)
                ->where('deleted_at', '1970-01-01 08:00:00')
                ->order_by('created_at', 'DESC')
                ->limit(1)
                ->get();
            $surtug = ($query_fallback) ? $query_fallback->row_array() : null;
        }

        if (!$surtug) return null;

        $id_st = $surtug['id'];

        return [
            'surat_tugas' => $surtug,
            'lokasi'      => $this->db->where('ptk_surtug_header_id', $id_st)->get('ptk_surtug_lokasi')->result_array(),
            'petugas'     => $this->db->select('mp.nama, mp.nip, sp.status')
                                ->from('ptk_surtug_petugas sp')
                                ->join('master_pegawai mp', 'sp.petugas_id = mp.id', 'left')
                                ->where('sp.ptk_surtug_header_id', $id_st)
                                ->get()->result_array(),
            'timeline'    => $this->db->where('surtug_header_id', $id_st)->order_by('time', 'ASC')->get('ptk_surtug_riwayat')->result_array(),
            'hasil'       => $this->db->where('id_surat_tugas', $id_st)->order_by('tgl_periksa', 'ASC')->get('officer_hasil_periksa')->result_array(),
        ];
    }

    public function getFullData($f)
    {  
        $sqlMulai   = "(SELECT MIN(time) FROM ptk_surtug_riwayat WHERE surtug_header_id = p1a.id AND status = 'mulai')";
        $sqlSelesai = "(SELECT MAX(time) FROM ptk_surtug_riwayat WHERE surtug_header_id = p1a.id AND status = 'selesai')";
        $sqlLog     = "(SELECT keterangan FROM ptk_surtug_riwayat WHERE surtug_header_id = p1a.id ORDER BY time DESC LIMIT 1)";

        $this->db->select("
            p.id, p.no_aju, p.no_dok_permohonan, p.tgl_dok_permohonan,
            p1a.nomor AS no_surtug, p1a.tanggal AS tgl_surtug,
            mu.nama AS upt_nama, mu.nama_satpel,
            mp.nama AS nama_petugas, mp.nip AS nip_petugas,
            ohp.tgl_periksa, ohp.target, ohp.metode, ohp.temuan, ohp.catatan,
            $sqlMulai AS mulai,
            $sqlSelesai AS selesai,
            TIMESTAMPDIFF(MINUTE, $sqlMulai, $sqlSelesai) AS durasi_menit,
            $sqlLog AS keterangan_log,
            CASE 
                WHEN p.jenis_karantina = 'H' THEN kh.nama
                WHEN p.jenis_karantina = 'I' THEN ki.nama
                WHEN p.jenis_karantina = 'T' THEN kt.nama
                ELSE '-'
            END AS nama_komoditas
        ", false);

        $this->db->from('ptk p')
            ->join('ptk_surtug_header p1a', 'p.id = p1a.ptk_id', 'inner')
            ->join('officer_hasil_periksa ohp', 'ohp.id_surat_tugas = p1a.id', 'inner')
            ->join('ptk_komoditas pk', 'ohp.id_komoditas = pk.id', 'left')
            ->join('komoditas_hewan kh', "pk.komoditas_id = kh.id AND p.jenis_karantina = 'H'", 'left')
            ->join('komoditas_ikan ki',  "pk.komoditas_id = ki.id AND p.jenis_karantina = 'I'", 'left')
            ->join('komoditas_tumbuhan kt', "pk.komoditas_id = kt.id AND p.jenis_karantina = 'T'", 'left')
            ->join('master_pegawai mp', 'ohp.id_petugas = mp.id', 'left')
            ->join('master_upt mu', 'p.kode_satpel = mu.id', 'left');

        $this->applyManualFilter($f, true);
        
        return $this->db->order_by('ohp.tgl_periksa', 'DESC')
                        ->limit(10000)
                        ->get()
                        ->result_array();
    }

    public function getExportByFilter($f)
    {
        return $this->getFullData($f);
    }

    private function applyManualFilter($f, $hasKomoditasJoin = false)
    {
        $this->db->where([
            'p.is_verifikasi' => '1',
            'p.is_batal'      => '0',
            'p.deleted_at'    => '1970-01-01 08:00:00',
            'p1a.deleted_at'  => '1970-01-01 08:00:00',
        ]);
        if (!empty($f['upt']) && !in_array(strtolower($f['upt']), ['all', 'semua', 'undefined'])) {
            $field = (strlen($f['upt']) <= 4) ? "p.upt_id" : "p.kode_satpel";
            $this->db->where($field, $f['upt']);
        }
        if (!empty($f['karantina']) && !in_array(strtolower($f['karantina']), ['all', 'semua', ''])) {
            $this->db->where('p.jenis_karantina', substr(strtoupper($f['karantina']), 0, 1));
        }
        $lingkup = $f['lingkup'] ?? ($f['permohonan'] ?? '');
        if (!empty($lingkup) && !in_array(strtolower($lingkup), ['all', 'semua', 'undefined', ''])) {
            $this->db->where('p.jenis_permohonan', strtoupper($lingkup));
        }
        if (!empty($f['start_date'])) {
            $this->db->where('DATE(ohp.tgl_periksa) >=', $f['start_date']);
        }
        if (!empty($f['end_date'])) {
            $this->db->where('DATE(ohp.tgl_periksa) <=', $f['end_date']);
        }

        if (!empty($f['search'])) {
            $searchColumns = [
                'p.no_aju',
                'p.no_dok_permohonan',
                'p1a.nomor',
                'mu.nama',
                'mp.nama',
                'ohp.temuan',
                'pk.nama_umum_tercetak',
            ];
            if ($hasKomoditasJoin) {
                $searchColumns[] = 'kh.nama';  
                $searchColumns[] = 'ki.nama'; 
                $searchColumns[] = 'kt.nama'; 
            }

            $this->applyGlobalSearch($f['search'], $searchColumns);
        }
    }
}