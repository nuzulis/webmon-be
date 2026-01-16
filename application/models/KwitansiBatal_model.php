<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'models/BaseModelStrict.php';

class KwitansiBatal_model extends BaseModelStrict
{
    protected $endpoint = 'https://simponi.karantinaindonesia.go.id/epnbp/batal/kuitansi';

    public function fetch($f)
    {
        $karMap = [
            'kh' => 'H',
            'ki' => 'I',
            'kt' => 'T',
        ];

        $karantina = $karMap[strtolower($f['karantina'] ?? '')] ?? '';
        
        $permMap    = ['ex', 'im', 'dk', 'dm'];
        $permohonan = in_array(strtolower($f['permohonan'] ?? ''), $permMap, true)
            ? strtolower($f['permohonan'])
            : '';

        $payload = http_build_query([
            'dFrom'       => $f['start_date'],
            'dTo'         => $f['end_date'],
            'kar'         => $karantina,
            'upt'         => $f['upt'] ?? '',
            'permohonan'  => $permohonan,
            'berdasarkan' => $f['berdasarkan'] ?? '',
        ]);

        $response = $this->curlPost($this->endpoint, $payload);

        if (!$response || empty($response['status']) || empty($response['data'])) {
            return [];
        }

        return $this->normalize($response['data']);
    }

    private function normalize($rows)
    {
        if (!is_array($rows)) return [];

        $seen = [];
        $out  = [];

        // Sort berdasarkan waktu hapus (terbaru di atas)
        usort($rows, function($a, $b) {
            return strcmp($b['deleted_at'] ?? '', $a['deleted_at'] ?? '');
        });

        foreach ($rows as $r) {
            // Gunakan kode_bill sebagai identifier unik utama untuk transaksi PNBP
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
            CURLOPT_TIMEOUT        => 30,
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