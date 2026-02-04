<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'models/BaseModelStrict.php';

class Kwitansi_model extends BaseModelStrict
{
    protected $endpoint = 'https://simponi.karantinaindonesia.go.id/epnbp/laporan/webmon';
    private $_cache_data = null;

    private function getAllDataFromSimponi($f)
{
    if ($this->_cache_data !== null) return $this->_cache_data;

    $karInput = strtolower($f['karantina'] ?? '');
    if (empty($karInput) || $karInput === 'all') {
        $karantinaField = 'all';
    } else {
        $karantinaField = match(substr($karInput, -1)) {
            'h' => 'H',
            'i' => 'I',
            't' => 'T',
            default => 'all'
        };
    }

    $permInput = strtolower($f['permohonan'] ?? '');
    $permohonanField = in_array($permInput, ['ex', 'im', 'dk', 'dm']) ? $permInput : 'all';

    $uptField = (!empty($f['upt']) && $f['upt'] !== 'all') ? $f['upt'] : 'all';
    $berdasarkan = strtoupper($f['berdasarkan'] ?? 'S');
    $berdasarkan = substr($berdasarkan, 0, 1) ?: 'S';

    $payload = [
        'dFrom'           => $f['start_date'] ?? '',
        'dTo'             => $f['end_date'] ?? '',
        'jenisKarantina'  => $karantinaField,
        'jenisPermohonan' => $permohonanField,
        'berdasarkan'     => $berdasarkan,
        'upt'             => $uptField,
        'kodeSatpel'      => 'all',
    ];

    log_message('error', 'PAYLOAD TO SIMPONI: ' . http_build_query($payload));

    $response = $this->curlPost($this->endpoint, http_build_query($payload));

    if (!$response || empty($response['status']) || !isset($response['data'])) {
        $this->_cache_data = [];
        return [];
    }

    $this->_cache_data = $this->normalize($response['data']);
    return $this->_cache_data;
}
    public function countAll($f) {
        return count($this->getAllDataFromSimponi($f));
    }

    public function fetch($f) {
        $allData = $this->getAllDataFromSimponi($f);
        $limit = isset($f['per_page']) ? (int)$f['per_page'] : 10;
        $page = isset($f['page']) ? (int)$f['page'] : 1;
        $offset = ($page - 1) * $limit;
        return array_slice($allData, $offset, $limit);
    }

    public function getAll($f) {
        return $this->getAllDataFromSimponi($f);
    }

    public function getIds($f, $limit, $offset) { return []; }
    public function getByIds($ids) { return []; }

    private function normalize($rows) {
        if (!is_array($rows)) return [];
        $out = [];
        $seen = [];
        foreach ($rows as $r) {
            $uniqueKey = $r['nomor'] ?? null;
            if (!$uniqueKey || in_array($uniqueKey, $seen, true)) continue;
            $seen[] = $uniqueKey;

            $out[] = [
                'id'               => $r['id'] ?? '',
                'nama_upt'         => $r['nama_upt'] ?? '',
                'nama_satpel'      => trim(($r['nama_satpel'] ?? '') . ' ' . ($r['nama_pospel'] ?? '')),
                'jenis_karantina'  => $this->mapKarantina($r['jenis_karantina'] ?? ''),
                'nomor'            => $r['nomor'] ?? '',
                'tanggal'          => $r['tanggal'] ?? '',
                'jenis_permohonan' => $this->mapPermohonan($r['jenis_permohonan'] ?? ''),
                'nama_wajib_bayar' => $r['nama_wajib_bayar'] ?? '',
                'tipe_bayar'       => $r['tipe_bayar'] ?? '',
                'total_pnbp'       => (float) ($r['total_pnbp'] ?? 0),
                'kode_bill'        => $r['kode_bill'] ?? '',
                'ntpn'             => $r['ntpn'] ?? '',
                'ntb'              => $r['ntb'] ?? '',
                'date_bill'        => $r['date_bill'] ?? '',
                'date_setor'       => $r['date_setor'] ?? '',
                'bank'             => $r['bank'] ?? '',
            ];
        }
        return $out;
    }

    private function mapKarantina($v) {
        return match (strtoupper($v)) { 'H' => 'Hewan', 'I' => 'Ikan', 'T' => 'Tumbuhan', default => '-' };
    }

    private function mapPermohonan($v) {
        return match (strtoupper($v)) { 'EX' => 'Ekspor', 'IM' => 'Impor', 'DK' => 'Domestik Keluar', 'DM' => 'Domestik Masuk', default => '-' };
    }

    private function curlPost($url, $payload) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT        => 120,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/x-www-form-urlencoded',
                'Authorization: Basic bXJpZHdhbjpaPnV5JCx+NjR7KF42WDQm'
            ],
        ]);
        $res = curl_exec($ch);
        curl_close($ch);
        return json_decode($res, true);
    }
}