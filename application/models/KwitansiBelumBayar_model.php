<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'core/BaseModelStrict.php';

class KwitansiBelumBayar_model extends BaseModelStrict
{
    protected $endpoint = 'https://simponi.karantinaindonesia.go.id/epnbp/kuitansi/unpaid';
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
        $this->session->set_userdata('kwitansi_belum_bayar_temp_data', $paginatedData);
        $this->session->set_userdata('kwitansi_belum_bayar_total', $total);
        return array_keys($paginatedData);
    }

    public function getByIds($ids)
    {
        $cachedData = $this->session->userdata('kwitansi_belum_bayar_temp_data');
        if (!$cachedData) return [];

        return array_values($cachedData);
    }

    public function countAll($filter)
    {
        return (int) ($this->session->userdata('kwitansi_belum_bayar_total') ?: 0);
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
        $karantinaField = match($karInput) {
            'kh' => 'H',
            'kt' => 'T',
            'ki' => 'I',
            default => ''
        };

        $uptField = ($f['upt'] !== 'all' && !empty($f['upt'])) ? $f['upt'] : '';

        $permInput = strtolower($f['permohonan'] ?? '');
        $permohonanField = in_array($permInput, ['ex', 'im', 'dk', 'dm']) ? $permInput : '';

        $payload = [
            'dFrom'       => $f['start_date'] ?? '',
            'dTo'         => $f['end_date'] ?? '',
            'kar'         => $karantinaField,
            'upt'         => $uptField,
            'permohonan'  => $permohonanField,
            'berdasarkan' => $f['berdasarkan'] ?? '', 
        ];

        log_message('debug', 'KWITANSI BELUM BAYAR PAYLOAD: ' . http_build_query($payload));

        $response = $this->curlPost($this->endpoint, http_build_query($payload));

        if (!$response || empty($response['status']) || !isset($response['data'])) {
            log_message('error', 'KWITANSI BELUM BAYAR API Error: ' . json_encode($response));
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
                stripos($row['no_aju'] ?? '', $search) !== false ||
                stripos($row['nama_wajib_bayar'] ?? '', $search) !== false ||
                stripos($row['kode_bill'] ?? '', $search) !== false ||
                stripos($row['nama_upt'] ?? '', $search) !== false ||
                stripos($row['nama_satpel'] ?? '', $search) !== false ||
                stripos($row['nama_pospel'] ?? '', $search) !== false
            );
        });
    }

    private function sortData($data, $sortBy, $sortOrder)
    {
        $columnMap = [
            'nomor'            => 'nomor',
            'no_aju'           => 'no_aju',
            'tanggal'          => 'tanggal',
            'nama_wajib_bayar' => 'nama_wajib_bayar',
            'total_pnbp'       => 'total_pnbp',
            'expired_date'     => 'expired_date',
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
        
        usort($rows, function ($a, $b) {
            return strcmp($a['ptk_id'] ?? '', $b['ptk_id'] ?? '');
        });

        $processed_ids = [];
        $out = [];

        foreach ($rows as $item) {
            $ptk_id = $item['ptk_id'] ?? '';
            if ($ptk_id && in_array($ptk_id, $processed_ids)) {
                continue;
            }
            $processed_ids[] = $ptk_id;

            $out[] = [
                'id'               => $item['id'] ?? '',
                'nama_upt'         => $item['nama_upt'] ?? '',
                'nama_satpel'      => $item['nama_satpel'] ?? '',
                'nama_pospel'      => $item['nama_pospel'] ?? '',
                'nomor'            => $item['nomor'] ?? '',
                'no_aju'           => $item['ptk_id'] ?? '',
                'tanggal'          => $item['tanggal'] ?? '',
                'nama_wajib_bayar' => $item['nama_wajib_bayar'] ?? '',
                'total_pnbp'       => (float) ($item['total_pnbp'] ?? 0),
                'kode_bill'        => $item['kode_bill'] ?? '',
                'expired_date'     => $item['tgl_exp_billing'] ?? ($item['date_bill_exp'] ?? '-'), 
                'jenis_karantina'  => $this->mapKarantina($item['jenis_karantina'] ?? ''),
                'jenis_permohonan' => $this->mapPermohonan($item['jenis_permohonan'] ?? ''),
                'tipe_bayar'       => $item['tipe_bayar'] ?? '',
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
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/x-www-form-urlencoded',
                'Authorization: Basic bXJpZHdhbjpaPnV5JCx+NjR7KF42WDQm'
            ],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT        => 60 
        ]);
        
        $res = curl_exec($ch);
        
        if ($res === false) {
            log_message('error', 'KWITANSI BELUM BAYAR CURL Error: ' . curl_error($ch));
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
}