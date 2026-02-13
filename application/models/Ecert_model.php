<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'core/BaseModelStrict.php';

class Ecert_model extends BaseModelStrict
{
    private string $apiUrl = 'https://api3.karantinaindonesia.go.id/ecert/certin';
    private string $authHeader = 'Basic bXJpZHdhbjpaPnV5JCx+NjR7KF42WDQm';

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
        $allData = $this->sortData($allData, $filter['sort_by'] ?? 'tgl_cert', $filter['sort_order'] ?? 'DESC');
        $total = count($allData);
        $paginatedData = array_slice($allData, $offset, $limit);
        $this->session->set_userdata('ecert_temp_data', $paginatedData);
        $this->session->set_userdata('ecert_total', $total);
        return array_keys($paginatedData);
    }

    public function getByIds($ids)
    {
        $cachedData = $this->session->userdata('ecert_temp_data');
        if (!$cachedData) return [];

        return array_values($cachedData);
    }

    public function countAll($filter)
    {
        return (int) ($this->session->userdata('ecert_total') ?: 0);
    }

    public function getFullData($filter)
    {
        $allData = $this->fetchFromApi($filter);
        if (empty($allData)) return [];

        if (!empty($filter['search'])) {
            $allData = $this->filterBySearch($allData, $filter['search']);
        }

        return $this->sortData($allData, 'tgl_cert', 'DESC');
    }

    private function fetchFromApi($filter)
    {
        if (empty($filter['karantina']) || empty($filter['start_date']) || empty($filter['end_date'])) {
            return [];
        }

        $payload = [
            'kar'    => $filter['karantina'],
            'dstart' => $filter['start_date'],
            'dend'   => $filter['end_date'],
            'negara' => $filter['negara'] ?? ''
        ];

        if (!empty($filter['upt']) && strtolower($filter['upt']) !== 'all' && strlen($filter['upt']) <= 2) {
            $payload['upt'] = $filter['upt'];
        }

        $ch = curl_init($this->apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($payload),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/x-www-form-urlencoded',
                'Authorization: ' . $this->authHeader
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_TIMEOUT        => 30
        ]);

        $response = curl_exec($ch);

        if ($response === false) {
            log_message('error', 'E-Cert API Error: ' . curl_error($ch));
            curl_close($ch);
            return [];
        }

        curl_close($ch);
        $response = preg_replace('/^\[\s*,/', '[', trim($response));

        $data = json_decode($response, true);

        if (!is_array($data)) {
            log_message('error', 'E-Cert JSON Decode Error');
            return [];
        }

        return $data;
    }

    private function filterBySearch($data, $search)
    {
        return array_filter($data, function($row) use ($search) {
            $search = strtolower($search);
            return (
                stripos($row['no_cert'] ?? '', $search) !== false ||
                stripos($row['komo_eng'] ?? '', $search) !== false ||
                stripos($row['neg_asal'] ?? '', $search) !== false ||
                stripos($row['tujuan'] ?? '', $search) !== false ||
                stripos($row['port_tujuan'] ?? '', $search) !== false
            );
        });
    }

    private function sortData($data, $sortBy, $sortOrder)
    {
        $columnMap = [
            'no_cert'     => 'no_cert',
            'tgl_cert'    => 'tgl_cert',
            'komoditas'   => 'komo_eng',
            'negara_asal' => 'neg_asal',
            'tujuan'      => 'tujuan',
        ];

        $column = $columnMap[$sortBy] ?? 'tgl_cert';
        $order = strtoupper($sortOrder) === 'ASC' ? SORT_ASC : SORT_DESC;
        $sortValues = array_column($data, $column);
        array_multisort($sortValues, $order, SORT_NATURAL | SORT_FLAG_CASE, $data);

        return $data;
    }
}