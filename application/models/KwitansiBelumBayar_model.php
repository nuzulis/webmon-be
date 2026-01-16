<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'models/BaseModelStrict.php';

class KwitansiBelumBayar_model extends BaseModelStrict
{
    private $endpoint = 'https://simponi.karantinaindonesia.go.id/epnbp/kuitansi/unpaid';

    /* =====================================================
     * FETCH DATA KWITANSI BELUM BAYAR DARI SIMPONI
     * ===================================================== */
    public function fetch($f)
    {
        $karantinaMap = [
            'kh' => 'H',
            'kt' => 'T',
            'ki' => 'I',
        ];

        $params = [
            'dFrom'       => $f['start_date'],
            'dTo'         => $f['end_date'],
            'kar'         => $karantinaMap[strtolower($f['karantina'] ?? '')] ?? '',
            'upt'         => ($f['upt'] !== 'all') ? $f['upt'] : '',
            'permohonan'  => $f['permohonan'] ?? '',
            'berdasarkan' => $f['berdasarkan'] ?? 'tanggal_aju',
        ];

        $response = $this->curlPost($this->endpoint, http_build_query($params));

        if (!$response || empty($response['status']) || empty($response['data'])) {
            return [];
        }

        return $this->normalize($response['data']);
    }

    /* =====================================================
     * NORMALISASI & DEDUP DATA (Berdasarkan Kode Bill)
     * ===================================================== */
    private function normalize($rows)
    {
        if (!is_array($rows)) return [];

        $unique = [];
        foreach ($rows as $row) {
            // Menggunakan kode_bill sebagai key unik karena satu ptk_id 
            // bisa saja memiliki histori billing yang berbeda
            $key = $row['kode_bill'] ?? $row['ptk_id'];
            
            $unique[$key] = [
                'upt'              => $row['nama_upt'] ?? '',
                'satpel'           => trim(($row['nama_satpel'] ?? '') . ' - ' . ($row['nama_pospel'] ?? '')),
                'jenis_karantina'  => $this->mapKarantina($row['jenis_karantina'] ?? ''),
                'nomor'            => $row['nomor'] ?? '', // No Kuitansi
                'tanggal'          => $row['tanggal'] ?? '', // Tgl Kuitansi
                'jenis_permohonan' => $this->mapPermohonan($row['jenis_permohonan'] ?? ''),
                'wajib_bayar'      => $row['nama_wajib_bayar'] ?? '',
                'total_pnbp'       => (float) ($row['total_pnbp'] ?? 0),
                'kode_bill'        => $row['kode_bill'] ?? '',
                'expired_date'     => $row['tgl_exp_billing'] ?? '', // Sangat penting untuk belum bayar
                'tipe_bayar'       => $row['tipe_bayar'] ?? '',
                'no_aju'           => $row['no_aju'] ?? ''
            ];
        }

        $data = array_values($unique);

        // Sort: tampilkan yang paling lama belum bayar atau yang paling baru billingnya
        usort($data, function($a, $b) {
            return strcmp($b['tanggal'], $a['tanggal']);
        });

        return $data;
    }

    private function mapKarantina($v)
    {
        return match (strtoupper($v)) {
            'H' => 'Hewan', 'I' => 'Ikan', 'T' => 'Tumbuhan', default => '-'
        };
    }

    private function mapPermohonan($v)
    {
        return match (strtoupper($v)) {
            'EX' => 'Ekspor', 'IM' => 'Impor', 'DK' => 'Domestik Keluar', 'DM' => 'Domestik Masuk', default => '-'
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
            CURLOPT_TIMEOUT        => 45 // Data unpaid biasanya butuh waktu tarik lebih lama
        ]);

        $res = curl_exec($ch);
        curl_close($ch);
        return json_decode($res, true);
    }
}