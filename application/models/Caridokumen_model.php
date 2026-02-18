<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Caridokumen_model extends CI_Model {

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
    private function mapPelepasanTable($jenis)
{
    $map = [
        'H' => 'pn_pelepasan_kh',
        'I' => 'pn_pelepasan_ki',
        'T' => 'pn_pelepasan_kt',
    ];

    return $map[strtoupper($jenis)] ?? null;
}

   

  public function getPtk($filter, $value, $upt_id)
{
    $this->db
        ->select("
            p.id,
            p.no_aju,
            p.tgl_aju,
            p.no_dok_permohonan,
            p.tgl_dok_permohonan,
            p.tssm_id,

            p.nama_pemohon,
            p.alamat_pemohon,
            p.stat_pemohon,

            p.nama_pengirim,
            p.alamat_pengirim,
            p.nama_penerima,
            p.alamat_penerima,

            mn1.nama AS asal,
            mn2.nama AS tuju,
            kn1.nama AS kota_asal,
            kn2.nama AS kota_tuju,

            pel_muat.nama    AS pelabuhanasal,
            pel_bongkar.nama AS pelabuhantuju,

            moda.nama AS moda,
            p.nama_alat_angkut_terakhir,
            p.no_voyage_terakhir,
            p.tanggal_rencana_berangkat_terakhir,
            p.tanggal_rencana_tiba_terakhir,

            mjk.nama AS mp,
            mjk.jenis_karantina AS karantina,
            p.jenis_karantina,

            p.jenis_permohonan,
            p.status_ptk,
            p.is_batal,

            peg.nama AS user_batal,
            peg.nip  AS nip_batal,

            p.nama_tempat_pemeriksaan,
            p.alamat_tempat_pemeriksaan,
            p.instalasi_karantina_id,
            p.npwp15,

            p.nomor_identitas_pengirim,
            p.jenis_identitas_pengirim,
            p.nomor_identitas_penerima,
            p.jenis_identitas_penerima,

            p.deleted_at AS tgl_batal,
            p.alasan_batal,
            p.alasan_penolakan,
            p.petugas,
            p.updated_at AS jam_update_ptk,
            mu.nama AS nama_upt,
            mu.nama_satpel
        ")
        ->from('ptk p')
        ->join('master_upt mu', 'p.kode_satpel = mu.id', 'left')
        ->join('master_jenis_media_pembawa mjk', 'p.jenis_media_pembawa_id = mjk.id', 'left')
        ->join('master_pelabuhan pel_muat', 'p.pelabuhan_muat_id = pel_muat.id', 'left')
        ->join('master_pelabuhan pel_bongkar', 'p.pelabuhan_bongkar_id = pel_bongkar.id', 'left')
        ->join('master_moda_alat_angkut moda', 'p.moda_alat_angkut_terakhir_id = moda.id', 'left')
        ->join('master_pegawai peg', 'p.user_batal = peg.id', 'left')
        ->join('master_negara mn1', 'p.negara_asal_id = mn1.id', 'left')
        ->join('master_negara mn2', 'p.negara_tujuan_id = mn2.id', 'left')
        ->join('master_kota_kab kn1', 'p.kota_kab_asal_id = kn1.id', 'left')
        ->join('master_kota_kab kn2', 'p.kota_kab_tujuan_id = kn2.id', 'left');

    if ($upt_id !== "1000") {
        $this->db->where('p.upt_id', $upt_id);
    }

    if ($filter === 'AJU') {
        $this->db->where('p.no_aju', $value);
    } else {
        $this->db->where('p.no_dok_permohonan', $value);
    }

     $row = $this->db->get()->row_array();
    if (!$row) {
        return null;
    }

    $kodeVia = substr($row['no_aju'], -1);
    $mapVia = [
        'S' => 'SSM QC',
        'P' => 'PTK ONLINE',
        'D' => 'DOMAS ONLINE',
        'M' => 'MANUAL',
        'T' => 'SERAH TERIMA'
    ];

    $row['via'] = $mapVia[$kodeVia] ?? '-';

    return $row;
}



    public function getHistory($ptk_id, $karantina)
{
      $tableP8 = $this->mapPelepasanTable($karantina);

    if (!$tableP8) {
        return [];
    }

    $sql = "
        SELECT 
            p.id,
            p.no_aju,
            p.no_dok_permohonan AS no_dok,
            p.tgl_dok_permohonan,
            p.jenis_karantina,
            p.jenis_permohonan,
            p.petugas,
            p.nip,
            p.created_at AS tgl_input_k11,
            st.id            AS id_st,
            st.nomor         AS nomor_st,
            st.tanggal       AS tgl_st,
            st.created_at    AS tgl_input_st,
            tst.nama         AS nama_st,
            tst.nip          AS nip_st,
            pen.id                   AS id_pen,
            pen.no_dok_permohonan    AS no_dok_pen,
            pen.tgl_dok_permohonan   AS tgl_dok_pen,
            pen.petugas              AS nama_pen_st,
            pen.nip                  AS nip_pen_st,
            pen.created_at           AS tgl_input_pen,
            p1a.id AS id_k37a, p1a.nomor AS nomor_k37a, p1a.tanggal AS tgl_k37a,
            p1a.created_at AS tgl_input_k37a, tp1a.nama AS nama_k37a, tp1a.nip AS nip_k37a,

            p1b.id AS id_k37b, p1b.nomor AS nomor_k37b, p1b.tanggal AS tgl_k37b,
            p1b.created_at AS tgl_input_k37b, tp1b.nama AS nama_k37b, tp1b.nip AS nip_k37b,

            p33.id AS id_k33, p33.nomor AS nomor_k33, p33.tanggal AS tgl_k33,
            p33.created_at AS tgl_input_k33, tp33.nama AS nama_k33, tp33.nip AS nip_k33,

            p34.id AS id_k34, p34.nomor AS nomor_k34, p34.tanggal AS tgl_k34,
            p34.created_at AS tgl_input_k34, tp34.nama AS nama_k34, tp34.nip AS nip_k34,

            p310.id AS id_k310, p310.nomor AS nomor_k310, p310.tanggal AS tgl_k310,
            p310.created_at AS tgl_input_k310, tp310.nama AS nama_k310, tp310.nip AS nip_k310,
            p53.id AS id_k53, p53.nomor AS nomor_k53, p53.tanggal AS tgl_k53,
            p53.created_at AS tgl_input_k53, tp53.nama AS nama_k53, tp53.nip AS nip_k53,

            p5.id AS id_p5, p5.nomor AS nomor_p5, p5.tanggal AS tgl_p5,
            p5.created_at AS tgl_input_p5, tp5.nama AS nama_p5, tp5.nip AS nip_p5,

            p6.id AS id_p6, p6.nomor AS nomor_p6, p6.tanggal AS tgl_p6,
            p6.created_at AS tgl_input_p6, tp6.nama AS nama_p6, tp6.nip AS nip_p6,

            p7.id AS id_p7, p7.nomor AS nomor_p7, p7.tanggal AS tgl_p7,
            p7.created_at AS tgl_input_p7, tp7.nama AS nama_p7, tp7.nip AS nip_p7,
            p8.id AS id_p8,
            p8.nomor AS nomor_p8,
            p8.tanggal AS tgl_p8,
            p8.created_at AS tgl_input_p8,
            p8.deleted_at AS tgl_delete_p8,
            p8.alasan_delete AS alasan_delete_p8,
            p8.nomor_seri,
            p8.dokumen_karantina_id,
            tp8.nama AS nama_p8,
            tp8.nip AS nip_p8,
            dok.kode_dok

        FROM ptk p
        LEFT JOIN ba_penyerahan_mp st ON st.ptk_id = p.id
        LEFT JOIN master_pegawai tst ON tst.id = st.user_asal_id
        LEFT JOIN ptk pen ON pen.id = st.ptk_id_penerima
        LEFT JOIN pn_administrasi p1a ON p.id=p1a.ptk_id AND p1a.deleted_at='1970-01-01 08:00:00'
        LEFT JOIN master_pegawai tp1a ON tp1a.id=p1a.user_ttd_id

        LEFT JOIN pn_fisik_kesehatan p1b ON p.id=p1b.ptk_id AND p1b.deleted_at='1970-01-01 08:00:00'
        LEFT JOIN master_pegawai tp1b ON tp1b.id=p1b.user_ttd1_id

        LEFT JOIN pn_ba_contoh p33 ON p.id=p33.ptk_id AND p33.deleted_at='1970-01-01 08:00:00'
        LEFT JOIN master_pegawai tp33 ON tp33.id=p33.user_ttd_id

        LEFT JOIN pn_masuk_instalasi p34 ON p.id=p34.ptk_id AND p34.deleted_at='1970-01-01 08:00:00'
        LEFT JOIN master_pegawai tp34 ON tp34.id=p34.user_ttd_id

        LEFT JOIN pn_sp2mp p310 ON p.id=p310.ptk_id AND p310.deleted_at='1970-01-01 08:00:00'
        LEFT JOIN master_pegawai tp310 ON tp310.id=p310.user_ttd_id
        LEFT JOIN pn_perlakuan p53 ON p.id=p53.ptk_id AND p53.deleted_at='1970-01-01 08:00:00'
        LEFT JOIN master_pegawai tp53 ON tp53.id=p53.user_ttd_id

        LEFT JOIN pn_penahanan p5 ON p.id=p5.ptk_id AND p5.deleted_at='1970-01-01 08:00:00'
        LEFT JOIN master_pegawai tp5 ON tp5.id=p5.user_ttd_id

        LEFT JOIN pn_penolakan p6 ON p.id=p6.ptk_id AND p6.deleted_at='1970-01-01 08:00:00'
        LEFT JOIN master_pegawai tp6 ON tp6.id=p6.user_ttd_id

        LEFT JOIN pn_pemusnahan p7 ON p.id=p7.ptk_id AND p7.deleted_at='1970-01-01 08:00:00'
        LEFT JOIN master_pegawai tp7 ON tp7.id=p7.user_ttd_id
        LEFT JOIN {$tableP8} p8 ON p.id=p8.ptk_id
        LEFT JOIN master_pegawai tp8 ON tp8.id=p8.user_ttd_id
        LEFT JOIN master_dokumen_karantina dok ON dok.id=p8.dokumen_karantina_id

        WHERE p.id=?
        ORDER BY p8.created_at ASC
    ";

    return $this->db->query($sql, [$ptk_id])->result_array();
}


    public function setValueRiwayatJson(array $history)
{
    if (!$history) return [];

    $h = $history[0];
    $rows = [];

    $push = function (
        $tindakan,
        $nomor,
        $tglDok,
        $nama,
        $nip,
        $tglInput,
        $link = null,
        $extra = []
    ) use (&$rows) {
        $rows[] = array_merge([
            'tindakan'        => $tindakan,
            'nomor'           => $nomor,
            'tanggal_dok'     => $tglDok,
            'penandatangan'   => $nama,
            'nip'             => $nip,
            'tanggal_input'   => $tglInput,
            'link'            => $link,
        ], $extra);
    };

    if (!empty($h['no_dok'])) {
        $push(
            'Verifikasi Permohonan',
            $h['no_dok'],
            $h['tgl_dok_permohonan'],
            $h['petugas'],
            $h['nip'],
            $h['tgl_input_k11'],
            "https://cert.karantinaindonesia.go.id/print_cert/preborder/k11/"
            . base64_encode($h['id'] . '_view')
        );
    }

    if (!empty($h['nomor_st'])) {
        $push(
            'Serah Terima (Antar UPT)',
            $h['nomor_st'],
            $h['tgl_st'],
            $h['nama_st'],
            $h['nip_st'],
            $h['tgl_input_st'],
            "https://cert.karantinaindonesia.go.id/print_cert/preborder/k15/"
            . base64_encode($h['id_st'] . '_view')
        );
    }

    if (!empty($h['no_dok_pen'])) {
        $push(
            'UPT Penerima',
            $h['no_dok_pen'],
            $h['tgl_dok_pen'],
            $h['nama_pen_st'],
            $h['nip_pen_st'],
            $h['tgl_input_pen'],
            "https://cert.karantinaindonesia.go.id/print_cert/preborder/k11/"
            . base64_encode($h['id_pen'] . '_view')
        );
    }


    $mapPemeriksaan = [
        ['Periksa Dokumen', 'k37a'],
        ['Periksa Kesehatan', 'k37b'],
        ['Pengambilan Contoh', 'k33'],
        ['Periksa Lanjutan', 'k34'],
        ['SP2MP', 'k310'],
    ];

    foreach ($mapPemeriksaan as [$label, $k]) {
        if (!empty($h["nomor_$k"])) {
            $push(
                $label,
                $h["nomor_$k"],
                $h["tgl_$k"],
                $h["nama_$k"],
                $h["nip_$k"],
                $h["tgl_input_$k"],
                "https://cert.karantinaindonesia.go.id/print_cert/pemeriksaan/{$k}/"
                . base64_encode($h["id_$k"] . '_view')
            );
        }
    }

    $mapTindakan = [
        ['Perlakuan', 'k53'],
        ['Penahanan', 'p5'],
        ['Penolakan', 'p6'],
        ['Pemusnahan', 'p7'],
    ];

    foreach ($mapTindakan as [$label, $k]) {
        if (!empty($h["nomor_$k"])) {
            $push(
                $label,
                $h["nomor_$k"],
                $h["tgl_$k"],
                $h["nama_$k"],
                $h["nip_$k"],
                $h["tgl_input_$k"]
            );
        }
    }

    foreach ($history as $item) {
        if (empty($item['nomor_p8'])) continue;

        $push(
            'Pembebasan',
            $item['nomor_p8'],
            $item['tgl_p8'],
            $item['nama_p8'],
            $item['nip_p8'],
            $item['tgl_input_p8'],
            $this->buildPembebasanLink($item),
            [
                'nomor_seri' => $item['nomor_seri'],
                'is_batal'   => $item['tgl_delete_p8'] !== '1970-01-01 08:00:00',
                'tgl_batal'  => $item['tgl_delete_p8'],
                'alasan'     => $item['alasan_delete_p8'],
            ]
        );
    }

    return $rows;
}

private function fetchSsmApi($nomor, $tssm_id)
{
    $ch = curl_init('https://api.karantinaindonesia.go.id/ssm/historySsm');

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Basic bXJpZHdhbjpaPnV5JCx+NjR7KF42WDQm'
        ],
        CURLOPT_POSTFIELDS     => json_encode([
            'nomor'   => $nomor,
            'tssm_id' => $tssm_id
        ]),
        CURLOPT_TIMEOUT        => 10
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
        if (!empty($api['data_izin'])) {
            foreach ($api['data_izin'] as $i) {
                $izin[] = [
                    'nomor'       => $i['nomor'] ?? null,
                    'kode'        => $i['kode'] ?? null,
                    'respon_raw'  => $i['respon'] ?? null,
                    'time'        => $i['time'] ?? null,
                    'tgl_dok'     => $history[0][$tglKey] ?? null,
                ];
            }
        }
        if (!$qc && !empty($api['data_ssm'])) {
            foreach ($api['data_ssm'] as $q) {
                $qc[] = [
                    'nomor'       => $q['no_ijin'] ?? null,
                    'status'      => $q['status'] ?? null,
                    'respon'      => $q['respon'] ?? null,
                    'respon_raw'  => $q['responbalik'] ?? null,
                    'tgl_respon'  => $q['tgl_respon'] ?? null,
                ];
            }
        }
    }

    return [$izin, $qc];
}
public function buildResponSsmJson(array $history, $tssm_id)
{
    if (!$tssm_id) {
        return [
            'status'  => 'NON_SSM',
            'message' => 'Permohonan NON SSM',
            'izin'    => [],
            'qc'      => []
        ];
    }

    if (!$history) {
        return [
            'status'  => 'EMPTY',
            'message' => 'Belum ada data SSM',
            'izin'    => [],
            'qc'      => []
        ];
    }

    [$izinRaw, $qcRaw] = $this->collectSsmData($history, $tssm_id);

    $izin = [];
    foreach ($izinRaw as $i) {
        $success = false;
        $message = '';

        if (!empty($i['kode'])) {
            $success = ((int)$i['kode'] === 200);
            $json = json_decode($i['respon_raw'], true);
            if ($json && isset($json['data'])) {
                $message = $json['data']['kode'] . ' - ' . $json['data']['keterangan'];
            }
        } else {
            $message = $i['respon_raw'];
            if ($message === 'Ijin telah diproses oleh INSW') {
                $success = true;
            }
        }

        $izin[] = [
            'nomor'        => $i['nomor'],
            'tgl_dok'      => $i['tgl_dok'],
            'tgl_respon'   => $i['time'],
            'success'      => $success,
            'message'      => $message
        ];
    }

    $qc = [];
    foreach ($qcRaw as $q) {
        $json = json_decode($q['respon_raw'], true);
        $ok   = ($json && ($json['code'] ?? null) === '01');

        $qc[] = [
            'nomor'        => $q['nomor'],
            'status'       => $q['status'],
            'respon'       => $q['respon'],
            'kode'         => $json['code'] ?? null,
            'message'      => $json['message'] ?? null,
            'success'      => $ok,
            'tgl_respon'   => $q['tgl_respon']
        ];
    }

    return [
        'status' => 'OK',
        'izin'   => $izin,
        'qc'     => $qc
    ];
}



    public function getKomoditas($ptk_id, $karantina)
{
    $this->db
        ->select("
            klas.deskripsi AS klasifikasi,
            kom.nama       AS komoditas,
            p.nama_umum_tercetak,
            p.nama_latin_tercetak,
            p.kode_hs,
            p.kode_hs10,

            p.volume_bruto,
            p.volume_netto,
            p.volume_lain,
            ms.nama AS satuan,

            p.volumeP1, p.nettoP1,
            p.volumeP2, p.nettoP2,
            p.volumeP3, p.nettoP3,
            p.volumeP4, p.nettoP4,
            p.volumeP5, p.nettoP5,
            p.volumeP6, p.nettoP6,
            p.volumeP7, p.nettoP7,
            p.volumeP8, p.nettoP8
        ")
        ->from('ptk_komoditas p')
        ->join('master_satuan ms', 'p.satuan_lain_id = ms.id', 'left')
        ->where('p.ptk_id', $ptk_id)
        ->where('p.deleted_at', '1970-01-01 08:00:00');

    if ($karantina === 'H') {
        $this->db
            ->join('komoditas_hewan kom', 'p.komoditas_id = kom.id', 'left')
            ->join('klasifikasi_hewan klas', 'p.klasifikasi_id = klas.id', 'left');
    } elseif ($karantina === 'I') {
        $this->db
            ->join('komoditas_ikan kom', 'p.komoditas_id = kom.id', 'left')
            ->join('klasifikasi_ikan klas', 'p.klasifikasi_id = klas.id', 'left');
    } elseif ($karantina === 'T') {
        $this->db
            ->join('komoditas_tumbuhan kom', 'p.komoditas_id = kom.id', 'left')
            ->join('klasifikasi_tumbuhan klas', 'p.klasifikasi_id = klas.id', 'left');
    }

    return $this->db->get()->result_array();
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
    $rows = $this->db
        ->select("
            ptk.tssm_id,
            p.no_dokumen,
            p.tanggal_dokumen,
            p.kota_kab_asal_id,
            mk.nama AS kota,
            mn.nama AS negara,
            mjd.nama AS jenis_dok,
            p.jenis_dokumen_id,
            p.efile
        ")
        ->from('ptk_dokumen p')
        ->join('ptk', 'p.ptk_id = ptk.id')
        ->join('master_jenis_dokumen mjd', 'p.jenis_dokumen_id = mjd.id')
        ->join('master_kota_kab mk', 'p.kota_kab_asal_id = mk.id', 'left')
        ->join('master_negara mn', 'p.negara_asal_id = mn.id', 'left')
        ->where('p.ptk_id', $ptk_id)
        ->get()
        ->result_array();

    if (!$rows) {
        return [];
    }

    foreach ($rows as &$row) {
        $row['penerbit'] = $row['kota'] ?: $row['negara'];

        if ($row['jenis_dokumen_id'] == '104') {
            $row['efile_url'] = $row['efile'];
        } else {
            $row['efile_url'] = is_null($row['tssm_id'])
                ? "https://api.karantinaindonesia.go.id/barantin-sys/" . $row['efile']
                : $row['efile'];
        }
    }

    return $rows;
}



    public function getSingmat($ptk_id)
    {
        $rows = $this->db
            ->select("
                psd.id                    AS id_ngasmat,
                ps.id                     AS id_tk4,
                psd.nomor                 AS nomor_ngasmat,
                psd.tanggal               AS tgl_tk2,
                psd.pengamatan_ke         AS pengamatan,
                psd.tgl_pengamatan        AS tgl_ngasmat,
                psd.gejala                AS tanda,

                mr.nama                   AS rekom,
                mrk.nama                  AS rekom_lanjut,

                psd.busuk                 AS bus,
                psd.rusak                 AS rus,
                psd.mati                  AS dead,
                psd.lainnya               AS lain,

                mperlakuan.nama           AS ttd,
                mp.nama                   AS ttd1,
                mpeg.nama                 AS input,
                psd.created_at            AS buat
            ")
            ->from('ptk p')
            ->join('pn_singmat ps', 'p.id = ps.ptk_id')
            ->join('pn_singmat_detil psd', 'ps.id = psd.pn_singmat_id')
            ->join('master_rekomendasi mr', 'psd.rekomendasi_id = mr.id')
            ->join('master_rekomendasi mrk', 'psd.rekomendasi_lanjut = mrk.id')
            ->join('master_pegawai mperlakuan', 'psd.user_ttd1_id = mperlakuan.id', 'left')
            ->join('master_pegawai mp', 'psd.user_ttd2_id = mp.id', 'left')
            ->join('master_pegawai mpeg', 'psd.user_id = mpeg.id', 'left')
            ->where('p.id', $ptk_id)
            ->order_by('psd.pengamatan_ke', 'ASC')
            ->get()
            ->result_array();

        if (!$rows) {
            return [];
        }

        foreach ($rows as &$row) {
            $kondisi = [];
            if ((int)$row['bus'] > 0) {
                $kondisi[] = 'busuk ' . $row['bus'];
            }
            if ((int)$row['rus'] > 0) {
                $kondisi[] = 'rusak ' . $row['rus'];
            }
            if ((int)$row['dead'] > 0) {
                $kondisi[] = 'mati ' . $row['dead'];
            }

            $row['kondisi'] = implode(', ', $kondisi);

            $row['link_singmat'] =
                'https://cert.karantinaindonesia.go.id/print_cert/singmat/k41/' .
                base64_encode($row['id_tk4'] . '_view');
        }

        return $rows;
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

    private function buildPembebasanLink(array $item)
{
    if (empty($item['id_p8'])) {
        return '#';
    }
    $kodeDok = !empty($item['kode_dok'])
        ? str_replace(['.', '-'], '', $item['kode_dok'])
        : '';

    $suffix = ($item['nomor_seri'] ?? '') === '*******'
        ? '_view'
        : '_view2';

    $dokId = $item['dokumen_karantina_id'] ?? null;
    $jenis = strtolower($item['jenis_karantina'] ?? '');

    if ($dokId && in_array($dokId, ['37','38','42','43'], true)) {
        $suffix = "_k{$jenis}{$suffix}";
    }

    $dataid = ($kodeDok ? $kodeDok.'/' : '')
        . base64_encode($item['id_p8'] . $suffix);

    return "https://cert.karantinaindonesia.go.id/print_cert/pembebasan/{$dataid}";
}


}
