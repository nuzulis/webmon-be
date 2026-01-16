<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Hitung SLA dari tanggal periksa ke tanggal lepas
 *
 * @param string|null $tglPeriksa
 * @param string|null $tglLepas
 * @return array|null
 */
if (!function_exists('hitung_sla')) {
    function hitung_sla($tglPeriksa, $tglLepas)
    {
        if (empty($tglPeriksa) || empty($tglLepas)) {
            return null;
        }

        try {
            $start = new DateTime($tglPeriksa);
            $end   = new DateTime($tglLepas);

            if ($end < $start) {
                return null;
            }

            $diff = $start->diff($end);

            return [
                'hari'  => $diff->days,
                'jam'   => $diff->h,
                'menit' => $diff->i,
                'label' => sprintf(
                    '%d hari %d jam %d menit',
                    $diff->days,
                    $diff->h,
                    $diff->i
                )
            ];
        } catch (Exception $e) {
            return null;
        }
    }
}
