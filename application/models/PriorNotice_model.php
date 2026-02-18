<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'core/BaseModelStrict.php';

class PriorNotice_model extends BaseModelStrict
{
    private string $apiUrl = 'https://api3.karantinaindonesia.go.id/rest-prior/puskodal';
    private string $authHeader = 'Basic bXJpZHdhbjpaPnV5JCx+NjR7KF42WDQm';


    public function __construct()
    {
        parent::__construct();
    }

    public function fetch($f): array
    {
        $kar = match (strtolower($f['karantina'] ?? '')) {
            'kh'    => 'H',
            'ki'    => 'I',
            'kt'    => 'T',
            default => null
        };

        if (!$kar) {
            return ['success' => false, 'message' => 'Jenis karantina (kh/ki/kt) diperlukan'];
        }
        $cacheKey = 'prior_notice_' . md5(json_encode($f));
        if (!empty($_SESSION[$cacheKey]) && 
            (time() - ($_SESSION[$cacheKey]['timestamp'] ?? 0)) < 300) { 
            log_message('debug', 'PRIOR_NOTICE: Using cached data');
            return $_SESSION[$cacheKey]['data'];
        }

        $payload = [
            'dFrom' => $f['start_date'] ?? date('Y-m-d'),
            'dTo'   => $f['end_date'] ?? date('Y-m-d'),
            'kar'   => $kar,
            'par'   => 'all'
        ];

        if (!empty($f['upt']) && !in_array(strtolower($f['upt']), ['all', 'semua'])) {
            $payload['upt'] = $f['upt'];
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
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT        => 45
        ]);

        $response = curl_exec($ch);
        $errNo    = curl_errno($ch);
        $errMsg   = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errNo) {
            log_message('error', "PRIOR_NOTICE API Error: $errMsg");
            return [
                'success' => false,
                'message' => "Koneksi API Gagal: $errMsg"
            ];
        }

        if ($httpCode !== 200) {
            log_message('error', "PRIOR_NOTICE HTTP Error: $httpCode");
            return [
                'success' => false,
                'message' => "HTTP Error: $httpCode"
            ];
        }

        $json = json_decode($response, true);

        if (!isset($json['status']) || $json['status'] != 1) {
            log_message('warning', 'PRIOR_NOTICE: API returned error status');
            return [
                'success' => false,
                'message' => $json['message'] ?? 'Respon API Pusat menyatakan error atau data kosong.'
            ];
        }

        $result = [
            'success' => true,
            'data'    => $json['data'] ?? []
        ];
        $_SESSION[$cacheKey] = [
            'data' => $result,
            'timestamp' => time()
        ];

        return $result;
    }

    public function getIds($f, $limit, $offset)
    {
        return [];
    }

    public function getByIds($ids)
    {
        return [];
    }

    public function countAll($f)
    {
        return 0;
    }

    public function filterAndSort($data, $f)
    {
        if (empty($data)) return $data;
        if (!empty($f['search'])) {
            $search = strtolower($f['search']);
            $data = array_filter($data, function($row) use ($search) {
                $searchable = [
                    $row['docnbr'] ?? '',
                    $row['name'] ?? '',
                    $row['company'] ?? '',
                    $row['company_imp'] ?? '',
                    $row['neg_origin'] ?? '',
                    $row['komoditas'] ?? '',
                    $row['novoyage'] ?? '',
                ];
                
                foreach ($searchable as $field) {
                    if (stripos($field, $search) !== false) {
                        return true;
                    }
                }
                
                return false;
            });
        }
        if (!empty($f['sort_by'])) {
            $sortBy = $f['sort_by'];
            $sortOrder = strtoupper($f['sort_order'] ?? 'DESC');
            $columnMap = [
                'docnbr'      => 'docnbr',
                'doc_date'    => 'tgl_doc',
                'applicant'   => 'name',
                'exporter'    => 'company',
                'origin'      => 'neg_origin',
                'importer'    => 'company_imp',
                'commodity'   => 'komoditas',
                'voyage'      => 'novoyage',
                'eta'         => 'tgl_tiba',
            ];

            $sortKey = $columnMap[$sortBy] ?? $sortBy;

            usort($data, function($a, $b) use ($sortKey, $sortOrder) {
                $valA = $a[$sortKey] ?? '';
                $valB = $b[$sortKey] ?? '';
                if (in_array($sortKey, ['tgl_doc', 'tgl_tiba'])) {
                    $valA = strtotime($valA);
                    $valB = strtotime($valB);
                }

                if ($sortOrder === 'ASC') {
                    return $valA <=> $valB;
                } else {
                    return $valB <=> $valA;
                }
            });
        } else { 
            usort($data, function($a, $b) {
                $dateA = strtotime($a['tgl_doc'] ?? '');
                $dateB = strtotime($b['tgl_doc'] ?? '');
                return $dateB <=> $dateA;
            });
        }

        return array_values($data); 
    }
}