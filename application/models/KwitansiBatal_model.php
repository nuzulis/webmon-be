<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'core/BaseModelStrict.php';

class KwitansiBatal_model extends BaseModelStrict
{
    private $endpoint = 'https://simponi.karantinaindonesia.go.id/epnbp/batal/kuitansi';
    private $_cache_data = null;

    public function __construct()
    {
        parent::__construct();
    }


    public function getIds($filter, $limit, $offset)
    {
        $allData = $this->fetchFromApi($filter);
        if (empty($allData)) return [];
        if (!empty($filter['search'])) {
            $allData = $this->filterBySearch($allData, $filter['search']);
        }
        $allData = $this->sortData($allData, $filter['sort_by'] ?? 'deleted_at', $filter['sort_order'] ?? 'DESC');
        $total = count($allData);
        $paginatedData = array_slice($allData, $offset, $limit);
        $this->session->set_userdata('kwitansi_batal_temp_data', $paginatedData);
        $this->session->set_userdata('kwitansi_batal_total', $total);
        return array_keys($paginatedData);
    }

    public function getByIds($ids)
    {
        $cachedData = $this->session->userdata('kwitansi_batal_temp_data');
        if (!$cachedData) return [];

        return array_values($cachedData);
    }

    public function countAll($filter)
    {
        return (int) ($this->session->userdata('kwitansi_batal_total') ?: 0);
    }

    public function getFullData($filter)
    {
        $allData = $this->fetchFromApi($filter);
        if (empty($allData)) return [];

        if (!empty($filter['search'])) {
            $allData = $this->filterBySearch($allData, $filter['search']);
        }

        return $this->sortData($allData, 'deleted_at', 'DESC');
    }
    private function fetchFromApi($f)
    {
        if ($this->_cache_data !== null) return $this->_cache_data;

        $karMap = [
            'kh' => 'H', 'ki' => 'I', 'kt' => 'T',
        ];

        $uptInput = $f['upt'] ?? 'all';
        $uptField = '';
        if ($uptInput !== 'all' && !empty($uptInput)) {
            $uptField = (strlen($uptInput) > 2) ? substr($uptInput, 0, 2) : $uptInput;
        }

        $payload = [
            'dFrom'       => $f['start_date'] ?? '',
            'dTo'         => $f['end_date'] ?? '',
            'kar'         => $karMap[strtolower($f['karantina'] ?? '')] ?? '',
            'upt'         => $uptField,
            'permohonan'  => $f['permohonan'] ?? '',
            'berdasarkan' => $f['berdasarkan'] ?? '',
        ];

        log_message('debug', 'KWITANSI BATAL PAYLOAD: ' . json_encode($payload));

        $response = $this->curlPostJson($this->endpoint, json_encode($payload));

        if (!$response || empty($response['status']) || empty($response['data'])) {
            log_message('error', 'KWITANSI BATAL API Error: ' . json_encode($response));
            $this->_cache_data = [];
            return [];
        }

        $this->_cache_data = $this->normalize($response['data']);
        return $this->_cache_data;
    }
    private function filterBySearch($data, $search)
    {
        return array_filter($data, function($row) use ($search) {
            $search = strtolower($search);
            return (
                stripos($row['nomor'] ?? '', $search) !== false ||
                stripos($row['wajib_bayar'] ?? '', $search) !== false ||
                stripos($row['kode_bill'] ?? '', $search) !== false ||
                stripos($row['ntpn'] ?? '', $search) !== false ||
                stripos($row['upt'] ?? '', $search) !== false ||
                stripos($row['satpel'] ?? '', $search) !== false ||
                stripos($row['alasan_hapus'] ?? '', $search) !== false
            );
        });
    }

    private function sortData($data, $sortBy, $sortOrder)
    {
        $columnMap = [
            'nomor'        => 'nomor',
            'tanggal'      => 'tanggal',
            'wajib_bayar'  => 'wajib_bayar',
            'total_pnbp'   => 'total_pnbp',
            'deleted_at'   => 'deleted_at',
            'upt'          => 'upt',
        ];

        $column = $columnMap[$sortBy] ?? 'deleted_at';
        $order = strtoupper($sortOrder) === 'ASC' ? SORT_ASC : SORT_DESC;
        $sortValues = array_column($data, $column);
        array_multisort($sortValues, $order, SORT_NATURAL | SORT_FLAG_CASE, $data);

        return $data;
    }
    private function normalize($rows)
    {
        if (!is_array($rows)) return [];

        $out = [];
        $seen = [];

        foreach ($rows as $r) {
            $uid = $r['kode_bill'] ?? ($r['ptk_id'] ?? null);
            if (!$uid || in_array($uid, $seen, true)) continue;
            $seen[] = $uid;

            $out[] = [
                'upt'              => $r['nama_upt'] ?? '',
                'satpel'           => trim(($r['nama_satpel'] ?? '') . ' - ' . ($r['nama_pospel'] ?? '')),
                'jenis_karantina'  => $this->mapKarantina($r['jenis_karantina'] ?? ''),
                'nomor'            => $r['nomor'] ?? '',
                'tanggal'          => $r['tanggal'] ?? '',
                'jenis_permohonan' => $this->mapPermohonan($r['jenis_permohonan'] ?? ''),
                'wajib_bayar'      => $r['nama_wajib_bayar'] ?? '',
                'tipe_bayar'       => $r['tipe_bayar'] ?? '',
                'total_pnbp'       => (float) ($r['total_pnbp'] ?? 0),
                'kode_bill'        => $r['kode_bill'] ?? '',
                'ntpn'             => $r['ntpn'] ?? '',
                'ntb'              => $r['ntb'] ?? '',
                'created_at'       => $r['created_at'] ?? '',
                'alasan_hapus'     => $r['alasan_hapus'] ?? 'Tidak disebutkan',
                'deleted_at'       => $r['deleted_at'] ?? '',
            ];
        }

        return $out;
    }

    private function mapKarantina($v)
    {
        return match (strtoupper($v)) {
            'H' => 'Hewan',
            'I' => 'Ikan',
            'T' => 'Tumbuhan',
            default => '-'
        };
    }

    private function mapPermohonan($v)
    {
        return match (strtoupper($v)) {
            'EX' => 'Ekspor',
            'IM' => 'Impor',
            'DK' => 'Domestik Keluar',
            'DM' => 'Domestik Masuk',
            default => '-'
        };
    }

    private function curlPostJson($url, $jsonPayload)
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $jsonPayload,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Basic bXJpZHdhbjpaPnV5JCx+NjR7KF42WDQm'
            ],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT        => 30
        ]);

        $res = curl_exec($ch);
        
        if ($res === false) {
            log_message('error', 'KWITANSI BATAL CURL Error: ' . curl_error($ch));
        }
        
        if (is_resource($ch) || (is_object($ch) && $ch instanceof \CurlHandle)) {
            curl_close($ch);
        }
        
        return json_decode($res, true);
    }
    public function fetch($f)
    {
        return $this->getFullData($f);
    }
}