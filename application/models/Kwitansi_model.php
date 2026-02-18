<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'core/BaseModelStrict.php';

class Kwitansi_model extends BaseModelStrict
{
    protected $endpoint = 'https://simponi.karantinaindonesia.go.id/epnbp/laporan/webmon';
    private $_cache_data = null;

    public function __construct()
    {
        parent::__construct();
    }

    public function getIds($filter, $limit, $offset)
    {
        $allData = $this->getAllDataFromSimponi($filter);
        if (empty($allData)) return [];
        if (!empty($filter['search'])) {
            $allData = $this->filterBySearch($allData, $filter['search']);
        }
        $allData = $this->sortData($allData, $filter['sort_by'] ?? 'tanggal', $filter['sort_order'] ?? 'DESC');
        $total = count($allData);
        $paginatedData = array_slice($allData, $offset, $limit);
        $this->session->set_userdata('kwitansi_temp_data', $paginatedData);
        $this->session->set_userdata('kwitansi_total', $total);
        return array_keys($paginatedData);
    }

    public function getByIds($ids)
    {
        $cachedData = $this->session->userdata('kwitansi_temp_data');
        if (!$cachedData) return [];

        return array_values($cachedData);
    }

    public function countAll($filter)
    {
        return (int) ($this->session->userdata('kwitansi_total') ?: 0);
    }

    public function getFullData($filter)
    {
        $allData = $this->getAllDataFromSimponi($filter);
        if (empty($allData)) return [];

        if (!empty($filter['search'])) {
            $allData = $this->filterBySearch($allData, $filter['search']);
        }

        return $this->sortData($allData, 'tanggal', 'DESC');
    }

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

        log_message('debug', 'KWITANSI PAYLOAD: ' . http_build_query($payload));

        $response = $this->curlPost($this->endpoint, http_build_query($payload));

        if (!$response || empty($response['status']) || !isset($response['data'])) {
            log_message('error', 'KWITANSI API Error: ' . json_encode($response));
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
                stripos($row['nama_wajib_bayar'] ?? '', $search) !== false ||
                stripos($row['kode_bill'] ?? '', $search) !== false ||
                stripos($row['ntpn'] ?? '', $search) !== false ||
                stripos($row['nama_upt'] ?? '', $search) !== false ||
                stripos($row['nama_satpel'] ?? '', $search) !== false
            );
        });
    }

    private function sortData($data, $sortBy, $sortOrder)
    {
        $columnMap = [
            'nomor'            => 'nomor',
            'tanggal'          => 'tanggal',
            'nama_wajib_bayar' => 'nama_wajib_bayar',
            'total_pnbp'       => 'total_pnbp',
            'date_setor'       => 'date_setor',
            'nama_upt'         => 'nama_upt',
        ];

        $column = $columnMap[$sortBy] ?? 'tanggal';
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

    private function curlPost($url, $payload)
    {
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
        
        if ($res === false) {
            log_message('error', 'KWITANSI CURL Error: ' . curl_error($ch));
        }
        
        curl_close($ch);
        return json_decode($res, true);
    }

    public function fetch($f)
    {
        $limit = isset($f['per_page']) ? (int)$f['per_page'] : 10;
        $page = isset($f['page']) ? (int)$f['page'] : 1;
        $offset = ($page - 1) * $limit;
        
        $ids = $this->getIds($f, $limit, $offset);
        return $this->getByIds($ids);
    }

    public function getAll($f)
    {
        return $this->getFullData($f);
    }
}