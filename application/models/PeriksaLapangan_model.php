<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'models/BaseModelStrict.php';

class PeriksaLapangan_model extends BaseModelStrict
{
    protected function getIdsQuery($f)
    {
        $this->db->select('p.id, MAX(ohp.tgl_periksa) as max_tgl') 
            ->from('ptk p')
            ->join('ptk_surtug_header p1a', 'p.id = p1a.ptk_id', 'inner')
            ->join('officer_hasil_periksa ohp', 'ohp.id_surat_tugas = p1a.id', 'inner');

        $this->db->where([
            'p.is_verifikasi' => '1',
            'p.is_batal'      => '0',
            'p.deleted_at'    => '1970-01-01 08:00:00',
            'p1a.deleted_at'  => '1970-01-01 08:00:00',
        ]);

        $this->applyFilter($f);
        
        $this->db->group_by('p.id');
        $this->db->order_by('max_tgl', 'DESC');
    }

    protected function countAllQuery($f)
    {
        $this->db->select('COUNT(DISTINCT p.id) AS total', false)
            ->from('ptk p')
            ->join('ptk_surtug_header p1a', 'p.id = p1a.ptk_id', 'inner')
            ->join('officer_hasil_periksa ohp', 'ohp.id_surat_tugas = p1a.id', 'inner');

        $this->db->where([
            'p.is_verifikasi' => '1',
            'p.is_batal'      => '0',
            'p.deleted_at'    => '1970-01-01 08:00:00',
            'p1a.deleted_at'  => '1970-01-01 08:00:00',
        ]);

        $this->applyFilter($f);
    }

    public function getByIds($ids)
    {
        if (empty($ids)) return [];

        $this->db->select("
            p.id, p.no_aju, p.no_dok_permohonan, p.tgl_dok_permohonan, p.jenis_karantina,
            mu.nama AS nama_upt, mu.nama_satpel,
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

        $query = $this->db->get();
        
        if (!$query) {
            $error = $this->db->error();
            log_message('error', 'Query getByIds Error: ' . $error['message']);
            return [];
        }

        return $query->result_array();
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

    if ($query === FALSE) {
        $db_error = $this->db->error();
        log_message('error', 'Database Error (detail): ' . $db_error['message']);
        return null;
    }

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
    
    public function getExportByFilter($f)
{
    $sqlMulai = "(SELECT MIN(time) FROM ptk_surtug_riwayat WHERE surtug_header_id = p1a.id AND status = 'mulai')";
    $sqlSelesai = "(SELECT MAX(time) FROM ptk_surtug_riwayat WHERE surtug_header_id = p1a.id AND status = 'selesai')";
    $sqlLog = "(SELECT keterangan FROM ptk_surtug_riwayat WHERE surtug_header_id = p1a.id ORDER BY time DESC LIMIT 1)";

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

    $this->db->where([
        'p.is_verifikasi' => '1',
        'p.is_batal'      => '0',
        'p.deleted_at'    => '1970-01-01 08:00:00',
        'p1a.deleted_at'  => '1970-01-01 08:00:00',
    ]);

    $this->applyFilter($f);

    return $this->db->order_by('ohp.tgl_periksa', 'DESC')
                    ->limit(10000)
                    ->get()
                    ->result_array();
}

            private function applyFilter($f)
        {
            if (!empty($f['karantina']) && !in_array(strtoupper($f['karantina']), ['ALL', 'SEMUA'])) {
                $this->db->where('p.jenis_karantina', strtoupper($f['karantina']));
            }

            if (!empty($f['lingkup']) && !in_array(strtoupper($f['lingkup']), ['ALL', 'SEMUA'])) {
                $this->db->where('p.jenis_permohonan', strtoupper($f['lingkup']));
            }
            if (!empty($f['upt']) && !in_array(strtolower($f['upt']), ['all', 'semua'])) {
                $this->db->where('p.kode_satpel', $f['upt']);
            }
            if (!empty($f['start_date'])) {
                $this->db->where('DATE(ohp.tgl_periksa) >=', $f['start_date']);
            }
            if (!empty($f['end_date'])) {
                $this->db->where('DATE(ohp.tgl_periksa) <=', $f['end_date']);
            }
        }
}