<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'models/BaseModelStrict.php';

class Kwitansi_model extends BaseModelStrict
{
    // Gunakan konstan atau ambil dari config untuk URL API
    protected $endpoint = 'https://simponi.karantinaindonesia.go.id/epnbp/laporan/webmon';

    public function fetch($f)
    {
        $karMap = [
            'kh' => 'H',
            'ki' => 'I',
            'kt' => 'T',
        ];

        $karantina = $karMap[strtolower($f['karantina'] ?? '')] ?? 'all';

        $permMap = ['ex', 'im', 'dk', 'dm'];
        $permohonan = in_array(strtolower($f['permohonan'] ?? ''), $permMap, true)
            ? strtolower($f['permohonan'])
            : 'all';

        // Pastikan UPT ID dikonversi ke format yang dikenali Simponi (biasanya string atau 'all')
        $uptId = $f['upt'] ?? 'all';

        $payload = http_build_query([
            'dFrom'           => $f['start_date'],
            'dTo'             => $f['end_date'],
            'jenisKarantina'  => $karantina,
            'jenisPermohonan' => $permohonan,
            'berdasarkan'     => $f['berdasarkan'] ?? 'tanggal_setor',
            'upt'             => $uptId,
            'kodeSatpel'      => 'all',
        ]);

        $response = $this->curlPost($this->endpoint, $payload);

        if (!$response || empty($response['status']) || empty($response['data'])) {
            return [];
        }

        return $this->normalize($response['data']);
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
    private function normalize($rows)
    {
        if (!is_array($rows)) return [];

        $seen = [];
        $out  = [];

        // Sorting berdasarkan nomor kwitansi agar urut seperti di native
        usort($rows, function($a, $b) {
            return strcmp($a['nomor'] ?? '', $b['nomor'] ?? '');
        });

        foreach ($rows as $r) {
            $uniqueKey = $r['nomor'] ?? null; // Native menggunakan field 'nomor' (No Kwitansi) untuk dedup
            if (!$uniqueKey || in_array($uniqueKey, $seen, true)) continue;
            $seen[] = $uniqueKey;

            $out[] = [
                'nama_upt'         => $r['nama_upt'] ?? '',
                'nama_satpel'      => $r['nama_satpel'] ?? '',
                'nama_pospel'      => $r['nama_pospel'] ?? '',
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
            default => '-',
        };
    }

    private function mapPermohonan($v)
    {
        return match (strtoupper($v)) {
            'EX' => 'Ekspor',
            'IM' => 'Impor',
            'DK' => 'Domestik Keluar',
            'DM' => 'Domestik Masuk',
            default => '-',
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
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/x-www-form-urlencoded',
                'Authorization: Basic bXJpZHdhbjpaPnV5JCx+NjR7KF42WDQm'
            ],
        ]);

        $res = curl_exec($ch);
        
        if (curl_errno($ch)) {
            // Opsional: Log error curl_error($ch);
            return null;
        }

        curl_close($ch);
        return json_decode($res, true);
    }
}