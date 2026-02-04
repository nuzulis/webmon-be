<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class M_SSM extends CI_Model
{
    /* =========================================================
     * PUBLIC: TIMELINE SSM
     * ========================================================= */
    public function ev_ssm(array $ptk): array
    {
        $nomor = $ptk['no_dok_permohonan']
            ?? $ptk['no_dok']
            ?? null;

        if (empty($ptk['tssm_id']) || empty($nomor)) {
            log_message('error', 'EV_SSM SKIP: nomor/tssm kosong');
            return [];
        }

        log_message('error', 'EV_SSM CALL: ' . json_encode([
            'nomor' => $nomor,
            'tssm_id' => $ptk['tssm_id']
        ]));

        $data = $this->fetch_ssm_history($nomor, $ptk['tssm_id']);

        $events = [];

        
        foreach ($data['izin'] as $i) {
            $events[] = [
                'kode'        => 'SSM',
                'jenis'       => 'ssm_izin',
                'judul'       => 'Respon SSM Perizinan',
                'nomor'       => $i['nomor'] ?? null,
                'tanggal'     => $i['tgl_dok'] ?? null,
                'waktu_input' => $i['time'] ?? null,
                'status'      => ($i['kode'] ?? null) == 200 ? 'sukses' : 'gagal',
                'alasan'      => $i['respon'] ?? null,
                'meta'        => $i
            ];
        }

       
        foreach ($data['qc'] as $q) {
            $events[] = [
                'kode'        => 'SSM',
                'jenis'       => 'ssm_qc',
                'judul'       => 'Respon SSM QC',
                'nomor'       => $q['no_ijin'] ?? null,
                'tanggal'     => $q['date_ijin'] ?? null,
                'waktu_input' => $q['tgl_respon'] ?? null,
                'status'      => $q['status'] ?? null,
                'alasan'      => $q['respon'] ?? null,
                'meta'        => $q
            ];
        }

        return $events;
    }

    /* =========================================================
     * PRIVATE: CALL API SSM
     * ========================================================= */
    private function fetch_ssm_history(string $nomor, string $tssm_id): array
    {
        $payload = json_encode([
            'nomor'   => $nomor,
            'tssm_id' => $tssm_id
        ]);

        $ch = curl_init('https://api.karantinaindonesia.go.id/ssm/historySsm');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Basic bXJpZHdhbjpaPnV5JCx+NjR7KF42WDQm'
            ],
            CURLOPT_POSTFIELDS     => $payload
        ]);

        $response = curl_exec($ch);

        if ($response === false) {
            log_message('error', 'SSM CURL ERROR: ' . curl_error($ch));
            curl_close($ch);
            return ['izin'=>[], 'qc'=>[]];
        }

        curl_close($ch);

        log_message('error', 'SSM RAW RESPONSE: ' . $response);

        $json = json_decode($response, true);

        log_message('error', 'SSM JSON: ' . json_encode($json));

        return [
            'izin' => $json['izin']
                ?? $json['data']['izin']
                ?? [],
            'qc'   => $json['qc']
                ?? $json['data']['qc']
                ?? []
        ];
    }
}
