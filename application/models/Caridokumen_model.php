<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Caridokumen_model extends CI_Model {

    /* ==========================
     * UTIL
     * ========================== */

    public function replaceDok($nomor)
    {
        $nomor = explode("/", $nomor)[0];

        $map = [
            'K.W.I','K.1.5','K.3.7a','K.3.10','K.3.9','K.3.8','K.3.6',
            'K.3.5','K.3.4','K.3.3','K.3.2','K.3.1','K.3.7b',
            'K.4.1','K.5.1','K.5.2','K.5.3','K.6.1','K.6.2',
            'K.6.3','K.7.1','K.7.2','K.7.3','K.9.1','K.9.2',
            'K.9.3','K.9.4','K.T.1','K.T.2','K.T.3','K.T.4',
            'K.H.1','K.H.2','K.I.1','K.I.2','K.2.2'
        ];

        return str_replace($map, 'K.1.1', $nomor);
    }

    /* ==========================
     * PTK
     * ========================== */

  public function getPtk($filter, $value, $upt_id)
    {
        $this->db
            ->select('p.*, mjk.jenis_karantina, mjk.nama AS mp')
            ->from('ptk p')
            ->join('master_jenis_media_pembawa mjk','p.jenis_media_pembawa_id=mjk.id', 'left');

        // Jika upt_id bukan 1000, lakukan filtering
        if ($upt_id != "1000") { 
            $this->db->where('p.upt_id', $upt_id);
        }

        if ($filter === 'AJU') {
            $this->db->where('p.no_aju', $value);
        } else {
            $this->db->where('p.no_dok_permohonan', $value);
        }

        return $this->db->get()->row_array();
    }


    public function getHistory($ptk_id, $karantina)
{
    // Query akan membentuk nama tabel seperti: pn_pelepasan_kh atau pn_pelepasan_kt
    $sql = "SELECT p.*, 
            p8.nomor AS nomor_p8, p8.created_at AS tgl_input_p8,
            dok.kode_dok, p8.nomor_seri
            FROM ptk p
            LEFT JOIN pn_pelepasan_{$karantina} p8 ON p.id=p8.ptk_id
            LEFT JOIN master_dokumen_karantina dok ON dok.id=p8.dokumen_karantina_id
            WHERE p.id=? 
            ORDER BY p8.created_at ASC";

    return $this->db->query($sql, [$ptk_id])->result_array();
}


    public function setValueTblPtk($ptk)
    {
        $via = substr($ptk['no_aju'], -1);
        $map = [
            'S'=>'SSM QC','P'=>'PTK ONLINE','D'=>'DOMAS ONLINE',
            'M'=>'MANUAL','T'=>'SERAH TERIMA'
        ];

        $via = $map[$via] ?? '-';

        return "<table class='table table-bordered'>
            <tr><th>Tanggal Aju</th><td>{$ptk['tgl_aju']}</td></tr>
            <tr><th>Via</th><td>{$via}</td></tr>
            <tr><th>Nama Pemohon</th><td>{$ptk['nama_pemohon']}</td></tr>
        </table>";
    }

    public function setValueRiwayat($history)
    {
        $html = '';
        foreach ($history as $h) {
            if ($h['nomor_p8']) {
                $link = "https://cert.karantinaindonesia.go.id/print_cert/pembebasan/"
                      . base64_encode($h['id']."_view");

                $html .= "<tr>
                    <td>PEMBEBASAN</td>
                    <td><a href='{$link}' target='_blank'>{$h['nomor_p8']}</a></td>
                    <td>{$h['tgl_input_p8']}</td>
                </tr>";
            }
        }
        return $html;
    }
    private function fetchSsmApi($nomor, $tssm_id)
    {
        $ch = curl_init('https://api.karantinaindonesia.go.id/ssm/historySsm');

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Basic bXJpZHdhbjpaPnV5JCx+NjR7KF42WDQm'
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'nomor'   => $nomor,
                'tssm_id' => $tssm_id
            ]),
            CURLOPT_TIMEOUT => 10
        ]);

        $res = curl_exec($ch);
        curl_close($ch);

        return json_decode($res, true);
    }


    private function collectSsmData(array $history, $tssm_id)
    {
        $izin = [];
        $qc   = [];

        $dokKeys = [
            'nomor_k34'  => 'tgl_k34',
            'nomor_k310' => 'tgl_k310',
            'nomor_p6'   => 'tgl_p6',
            'nomor_p8'   => 'tgl_p8'
        ];

        foreach ($dokKeys as $noKey => $tglKey) {
            if (empty($history[0][$noKey])) {
                continue;
            }

            $api = $this->fetchSsmApi($history[0][$noKey], $tssm_id);
            if (!$api) continue;

            // DATA IZIN
            if (!empty($api['data_izin'])) {
                foreach ($api['data_izin'] as $i) {
                    $i['tgl_dok'] = $history[0][$tglKey] ?? null;
                    $izin[] = $i;
                }
            }

            // DATA QC (ambil sekali)
            if (!$qc && !empty($api['data_ssm'])) {
                $qc = $api['data_ssm'];
            }
        }

        return [$izin, $qc];
    }


  public function buildResponSsm(array $history, $tssm_id)
    {
        if (!$tssm_id) {
            return '<b>*Permohonan NON SSM</b>';
        }

        if (!$history) {
            return '<b>Belum ada data SSM</b>';
        }

        [$izin, $qc] = $this->collectSsmData($history, $tssm_id);

        $html = '<div class="row">';

        /* ================= IZIN ================= */
        $html .= '<div class="col-sm-6">
            <h5 class="text-primary">Respon SSM Perizinan</h5>
            <ul class="timeline">';

        if ($izin) {
            foreach ($izin as $iz) {
                $success = false;
                $msg = '';

                if (!empty($iz['kode'])) {
                    $success = ($iz['kode'] == 200);
                    $json = json_decode($iz['respon'], true);
                    if ($json && isset($json['data'])) {
                        $msg = $json['data']['kode'].' - '.$json['data']['keterangan'];
                    }
                } else {
                    $msg = $iz['respon'];
                    if ($msg === 'Ijin telah diproses oleh INSW') {
                        $success = true;
                    }
                }

                $html .= "
                <li class='timeline-item mb-4'>
                    <p class='float-end'>Tgl respon: {$iz['time']}</p>
                    <h6 class='fw-bold'>Nomor: {$iz['nomor']}</h6>
                    <p class='text-muted'>Tgl Dokumen: {$iz['tgl_dok']}</p>
                    <p class='fw-bold ".($success?'text-success':'text-danger')."'>
                        {$msg}
                    </p>
                </li>";
            }
        } else {
            $html .= '<b>Belum ada respon perizinan</b>';
        }

        $html .= '</ul></div>';

        /* ================= QC ================= */
        $html .= '<div class="col-sm-6">
            <h5 class="text-primary">Respon SSM QC</h5>
            <ul class="timeline">';

        if ($qc) {
            foreach ($qc as $q) {
                $json = json_decode($q['responbalik'], true);
                $ok   = ($json && $json['code'] === '01');

                $html .= "
                <li class='timeline-item mb-4'>
                    <h6 class='fw-bold'>".($q['no_ijin'] ? 'Nomor: '.$q['no_ijin'] : 'Karantina terima respon')."</h6>
                    <p>{$q['status']} - {$q['respon']}</p>
                    <p class='fw-bold ".($ok?'text-success':'text-danger')."'>
                        ".($json ? $json['code'].' - '.$json['message'] : '')."
                    </p>
                    <small>Tgl respon: {$q['tgl_respon']}</small>
                </li>";
            }
        } else {
            $html .= '<b>Belum ada respon QC</b>';
        }

        $html .= '</ul></div></div>';

        return $html;
    }



    public function getKomoditas($ptk_id)
    {
        return $this->db
            ->where('ptk_id', $ptk_id)
            ->where('deleted_at', '1970-01-01 08:00:00')
            ->get('ptk_komoditas')
            ->result_array();
    }

    public function getKontainer($ptk_id)
    {
        return $this->db->get_where('ptk_kontainer', [
            'ptk_id' => $ptk_id,
            'deleted_at' => '1970-01-01 08:00:00'
        ])->result_array();
    }

    public function getDokumen($ptk_id)
    {
        return $this->db
            ->where('ptk_id', $ptk_id)
            ->get('ptk_dokumen')
            ->result_array();
    }

    public function getSingmat($ptk_id)
    {
        return $this->db
            ->where('ptk_id', $ptk_id)
            ->get('pn_singmat')
            ->result_array();
    }

    public function getKuitansiHtml($ptk_id)
    {
        $url = "https://simponi.karantinaindonesia.go.id/epnbp/kuitansi/list?ptk_id=".$ptk_id;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Basic bXJpZHdhbjpaPnV5JCx+NjR7KF42WDQm'
            ]
        ]);

        $res = json_decode(curl_exec($ch), true);
        curl_close($ch);

        return $res['data'] ?? null;
    }
}
