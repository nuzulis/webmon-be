<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'models/BaseModelStrict.php';

class Monitoring_model extends BaseModelStrict 
{
    public function getIds($f, $limit, $offset) {
    // Tahap 1: Ambil ID dari tabel utama PTK saja dulu
    // Tidak perlu join fisik di sini agar scanning cepat
    $this->db->select('p.id')
        ->from('ptk p')
        ->where([
            'p.is_verifikasi' => '1',
            'p.is_batal'      => '0'
        ]);

    // Masukkan filter UPT, Karantina, Permohonan
    $this->applyCommonFilter($f, 'p');

    // Filter tanggal di tabel ptk langsung (biasanya ada index di tgl_dok_permohonan)
    // Jika kolomnya tgl_dok_permohonan, gunakan itu. 
    // Jika terpaksa harus tgl_periksa, gunakan JOIN p1b tapi tanpa GROUP BY dulu.
    if (!empty($f['start_date']) && !empty($f['end_date'])) {
        $this->db->where('p.tgl_dok_permohonan >=', $f['start_date']);
        $this->db->where('p.tgl_dok_permohonan <=', $f['end_date']);
    }

    $this->db->order_by('p.id', 'DESC') // Urutkan berdasarkan ID terbaru (paling cepat)
             ->limit($limit, $offset);

    $res = $this->db->get()->result_array();
    return array_column($res, 'id');
}
    

public function getByIds($ids)
{
    if (empty($ids)) return [];

    $quotedIds = "'" . implode("','", $ids) . "'";
    $karInput = strtolower($this->input->get('karantina', true));
    $map = ['h' => 'kh', 'i' => 'ki', 't' => 'kt'];
    $kar = $map[$karInput] ?? 'kh'; 

    $this->db->select("
        p.id, p.no_aju, p.no_dok_permohonan AS no_dok, p.tgl_dok_permohonan,
        p.jenis_karantina, p.jenis_permohonan, p.nama_pengirim, p.nama_penerima,
        mu.nama AS upt_raw, mu.nama_satpel,
        p1b.waktu_periksa AS tgl_periksa,
        p8.tanggal_lepas,
        k.komoditas, k.nama_umum_tercetak, k.p1, k.p2, k.p3, k.p4, k.p5, k.p6, k.p7, k.p8, k.satuan,
        CASE
            WHEN s8.p8 IS NOT NULL THEN 'Pelepasan'
            WHEN s8.p5 IS NOT NULL THEN 'Penahanan'
            WHEN s8.p6 IS NOT NULL THEN 'Penolakan'
            WHEN s8.p7 IS NOT NULL THEN 'Pemusnahan'
        END AS status
    ", false);

    $this->db->from('ptk p')
        ->join('pn_fisik_kesehatan p1b', 'p.id = p1b.ptk_id', 'left') 
        ->join('master_upt mu', 'p.kode_satpel = mu.id', 'inner')
        ->join('status8p s8', 'p.id = s8.id', 'left');

    $tabel_kom = "komoditas_" . ($kar == 'kh' ? 'hewan' : ($kar == 'ki' ? 'ikan' : 'tumbuhan'));
    
    // Subquery untuk komoditas dan volume detail
    $this->db->join("(
        SELECT 
            pkom.ptk_id,
            GROUP_CONCAT(kom.nama SEPARATOR '\n') AS komoditas,
            GROUP_CONCAT(pkom.nama_umum_tercetak SEPARATOR '\n') AS nama_umum_tercetak,
            GROUP_CONCAT(pkom.volumeP1 SEPARATOR '\n') AS p1,
            GROUP_CONCAT(pkom.volumeP2 SEPARATOR '\n') AS p2,
            GROUP_CONCAT(pkom.volumeP3 SEPARATOR '\n') AS p3,
            GROUP_CONCAT(pkom.volumeP4 SEPARATOR '\n') AS p4,
            GROUP_CONCAT(pkom.volumeP5 SEPARATOR '\n') AS p5,
            GROUP_CONCAT(pkom.volumeP6 SEPARATOR '\n') AS p6,
            GROUP_CONCAT(pkom.volumeP7 SEPARATOR '\n') AS p7,
            GROUP_CONCAT(pkom.volumeP8 SEPARATOR '\n') AS p8,
            GROUP_CONCAT(ms.nama SEPARATOR '\n') AS satuan
        FROM ptk_komoditas pkom
        JOIN $tabel_kom kom ON pkom.komoditas_id = kom.id
        LEFT JOIN master_satuan ms ON pkom.satuan_lain_id = ms.id
        WHERE pkom.deleted_at = '1970-01-01 08:00:00'
        AND pkom.ptk_id IN ($quotedIds)
        GROUP BY pkom.ptk_id
    ) k", 'p.id = k.ptk_id', 'left');

    $this->db->join("(
        SELECT ptk_id, MIN(tanggal) AS tanggal_lepas
        FROM pn_pelepasan_{$kar}
        WHERE ptk_id IN ($quotedIds)
        GROUP BY ptk_id
    ) p8", 'p.id = p8.ptk_id', 'left');

    $this->db->where_in('p.id', $ids);
    $this->db->order_by('p1b.waktu_periksa', 'DESC');

    $results = $this->db->get()->result_array();

    foreach ($results as &$row) {
        // Logika SLA
        $row['sla'] = '-';
        if (!empty($row['tanggal_lepas']) && !empty($row['tgl_periksa'])) {
            $start = new DateTime($row['tgl_periksa']);
            $end   = new DateTime($row['tanggal_lepas']);
            $diff  = $start->diff($end);
            $row['sla'] = "{$diff->days} hari {$diff->h} jam {$diff->i} menit";
        }
        
        // Pembersihan Nama UPT
        $row['upt_full'] = str_replace(
            ['Balai Besar Karantina Hewan, Ikan, dan Tumbuhan', 'Balai Karantina Hewan, Ikan, dan Tumbuhan'],
            ['BBKHIT', 'BKHIT'],
            $row['upt_raw'] ?? ''
        ) . ' - ' . ($row['nama_satpel'] ?? '');
    }

    return $results;
}


public function countAll($f)
{
    $this->db->from('ptk p')
             ->where(['p.is_verifikasi' => '1', 'p.is_batal' => '0']);

    // Gunakan filter yang sama agar jumlah total sesuai dengan pencarian
    $this->applyCommonFilter($f, 'p');

    if (!empty($f['start_date']) && !empty($f['end_date'])) {
        $this->db->where('p.tgl_dok_permohonan >=', $f['start_date']);
        $this->db->where('p.tgl_dok_permohonan <=', $f['end_date']);
    }

    return $this->db->count_all_results();
}
}