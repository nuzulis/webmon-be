<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * @property M_Ptk_Core      $ptk
 * @property M_Tindakan      $tindakan
 * @property M_SSM_Legacy    $ssmLegacy
 */
class Detail extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();

        $this->load->model('M_Ptk_Core', 'ptk');
        $this->load->model('M_Tindakan', 'tindakan');
        $this->load->model('M_SSM_Legacy', 'ssmLegacy');
    }

    public function view()
    {   
        $rawInput = file_get_contents("php://input");
    if (!empty($rawInput)) {
        $json = json_decode($rawInput, true);
        if ($json) {
            $_POST = $json;
        } else {
            parse_str($rawInput, $postData);
            $_POST = $postData;
        }
    }

        $id    = trim($this->input->post('id'));
        $modul = trim($this->input->post('modul'));

        if (!$id) {
            return $this->json_error('ID tidak boleh kosong');
        }
        $base = $this->ptk->get_ptk_detail('ID', $id);
        if (!$base) {
            return $this->json_error('PTK tidak ditemukan');
        }
        $via = 'LAINNYA';
        $lastChar = substr($base['no_aju'], -1);

        if ($lastChar == 'S') {
            $via = "SSM QC";
        } else if ($lastChar == 'P') {
            $via = "PTK ONLINE";
        } else if ($lastChar == 'D') {
            $via = "DOMAS ONLINE";
        } else if ($lastChar == 'M') {
            $via = "MANUAL";
        } else if ($lastChar == 'T') {
            $via = "SERAH TERIMA";
        }

        $base['via'] = $via;
        $jenisKarantina = $base['jenis_karantina'];
        $response = [
            'success' => true,
            'data' => [
                'base'      => $base,
                'komoditas' => $this->ptk->get_komoditas($id, $jenisKarantina),
                'dokumen'   => $this->ptk->get_dokumen($id),
            ]
        ];
        $deteksi = $this->tindakan->detect_serah_terima($id);
        if ($deteksi['mode'] === 'NORMAL') {
            $timeline = $this->tindakan->get_timeline($id, $jenisKarantina);

            usort($timeline, static fn($a, $b) =>
                strtotime($a['waktu_input']) <=> strtotime($b['waktu_input'])
            );

            $response['data']['timeline'] = $timeline;
            $history = $this->tindakan->get_history_flat($id);
            $jenisPermohonan = $base['jenis_permohonan'] 
                ?? ($history[0]['jenis_permohonan'] ?? null);

            $ssmParams = [
                'tssm_id'          => $base['tssm_id'] ?? null,
                'jenis_permohonan' => $jenisPermohonan
            ];
            log_message('debug', 'SSM Params: ' . json_encode($ssmParams));
            log_message('debug', 'History Data: ' . json_encode($history));

            $response['data']['respon_ssm'] = 
                $this->ssmLegacy->build_respon_ssm($ssmParams, $history);
        }

        else {

            $timeline = [];
            $asalCtx = $this->tindakan->get_ptk_context($deteksi['ptk_asal_id']);

            if ($asalCtx) {

                $eventsAsal = $this->tindakan
                    ->get_timeline($asalCtx['id'], $asalCtx['jenis_karantina']);

                usort($eventsAsal, static fn($a, $b) =>
                    strtotime($a['waktu_input']) <=> strtotime($b['waktu_input'])
                );

                $timeline['asal'] = [
                    'ptk_id'          => $asalCtx['id'],
                    'upt_id'          => $asalCtx['upt_id'],
                    'upt_nama'        => $asalCtx['upt_nama'],
                    'jenis_karantina' => $asalCtx['jenis_karantina'],
                    'status'          => 'SELESAI_SERAH',
                    'events'          => $eventsAsal
                ];
                $historyAsal = $this->tindakan->get_history_flat($asalCtx['id']);

                $ssmParamsAsal = [
                    'tssm_id'          => $asalCtx['tssm_id'] ?? null,
                    'jenis_permohonan' => $asalCtx['jenis_permohonan'] ?? null
                ];

                $response['data']['respon_ssm'] =
                    $this->ssmLegacy->build_respon_ssm($ssmParamsAsal, $historyAsal);
            }
            if (!empty($deteksi['ptk_tujuan_id'])) {

                $tujuanCtx = $this->tindakan->get_ptk_context($deteksi['ptk_tujuan_id']);

                if ($tujuanCtx) {

                    $eventsTujuan = $this->tindakan
                        ->get_timeline($tujuanCtx['id'], $tujuanCtx['jenis_karantina']);

                    usort($eventsTujuan, static fn($a, $b) =>
                        strtotime($a['waktu_input']) <=> strtotime($b['waktu_input'])
                    );

                    $timeline['tujuan'] = [
                        'ptk_id'          => $tujuanCtx['id'],
                        'upt_id'          => $tujuanCtx['upt_id'],
                        'upt_nama'        => $tujuanCtx['upt_nama'],
                        'jenis_karantina' => $tujuanCtx['jenis_karantina'],
                        'status'          => 'DALAM_PROSES',
                        'events'          => $eventsTujuan
                    ];
                }
            }

            $response['data']['timeline'] = $timeline;
        }

        return $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode($response));
    }
    private function json_error(string $message, int $code = 400)
    {
        return $this->output
            ->set_status_header($code)
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'success' => false,
                'message' => $message
            ]));
    }
}