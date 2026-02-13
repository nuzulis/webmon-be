<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Dashboard_model extends CI_Model
{
    public function __construct()
    {
        parent::__construct();
    }

        private function dbBarantin()
    {

        return $this->db;
    }

    private function yearRange(int $year): array
    {
        return [
            'start' => "$year-01-01 00:00:00",
            'end'   => ($year + 1) . "-01-01 00:00:00",
        ];
    }

    private function basePtkFilter($db, array $filter)
    {
        $db->where('p.is_verifikasi', 1);
        $db->where('p.is_batal', 0);
        $db->where('p.deleted_at', '1970-01-01 08:00:00');

        if (!empty($filter['upt_id']) && strtoupper($filter['upt_id']) !== 'ALL') {
            $uptPrefix = substr((string)$filter['upt_id'], 0, 2);
            $db->like('p.kode_satpel', $uptPrefix, 'after');
        }
    }

   public function get_freq_3p(array $filter): array
{
    $db = $this->dbBarantin();
    $currentYear = (int)($filter['year'] ?? date('Y'));
    $rng = $this->yearRange($currentYear);
    
    $uptFilter = "";
    $binds = [$rng['start'], $rng['end']];
    if (!empty($filter['upt_id']) && strtoupper($filter['upt_id']) !== 'ALL') {
        $uptFilter = " AND p.kode_satpel LIKE ? ";
        $binds[] = substr((string)$filter['upt_id'], 0, 2) . '%';
    }

    $sql = "
        SELECT 'penahanan' as tipe, COUNT(pn.id) as total
        FROM pn_penahanan pn
        JOIN ptk p ON pn.ptk_id = p.id
        WHERE p.is_verifikasi = 1 AND p.is_batal = 0 
          AND p.deleted_at = '1970-01-01 08:00:00'
          AND pn.deleted_at = '1970-01-01 08:00:00'
          AND p.created_at >= ? AND p.created_at < ? $uptFilter
        
        UNION ALL

        SELECT 'penolakan' as tipe, COUNT(po.id) as total
        FROM pn_penolakan po
        JOIN ptk p ON po.ptk_id = p.id
        WHERE p.is_verifikasi = 1 AND p.is_batal = 0 
          AND p.deleted_at = '1970-01-01 08:00:00'
          AND po.deleted_at = '1970-01-01 08:00:00'
          AND p.created_at >= ? AND p.created_at < ? $uptFilter

        UNION ALL

        SELECT 'pemusnahan' as tipe, COUNT(pm.id) as total
        FROM pn_pemusnahan pm
        JOIN ptk p ON pm.ptk_id = p.id
        WHERE p.is_verifikasi = 1 AND p.is_batal = 0 
          AND p.deleted_at = '1970-01-01 08:00:00'
          AND pm.deleted_at = '1970-01-01 08:00:00'
          AND p.created_at >= ? AND p.created_at < ? $uptFilter
    ";

    $allBinds = array_merge($binds, $binds, $binds);
    $resultRaw = $db->query($sql, $allBinds)->result_array();
    $data = ['penahanan' => 0, 'penolakan' => 0, 'pemusnahan' => 0];
    foreach ($resultRaw as $row) {
        $data[$row['tipe']] = (int)$row['total'];
    }

    return [
        ['label' => 'Penahanan',  'value' => $data['penahanan']],
        ['label' => 'Penolakan',  'value' => $data['penolakan']],
        ['label' => 'Pemusnahan', 'value' => $data['pemusnahan']],
    ];
}

    public function get_freq_permohonan(array $filter): array
{
    $db = $this->dbBarantin();
    $year = (int)($filter['year'] ?? date('Y'));
    $startDate = "$year-01-01 00:00:00";
    $endDate   = "$year-12-31 23:59:59";

    $db->select('p.jenis_permohonan, COUNT(*) AS frek');
    $db->from('ptk p');
    $db->where('p.is_batal', 0);
    $db->where("p.tgl_dok_permohonan BETWEEN '$startDate' AND '$endDate'");

    if (!empty($filter['upt_id']) && strtoupper($filter['upt_id']) !== 'ALL') {
        $db->like('p.kode_satpel', substr($filter['upt_id'], 0, 2), 'after');
    }

    $db->group_by('p.jenis_permohonan');
    
    $raw = $db->get()->result_array();

    $map = [
        'DM' => 'Domestik Masuk', 'DK' => 'Domestik Keluar',
        'EX' => 'Ekspor', 'IM' => 'Impor',
        'RE' => 'Reekspor', 'ST' => 'Serah Terima'
    ];

    $result = [];
    foreach ($raw as $r) {
        $result[] = [
            'name' => $map[$r['jenis_permohonan']] ?? $r['jenis_permohonan'],
            'y'    => (float)$r['frek']
        ];
    }
    return $result;
}

    public function get_sla_combined(string $jenis, array $filter): array
{
    $db = $this->dbBarantin();
    $rng = $this->yearRange((int)$filter['year']);
    $uptId = !empty($filter['upt_id']) && strtoupper($filter['upt_id']) !== 'ALL' ? substr($filter['upt_id'], 0, 2) : null;
    
    $maps = ['kt'=>'pn_pelepasan_kt', 'kh'=>'pn_pelepasan_kh', 'ki'=>'pn_pelepasan_ki'];
    $queries = [];
    $bindings = [];

    foreach ($maps as $kar => $tbl) {
        $sql = "
            SELECT '$kar' as tipe, MONTH(p1.waktu_periksa) as bulan_idx, 
                   AVG(TIMESTAMPDIFF(HOUR, p1.waktu_periksa, p8.tanggal)) as sla_val
            FROM ptk p
            JOIN pn_fisik_kesehatan p1 ON p.id = p1.ptk_id
            JOIN $tbl p8 ON p.id = p8.ptk_id
            WHERE p.jenis_permohonan = ? 
              AND p.is_verifikasi = 1 AND p.is_batal = 0 
              AND p.deleted_at = '1970-01-01 08:00:00'
              AND p1.waktu_periksa >= ? AND p1.waktu_periksa < ?
              AND p8.tanggal > p1.waktu_periksa
              " . ($uptId ? " AND p.kode_satpel LIKE ?" : "") . "
            GROUP BY MONTH(p1.waktu_periksa)
        ";

        $bindings[] = $jenis;
        $bindings[] = $rng['start'];
        $bindings[] = $rng['end'];
        if ($uptId) $bindings[] = $uptId . '%';

        $queries[] = $sql;
    }

    $finalSql = implode(" UNION ALL ", $queries);
    $resultRaw = $db->query($finalSql, $bindings)->result_array();
    

        $output = ['kt' => array_fill(1, 12, 0), 'kh' => array_fill(1, 12, 0), 'ki' => array_fill(1, 12, 0)];
        foreach ($resultRaw as $row) {
            $output[$row['tipe']][(int)$row['bulan_idx']] = round((float)$row['sla_val'], 2);
        }

        $final = [];
        foreach ($output as $kar => $vals) {
            foreach ($vals as $m => $v) {
                $final[$kar][] = ['bulan' => date('M', mktime(0,0,0,$m,1)), 'sla' => $v];
            }
        }
        return $final;
    }


    public function get_top_komoditi(string $jenis, array $filter): array
    {
        $db = $this->dbBarantin();
        if (!$db) return [];

        $rng = $this->yearRange((int)$filter['year']);
        $kar = strtolower($filter['karantina'] ?? 'kt');
        $pelepasan = ['kt'=>'pn_pelepasan_kt','kh'=>'pn_pelepasan_kh','ki'=>'pn_pelepasan_ki'][$kar];
        $komoditas = ['kt'=>'komoditas_tumbuhan','kh'=>'komoditas_hewan','ki'=>'komoditas_ikan'][$kar];

        $db->select('p.id, mn.nama as negara_nama');
        $db->from('ptk p');
        $db->join("$pelepasan p8", 'p.id = p8.ptk_id');
        
        $joinCol = ($jenis === 'EX') ? 'p.negara_tujuan_id' : 'p.negara_asal_id';
        $db->join('master_negara mn', "$joinCol = mn.id", 'left');

        $this->basePtkFilter($db, $filter);
        $db->where('p.jenis_permohonan', $jenis);
        $db->where('p8.created_at >=', $rng['start']);
        $db->where('p8.created_at <',  $rng['end']);
        $subquery = $db->get_compiled_select();

        $sql = "SELECT COUNT(pk.ptk_id) as frekuensi, k.nama as komoditi, t.negara_nama as negara
                FROM ptk_komoditas pk
                JOIN ($subquery) t ON pk.ptk_id = t.id
                JOIN $komoditas k ON pk.komoditas_id = k.id
                GROUP BY k.nama, t.negara_nama
                ORDER BY frekuensi DESC LIMIT " . (int)($filter['limit'] ?? 5);

        return $db->query($sql)->result_array();
    }

     public function get_pnbp($filter) 
{
    $db = $this->dbBarantin();
    if (!$db) return [];
    $uptId = $filter['upt_id'] ?? 'ALL';
    $kdupt = ($uptId == '1000' || strtoupper($uptId) === 'ALL') ? 'ALL' : (string)$uptId;

    $year  = (int)($filter['year'] ?? date('Y'));
    $isMonthly = (isset($filter['jns']) && $filter['jns'] === 'M');
    $month = $isMonthly ? (int)($filter['month'] ?? date('m')) : 0;

    $query = $db->query("CALL dashboard.GetPNBP(?, ?, ?)", [
        $year, 
        $month, 
        $kdupt
    ]);

    if ($query) {
        $result = $query->result_array();
        if (method_exists($db->conn_id, 'next_result')) {
            $db->conn_id->next_result();
        }
        return $result;
    }
    return [];
}

    public function get_potensi_simponi($uptId, $year, $month)
    {
        $kdupt = ($uptId == '1000' || strtoupper($uptId) === 'ALL' || empty($uptId)) ? '' : $uptId;
        $bln = str_pad($month, 2, "0", STR_PAD_LEFT);
        
        $url = "https://simponi.karantinaindonesia.go.id/epnbp/laporan";
        $strdata = 'thn='.$year.'&bln='.$bln.'&upt='.$kdupt;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $strdata);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded',
            'Authorization: Basic bXJpZHdhbjpaPnV5JCx+NjR7KF42WDQm'
        ]);
        
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60); 
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $response = curl_exec($ch);
        $err_no = curl_errno($ch);
        $err_msg = curl_error($ch);
        curl_close($ch);

        if ($err_no) {
            return [
                "success" => false,
                "status" => false, 
                "message" => "Simponi Timeout: Jaringan sibuk atau tidak merespon."
            ];
        }

        $data = json_decode($response, true);
        
        if (isset($data['status']) && $data['status']) {
            return [
                "success" => true,
                "status" => true,
                "data" => isset($data['data'][0]) ? $data['data'][0] : $data['data']
            ];
        }

        return [
            "success" => false,
            "status" => false,
            "message" => $data['message'] ?? "Data tidak ditemukan"
        ];
    }
}