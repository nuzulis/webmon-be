<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class M_SSM_Legacy extends CI_Model
{
    /* =====================================================
     * PUBLIC ENTRY
     * ===================================================== */
    public function build_respon_ssm(array $ptk, array $history): string
    {
        // Validasi tssm_id - jika kosong, tetap lanjut tapi tandai sebagai NON SSM
        $tssmId = $ptk['tssm_id'] ?? null;

        // Validasi jenis_permohonan dengan pengecekan aman
        $jenisPermohonan = $ptk['jenis_permohonan'] ?? null;
        
        // Hanya proses untuk IM dan EX
        if (!$jenisPermohonan || !in_array($jenisPermohonan, ['IM', 'EX'], true)) {
            return '';
        }

        // Validasi history tidak kosong
        if (empty($history) || !isset($history[0])) {
            // Tetap render HTML kosong untuk konsistensi
            return $this->render_html([], [], $tssmId);
        }

        $dataIzin = [];
        $dataSsm  = [];

        // Jika tidak ada tssm_id, skip API call dan langsung render empty
        if (empty($tssmId)) {
            return $this->render_html($dataIzin, $dataSsm, $tssmId);
        }

        $map = [
            'nomor_k34'  => 'tgl_k34',
            'nomor_k310' => 'tgl_k310',
            'nomor_p6'   => 'tgl_p6',
            'nomor_p8'   => 'tgl_p8',
        ];

        foreach ($map as $nomorKey => $tglKey) {

            if (empty($history[0][$nomorKey])) {
                continue;
            }

            $raw = $this->call_ssm_api(
                $history[0][$nomorKey],
                $tssmId
            );

            if (!$raw) continue;

            $json = json_decode($raw, true);
            if (!$json) continue;

            /* ===== IZIN ===== */
            if (!empty($json['data_izin'])) {
                foreach ($json['data_izin'] as $izin) {
                    $izin['tgl_dok'] = $history[0][$tglKey] ?? null;
                    $dataIzin[] = $izin;
                }
            }

            /* ===== QC (AMBIL SEKALI) ===== */
            if (empty($dataSsm) && !empty($json['data_ssm'])) {
                $dataSsm = $json['data_ssm'];
            }
        }

        return $this->render_html($dataIzin, $dataSsm, $tssmId);
    }

    /* =====================================================
     * CALL API SSM (LEGACY STYLE)
     * ===================================================== */
    private function call_ssm_api(string $nomor, string $tssmId): ?string
    {
        $payload = json_encode([
            'nomor'   => $nomor,
            'tssm_id' => $tssmId
        ]);

        $ch = curl_init('https://api.karantinaindonesia.go.id/ssm/historySsm');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Basic bXJpZHdhbjpaPnV5JCx+NjR7KF42WDQm'
            ],
            CURLOPT_POSTFIELDS     => $payload
        ]);

        $resp = curl_exec($ch);

        if ($resp === false) {
            log_message('error', 'SSM LEGACY CURL ERROR: ' . curl_error($ch));
            curl_close($ch);
            return null;
        }

        curl_close($ch);
        return $resp;
    }

    /* =====================================================
     * RENDER HTML (COPY LOGIC setdatassm)
     * ===================================================== */
    private function render_html(array $izin, array $qc, ?string $tssmId): string
    {
        $html  = '<div class="row">';

        /* ===== IZIN ===== */
        $html .= '<div class="col-xs-12 col-sm-6" style="padding-right:50px">';
        $html .= '<h5 class="text-primary">Respon SSM Perizinan</h5>';
        $html .= '<ul class="timeline">';

        if (!empty($izin)) {
            foreach ($izin as $iz) {

                $berhasil = ($iz['kode'] ?? null) == 200;
                $respText = $iz['respon'] ?? '';
                $errIzin = '';

                if (!empty($iz['kode']) && !empty($iz['respon'])) {
                    $json = json_decode($iz['respon'], true);
                    if ($json && isset($json['data'])) {
                        $respText = ($json['data']['kode'] ?? '')
                            . ' - ' . ($json['data']['keterangan'] ?? '');
                        
                        // Jika ada error, tampilkan detail
                        if ($iz['kode'] != 200 && !empty($json['data']['data'])) {
                            $errIzin = '<ol>';
                            foreach ($json['data']['data'] as $e) {
                                $errIzin .= '<li>' . ($e['tipe'] ?? '') . ' - ' . ($e['keterangan'] ?? '') . '</li>';
                            }
                            $errIzin .= '</ol>';
                        }
                    }
                } else {
                    // Cek jika respon adalah "Ijin telah diproses oleh INSW"
                    if ($iz['respon'] === 'Ijin telah diproses oleh INSW') {
                        $berhasil = true;
                    }
                }

                $html .= '<li class="timeline-item mb-5">';
                $html .= '<p class="float-end">Tgl respon: '.($iz['time'] ?? '').'</p>';
                $html .= '<h6 class="fw-bold mb-0">Nomor: '.($iz['nomor'] ?? '').'</h6>';
                $html .= '<p class="text-muted fw-bold">Tgl Dokumen: '.($iz['tgl_dok'] ?? '').'</p>';
                $html .= '<p class="fw-bold '.($berhasil?'text-success':'text-danger').'">'
                      .  $respText . '</p>';
                
                if ($errIzin) {
                    $html .= $errIzin;
                }
                
                $html .= '</li>';
            }
        } else {
            $html .= '<b>Belum ada respon</b>';
        }

        $html .= '</ul></div>';

        /* ===== QC ===== */
        $html .= '<div class="col-xs-12 col-sm-6">';
        $html .= '<h5 class="text-primary">Respon SSM QC '
              . (!empty($qc[0]['ajussm']) ? ' - '.$qc[0]['ajussm'] : '')
              . '</h5>';
        $html .= '<ul class="timeline">';

        if (!empty($qc)) {
            foreach ($qc as $q) {

                $responJson = null;
                $err = '';
                
                if (!empty($q['responbalik'])) {
                    $responJson = json_decode($q['responbalik'], true);
                    if ($responJson && isset($responJson['code']) && $responJson['code'] != '01') {
                        if (!empty($responJson['data']) && is_array($responJson['data'])) {
                            $err = implode('; ', $responJson['data']);
                        }
                    }
                }

                $html .= '<li class="timeline-item mb-5">';
                $html .= '<div class="input-group justify-content-between">';
                $html .= '<h6 class="fw-bold mb-0 float-start">'
                      . (!empty($q['no_ijin']) ? 'Nomor: '.$q['no_ijin'] : 'Karantina terima respon')
                      . '</h6>';
                
                $statusText = ($q['status'] ?? '') . ' - ' . (
                    !empty($q['respon']) 
                        ? str_replace('SURAT PERSETUJUAN PEMINDAHAN MEDIA PEMBAWA (SP2MP)', 'SP2MP', $q['respon'])
                        : ''
                );
                
                $html .= '<p class="float-end mb-1">'.$statusText.'</p>';
                $html .= '</div>';
                
                $html .= '<div class="input-group justify-content-between">';
                $html .= '<p class="text-muted mb-1 fw-bold float-start">'
                      . (!empty($q['date_ijin']) ? 'Tgl Dokumen: '.$q['date_ijin'] : '')
                      . '</p>';
                $html .= '<p class="float-end mb-1">Tgl respon: '.($q['tgl_respon'] ?? '').'</p>';
                $html .= '</div>';
                
                $html .= '<p class="text-muted float-start">';
                if ($responJson) {
                    $html .= '<b class="'
                          . (($responJson['code'] ?? '') == '01' ? 'text-success' : 'text-danger')
                          . '">'.($responJson['code'] ?? '').' - '.($responJson['message'] ?? '').'</b> '
                          . $err;
                }
                $html .= '</p>';

                $html .= '</li>';
            }
        } else {
            if ($tssmId) {
                $html .= '<b>Belum ada respon</b>';
            } else {
                $html .= '<b>*Permohonan NON SSM</b>';
            }
        }

        $html .= '</ul></div></div>';

        return $html;
    }
}