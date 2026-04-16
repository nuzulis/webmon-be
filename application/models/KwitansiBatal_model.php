<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'core/BaseModelStrict.php';

class KwitansiBatal_model extends BaseModelStrict
{
    private $endpoint = 'https://simponi.karantinaindonesia.go.id/epnbp/batal/kuitansi';
    protected $db_excel;
    private $_cache_data = null;

    public function __construct()
    {
        parent::__construct();
        $this->db_excel = $this->load->database('excel', TRUE);
    }


    public function getIds(array $f, int $limit, int $offset): array
    {
        $allData = $this->fetchFromApi($f);
        return array_keys(array_slice($allData, $offset, $limit));
    }

    public function getByIds($ids)
    {
        return $ids;
    }

    public function getAll($f)
    {
        return $this->fetchFromApi($f);
    }

    public function getFullData($f)
    {
        return $this->getAll($f);
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
                'upt'              => $r['nama_upt'] ?? '-',
                'satpel'           => $r['nama_satpel'] ?? '-',
                'pos_pelayanan'    => $r['nama_pospel'] ?? '-',
                'jenis_karantina'  => $this->mapKarantina($r['jenis_karantina'] ?? ''),
                'nomor'            => $r['nomor'] ?? '-',
                'tanggal'          => $r['tanggal'] ?? '-',
                'jenis_permohonan' => $this->mapPermohonan($r['jenis_permohonan'] ?? ''),
                'wajib_bayar'      => $r['nama_wajib_bayar'] ?? '-',
                'tipe_bayar'       => $r['tipe_bayar'] ?? '-',
                'total_pnbp'       => (float) ($r['total_pnbp'] ?? 0),
                'kode_bill'        => $r['kode_bill'] ?? '-',
                'ntpn'             => $r['ntpn'] ?? '-',
                'ntb'              => $r['ntb'] ?? '-',
                'created_at'       => $r['created_at'] ?? '-',
                'alasan_hapus'     => $r['alasan_hapus'] ?? 'Tidak disebutkan',
                'deleted_at'       => $r['deleted_at'] ?? '-',
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
}