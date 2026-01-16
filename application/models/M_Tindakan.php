<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class M_Tindakan extends CI_Model
{
    public function __construct()
    {
        parent::__construct();
        $this->load->helper('url');
        $this->load->model('M_Ptk_Core', 'ptk');
    }

    /* =====================================================
     * TIMELINE UTAMA
     * ===================================================== */
    public function get_timeline($ptk_id, $kar)
    {
        $events = [];

        $events = array_merge($events, $this->ev_verifikasi($ptk_id));
        $events = array_merge($events, $this->ev_admin($ptk_id));
        $events = array_merge($events, $this->ev_fisik($ptk_id));
        $events = array_merge($events, $this->ev_kontainer($ptk_id));
        $events = array_merge($events, $this->ev_lab($ptk_id));

        $events = array_merge($events, $this->ev_masuk_instalasi($ptk_id));
        $events = array_merge($events, $this->ev_sp2mp($ptk_id));
        $events = array_merge($events, $this->ev_pengasingan($ptk_id));
        $events = array_merge($events, $this->ev_perlakuan($ptk_id));

        $events = array_merge($events, $this->ev_tangkapan($ptk_id));
        $events = array_merge($events, $this->ev_penahanan($ptk_id));
        $events = array_merge($events, $this->ev_penolakan($ptk_id));
        $events = array_merge($events, $this->ev_pemusnahan($ptk_id));

        $events = array_merge($events, $this->ev_serah($ptk_id));
        $events = array_merge($events, $this->ev_terima($ptk_id));

        $events = array_merge($events, $this->ev_pelepasan($ptk_id, $kar));
        $events = array_merge($events, $this->ev_kuitansi($ptk_id));

        $events = array_merge($events, $this->ev_status_ptk($ptk_id));

        usort($events, fn($a,$b) =>
            strtotime($a['waktu_input']) <=> strtotime($b['waktu_input'])
        );

        return $events;
    }

    /* =====================================================
     * DETEKSI SERAH TERIMA
     * ===================================================== */
    public function detect_serah_terima(string $ptkId): array
    {
        $asal = $this->db
            ->select('id, ptk_id_penerima')
            ->from('ba_penyerahan_mp')
            ->where('ptk_id', $ptkId)
            ->where('deleted_at', '1970-01-01 08:00:00')
            ->get()
            ->row_array();

        if ($asal) {
            return [
                'mode' => 'SERAH',
                'role' => 'ASAL',
                'ptk_asal_id' => $ptkId,
                'ptk_tujuan_id' => $asal['ptk_id_penerima']
            ];
        }

        $tujuan = $this->db
            ->select('id, ptk_id')
            ->from('ba_penyerahan_mp')
            ->where('ptk_id_penerima', $ptkId)
            ->where('deleted_at', '1970-01-01 08:00:00')
            ->get()
            ->row_array();

        if ($tujuan) {
            return [
                'mode' => 'SERAH',
                'role' => 'TUJUAN',
                'ptk_asal_id' => $tujuan['ptk_id'],
                'ptk_tujuan_id' => $ptkId
            ];
        }

        return ['mode' => 'NORMAL'];
    }

    /* =====================================================
     * GET PTK CONTEXT
     * ===================================================== */
    public function get_ptk_context(string $ptkId): ?array
    {
        $result = $this->db
            ->select('
                p.id,
                p.jenis_karantina,
                p.jenis_permohonan,
                p.upt_id,
                mu.nama AS upt_nama,
                p.tssm_id
            ')
            ->from('ptk p')
            ->join('master_upt mu', 'mu.id = p.upt_id', 'left')
            ->where('p.id', $ptkId)
            ->get()
            ->row_array();

        return $result ?: null;
    }

    /* =====================================================
     * GET HISTORY FLAT
     * ===================================================== */
    public function get_history_flat(string $ptkId): array
    {
        $ptk = $this->db
            ->select('id, jenis_karantina, jenis_permohonan')
            ->from('ptk')
            ->where('id', $ptkId)
            ->get()
            ->row_array();

        if (!$ptk) {
            return [];
        }

        $tblPelepasan = null;
        if ($ptk['jenis_karantina'] === 'H') {
            $tblPelepasan = 'pn_pelepasan_kh';
        } elseif ($ptk['jenis_karantina'] === 'I') {
            $tblPelepasan = 'pn_pelepasan_ki';
        } elseif ($ptk['jenis_karantina'] === 'T') {
            $tblPelepasan = 'pn_pelepasan_kt';
        }

        $sql = "
            SELECT
                p.id,
                p.jenis_permohonan,
                p.jenis_karantina,
                p34.nomor AS nomor_k34,
                p34.tanggal AS tgl_k34,
                p310.nomor AS nomor_k310,
                p310.tanggal AS tgl_k310,
                p6.nomor AS nomor_p6,
                p6.tanggal AS tgl_p6,
                p8.nomor AS nomor_p8,
                p8.tanggal AS tgl_p8
            FROM ptk p
            LEFT JOIN pn_masuk_instalasi p34
                ON p.id = p34.ptk_id
                AND p34.deleted_at = '1970-01-01 08:00:00'
            LEFT JOIN pn_sp2mp p310
                ON p.id = p310.ptk_id
                AND p310.deleted_at = '1970-01-01 08:00:00'
            LEFT JOIN pn_penolakan p6
                ON p.id = p6.ptk_id
                AND p6.deleted_at = '1970-01-01 08:00:00'
        ";

        if ($tblPelepasan && $this->db->table_exists($tblPelepasan)) {
            $sql .= " LEFT JOIN {$tblPelepasan} p8 ON p.id = p8.ptk_id ";
        } else {
            $sql .= " LEFT JOIN (SELECT NULL AS ptk_id, NULL AS nomor, NULL AS tanggal) p8 ON p.id = p8.ptk_id ";
        }

        $sql .= " WHERE p.id = ? LIMIT 1 ";

        $result = $this->db->query($sql, [$ptkId])->result_array();
        
        // Return array dengan index [0] untuk konsistensi dengan legacy code
        return !empty($result) ? $result : [
            [
                'id' => $ptkId,
                'jenis_permohonan' => $ptk['jenis_permohonan'],
                'jenis_karantina' => $ptk['jenis_karantina'],
                'nomor_k34' => null,
                'tgl_k34' => null,
                'nomor_k310' => null,
                'tgl_k310' => null,
                'nomor_p6' => null,
                'tgl_p6' => null,
                'nomor_p8' => null,
                'tgl_p8' => null
            ]
        ];
    }

    /* =====================================================
     * EVENT METHODS
     * ===================================================== */

    private function ev_verifikasi($ptk_id)
    {
        $p = $this->db->get_where('ptk', ['id' => $ptk_id])->row_array();
        if (!$p) return [];

        return [[
            'kode' => 'K11',
            'jenis' => 'verifikasi',
            'judul' => 'Verifikasi Permohonan',
            'nomor' => $p['no_dok_permohonan'],
            'tanggal' => $p['tgl_aju'],
            'waktu_input' => $p['tgl_dok_permohonan'],
            'user_input' => $p['petugas'],
            'user_ttd' => $p['petugas'],
            'status' => 'aktif',
            'alasan' => null,
            'owner' => null,
            'link' => $this->link('preborder/k11', $p['id']),
            'meta' => $p
        ]];
    }

    private function ev_admin($ptk_id)
    {
        $rows = $this->db
            ->select("a.*, ui.nama user_input, ut.nama user_ttd")
            ->from('pn_administrasi a')
            ->join('master_pegawai ui', 'ui.id=a.user_id', 'left')
            ->join('master_pegawai ut', 'ut.id=a.user_ttd_id', 'left')
            ->where('a.ptk_id', $ptk_id)
            ->where('a.deleted_at', '1970-01-01 08:00:00')
            ->get()
            ->result_array();

        return $this->map_rows(
            $rows,
            'K37A',
            'admin',
            'Pemeriksaan Administrasi',
            fn($r) => $this->link('pemeriksaan/k37a', $r['id'])
        );
    }

    private function ev_fisik($ptk_id)
    {
        $rows = $this->db
            ->select("f.*, ui.nama user_input, ut.nama user_ttd")
            ->from('pn_fisik_kesehatan f')
            ->join('master_pegawai ui', 'ui.id=f.user_id', 'left')
            ->join('master_pegawai ut', 'ut.id=f.user_ttd1_id', 'left')
            ->where('f.ptk_id', $ptk_id)
            ->get()
            ->result_array();

        return $this->map_rows(
            $rows,
            'K37B',
            'fisik',
            'Pemeriksaan Fisik / Kesehatan',
            fn($r) => $r['deleted_at'] != '1970-01-01 08:00:00'
                ? null
                : $this->link('pemeriksaan/k37b', $r['id']),
            fn($r) => $r['deleted_at'] != '1970-01-01 08:00:00'
                ? ['status' => 'batal', 'alasan' => $r['alasan_delete']]
                : []
        );
    }

    private function ev_kontainer($ptk_id)
    {
        $rows = $this->ptk->get_kontainer($ptk_id);
        if (!$rows) return [];

        $events = [];
        foreach ($rows as $r) {
            $events[] = [
                'kode' => 'CNT',
                'jenis' => 'kontainer',
                'judul' => 'Data Kontainer',
                'nomor' => $r['nomor'] ?? null,
                'tanggal' => null,
                'waktu_input' => $r['created_at'] ?? null,
                'user_input' => null,
                'user_ttd' => null,
                'status' => 'aktif',
                'alasan' => null,
                'owner' => null,
                'link' => null,
                'meta' => [
                    'nomor' => $r['nomor'] ?? null,
                    'segel' => $r['segel'] ?? null
                ]
            ];
        }
        return $events;
    }

    private function ev_lab($ptk_id)
    {
        return [];
    }

    private function ev_masuk_instalasi($ptk_id)
    {
        $rows = $this->db
            ->select("i.*, ui.nama user_input, ut.nama user_ttd")
            ->from('pn_masuk_instalasi i')
            ->join('master_pegawai ui', 'ui.id=i.user_id', 'left')
            ->join('master_pegawai ut', 'ut.id=i.user_ttd_id', 'left')
            ->where('i.ptk_id', $ptk_id)
            ->where('i.deleted_at', '1970-01-01 08:00:00')
            ->order_by('i.created_at', 'ASC')
            ->get()
            ->result_array();

        return $this->map_rows(
            $rows,
            'K34',
            'masuk_instalasi',
            'Masuk Instalasi',
            fn($r) => $this->link('pemeriksaan/k34', $r['id'])
        );
    }

    private function ev_sp2mp($ptk_id)
    {
        $rows = $this->db
            ->select('p.*, ui.nama AS user_input, ut.nama AS user_ttd')
            ->from('pn_sp2mp p')
            ->join('master_pegawai ui', 'ui.id = p.user_id', 'left')
            ->join('master_pegawai ut', 'ut.id = p.user_ttd_id', 'left')
            ->where('p.ptk_id', $ptk_id)
            ->order_by('p.created_at', 'ASC')
            ->get()
            ->result_array();

        $out = [];
        foreach ($rows as $r) {
            $isBatal = (!empty($r['deleted_at']) && $r['deleted_at'] !== '1970-01-01 08:00:00');

            $out[] = [
                'kode' => 'K310',
                'jenis' => 'sp2mp',
                'judul' => 'Persetujuan Pemindahan Media Pembawa (SP2MP)',
                'nomor' => $r['nomor'],
                'tanggal' => $isBatal ? $r['deleted_at'] : $r['tanggal'],
                'waktu_input' => $isBatal ? $r['deleted_at'] : $r['created_at'],
                'user_input' => $r['user_input'],
                'user_ttd' => $r['user_ttd'],
                'status' => $isBatal ? 'batal' : 'aktif',
                'alasan' => $isBatal ? ($r['alasan_delete'] ?? null) : null,
                'owner' => null,
                'link' => $isBatal ? null : $this->link('pemeriksaan/k310', $r['id']),
                'meta' => $r
            ];
        }
        return $out;
    }

    private function ev_pengasingan($ptk_id)
    {
        $rows = $this->db
            ->select("d.*, s.id singmat_id, ui.nama user_input, ut1.nama ttd1, ut2.nama ttd2")
            ->from('pn_singmat_detil d')
            ->join('pn_singmat s', 's.id=d.pn_singmat_id')
            ->join('master_pegawai ui', 'ui.id=d.user_id', 'left')
            ->join('master_pegawai ut1', 'ut1.id=d.user_ttd1_id', 'left')
            ->join('master_pegawai ut2', 'ut2.id=d.user_ttd2_id', 'left')
            ->where('s.ptk_id', $ptk_id)
            ->order_by('d.pengamatan_ke', 'ASC')
            ->get()
            ->result_array();

        return $this->map_rows(
            $rows,
            'K41',
            'pengasingan',
            'Pengasingan / Pengamatan',
            fn($r) => $this->link('singmat/k41', $r['singmat_id']),
            fn($r) => [
                'user_ttd' => trim(($r['ttd1'] ?? '') . ' / ' . ($r['ttd2'] ?? '')),
                'meta_kondisi' => [
                    'busuk' => $r['busuk'],
                    'rusak' => $r['rusak'],
                    'mati' => $r['mati'],
                    'lainnya' => $r['lainnya']
                ]
            ]
        );
    }

    private function ev_perlakuan($ptk_id)
    {
        $rows = $this->db
            ->where('ptk_id', $ptk_id)
            ->where('deleted_at', '1970-01-01 08:00:00')
            ->get('pn_perlakuan')
            ->result_array();

        return $this->map_rows(
            $rows,
            'K51',
            'perlakuan',
            'Perlakuan Karantina',
            fn($r) => $this->link('perlakuan/k51', $r['id'])
        );
    }

    private function ev_tangkapan($ptk_id)
    {
        $rows = $this->db
            ->get_where('tangkapan', ['ptk_id' => $ptk_id])
            ->result_array();

        return $this->map_rows($rows, 'TKP', 'tangkapan', 'Tangkapan', null);
    }

    private function ev_penahanan($ptk_id)
    {
        $rows = $this->db
            ->where('ptk_id', $ptk_id)
            ->where('deleted_at', '1970-01-01 08:00:00')
            ->get('pn_penahanan')
            ->result_array();

        return $this->map_rows(
            $rows,
            'K6',
            'penahanan',
            'Penahanan',
            fn($r) => $this->link_penahanan($r['nomor'], $r['id'])
        );
    }

    private function ev_penolakan($ptk_id)
    {
        $rows = $this->db
            ->where('ptk_id', $ptk_id)
            ->where('deleted_at', '1970-01-01 08:00:00')
            ->get('pn_penolakan')
            ->result_array();

        return $this->map_rows(
            $rows,
            'K7',
            'penolakan',
            'Penolakan',
            fn($r) => $this->link_penolakan($r['nomor'], $r['id'])
        );
    }

    private function ev_pemusnahan($ptk_id)
    {
        $rows = $this->db
            ->where('ptk_id', $ptk_id)
            ->where('deleted_at', '1970-01-01 08:00:00')
            ->get('pn_pemusnahan')
            ->result_array();

        return $this->map_rows(
            $rows,
            'K8',
            'pemusnahan',
            'Pemusnahan',
            fn($r) => $this->link_pemusnahan($r['nomor'], $r['id'])
        );
    }

    private function ev_serah($ptk_id)
    {
        $rows = $this->db
            ->where('ptk_id', $ptk_id)
            ->where('deleted_at', '1970-01-01 08:00:00')
            ->get('ba_penyerahan_mp')
            ->result_array();

        return $this->map_rows(
            $rows,
            'BA',
            'serah',
            'Serah Media Pembawa',
            fn($r) => $this->link('serahterima/ba', $r['id'])
        );
    }

    private function ev_terima($ptk_id)
    {
        $rows = $this->db
            ->where('ptk_id_penerima', $ptk_id)
            ->where('deleted_at', '1970-01-01 08:00:00')
            ->get('ba_penyerahan_mp')
            ->result_array();

        return $this->map_rows(
            $rows,
            'BA',
            'terima',
            'Terima Media Pembawa',
            fn($r) => $this->link('serahterima/ba', $r['id'])
        );
    }

    private function pelepasan_table($kar)
    {
        return match (strtoupper($kar)) {
            'H' => 'pn_pelepasan_kh',
            'I' => 'pn_pelepasan_ki',
            'T' => 'pn_pelepasan_kt',
            default => null
        };
    }

    private function ev_pelepasan($ptk_id, $kar)
    {
        $tbl = $this->pelepasan_table($kar);
        if (!$tbl || !$this->db->table_exists($tbl)) {
            return [];
        }

        $rows = $this->db
            ->select("p8.*, dok.kode_dok, p.jenis_permohonan, mp.nama AS user_ttd, mu.nama AS user_input")
            ->from("{$tbl} p8")
            ->join('ptk p', 'p.id = p8.ptk_id')
            ->join('master_dokumen_karantina dok', 'dok.id = p8.dokumen_karantina_id')
            ->join('master_pegawai mp', 'mp.id = p8.user_ttd_id', 'left')
            ->join('master_pegawai mu', 'mu.id = p8.user_id', 'left')
            ->where('p8.ptk_id', $ptk_id)
            ->get()
            ->result_array();

        $events = [];
        foreach ($rows as $r) {
            $isBatal = (!empty($r['deleted_at']) && $r['deleted_at'] !== '1970-01-01 08:00:00');

            $link = null;
            if (!$isBatal && !empty($r['id']) && !empty($r['kode_dok'])) {
                $folder = str_replace(['.', '-'], '', $r['kode_dok']);
                $suffix = (!empty($r['nomor_seri']) && $r['nomor_seri'] !== '*******') ? '_view2' : '_view';
                $dokKhusus = ['37', '38', '42', '43'];

                if (in_array($r['dokumen_karantina_id'], $dokKhusus, true)) {
                    $payload = $r['id'] . '_k' . strtolower($kar) . $suffix;
                } else {
                    $payload = $r['id'] . $suffix;
                }

                $link = "https://cert.karantinaindonesia.go.id/print_cert/pembebasan/{$folder}/" . base64_encode($payload);
            }

            $events[] = [
                'kode' => 'K15',
                'jenis' => 'pelepasan',
                'judul' => $isBatal ? 'Pelepasan (Dibatalkan)' : 'Pelepasan',
                'nomor' => $r['nomor'] ?? null,
                'tanggal' => $r['tanggal'] ?? null,
                'waktu_input' => $r['created_at'],
                'user_input' => $r['user_input'] ?? null,
                'user_ttd' => $r['user_ttd'] ?? null,
                'status' => $isBatal ? 'batal' : 'aktif',
                'alasan' => $isBatal ? ($r['alasan_delete'] ?? null) : null,
                'owner' => null,
                'link' => $link,
                'meta' => $r
            ];
        }

        return $events;
    }

    private function get_kuitansi($ptk_id)
    {
        $url = "https://simponi.karantinaindonesia.go.id/epnbp/kuitansi/list?ptk_id={$ptk_id}";

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_HTTPHEADER => ['Authorization: Basic bXJpZHdhbjpaPnV5JCx+NjR7KF42WDQm'],
        ]);

        $resp = curl_exec($ch);
        curl_close($ch);

        $json = json_decode($resp, true);
        return $json['data'] ?? [];
    }

    private function ev_kuitansi($ptk_id)
    {
        $rows = $this->get_kuitansi($ptk_id);
        if (!$rows) return [];

        $events = [];
        foreach ($rows as $r) {
            $events[] = [
                'kode' => 'PNBP',
                'jenis' => 'kuitansi',
                'judul' => 'Kuitansi PNBP',
                'nomor' => $r['nomor'] ?? null,
                'tanggal' => $r['tanggal'] ?? null,
                'waktu_input' => $r['tanggal'] ?? null,
                'user_input' => $r['nama_bendahara'] ?? null,
                'user_ttd' => null,
                'status' => $r['status_bill'] ?? null,
                'alasan' => null,
                'owner' => null,
                'link' => !empty($r['id']) ? "https://cert.karantinaindonesia.go.id/print_cert/payment/kuitansi/{$r['id']}" : null,
                'meta' => [
                    'kode_bill' => $r['kode_bill'] ?? null,
                    'ntpn' => $r['ntpn'] ?? null,
                    'total_pnbp' => $r['total_pnbp'] ?? 0,
                    'date_setor' => $r['date_setor'] ?? null,
                    'billing' => !empty($r['req_bill_id']) ? "https://cert.karantinaindonesia.go.id/print_cert/payment/billing/{$r['req_bill_id']}" : null
                ]
            ];
        }
        return $events;
    }

    private function ev_status_ptk($ptk_id)
    {
        $p = $this->db->get_where('ptk', ['id' => $ptk_id])->row_array();
        if (!$p || $p['is_batal'] != 1) return [];

        return [[
            'kode' => 'PTK',
            'jenis' => 'batal',
            'judul' => 'Permohonan Dibatalkan',
            'nomor' => null,
            'tanggal' => $p['deleted_at'],
            'waktu_input' => $p['deleted_at'],
            'user_input' => null,
            'user_ttd' => null,
            'status' => 'batal',
            'alasan' => $p['alasan_batal'],
            'owner' => null,
            'link' => null,
            'meta' => $p
        ]];
    }

    /* =====================================================
     * HELPER METHODS
     * ===================================================== */

    private function link($path, $id)
    {
        return "https://cert.karantinaindonesia.go.id/print_cert/{$path}/" . base64_encode($id . '_view');
    }

    private function link_penahanan($nomor, $id)
    {
        $kode = explode('-', $nomor)[3] ?? null;
        $map = ['K.6.1' => 'k61', 'K.6.2' => 'k62', 'K.6.3' => 'k63'];
        return $map[$kode] ?? null
            ? "https://cert.karantinaindonesia.go.id/print_cert/penahanan/{$map[$kode]}/" . base64_encode($id . '_view')
            : null;
    }

    private function link_penolakan($nomor, $id)
    {
        $kode = explode('-', $nomor)[3] ?? null;
        $map = ['K.7.1' => 'k71', 'K.7.2' => 'k72', 'K.7.3' => 'k73', 'K.7.4' => 'k74'];
        return $map[$kode] ?? null
            ? "https://cert.karantinaindonesia.go.id/print_cert/penolakan/{$map[$kode]}/" . base64_encode($id . '_view')
            : null;
    }

    private function link_pemusnahan($nomor, $id)
    {
        $kode = explode('-', $nomor)[3] ?? null;
        $map = ['K.8.1' => 'k81', 'K.8.2' => 'k82', 'K.8.3' => 'k83'];
        return $map[$kode] ?? null
            ? "https://cert.karantinaindonesia.go.id/print_cert/pemusnahan/{$map[$kode]}/" . base64_encode($id . '_view')
            : null;
    }

    private function map_rows($rows, $kode, $jenis, $judul, $linkFn = null, $extraFn = null)
    {
        $out = [];
        foreach ($rows as $r) {
            $base = [
                'kode' => $kode,
                'jenis' => $jenis,
                'judul' => $judul,
                'nomor' => $r['nomor'] ?? null,
                'tanggal' => $r['tanggal'] ?? null,
                'waktu_input' => $r['created_at'],
                'user_input' => $r['user_input'] ?? null,
                'user_ttd' => $r['user_ttd'] ?? null,
                'status' => 'aktif',
                'alasan' => null,
                'owner' => null,
                'link' => $linkFn ? $linkFn($r) : null,
                'meta' => $r
            ];
            if ($extraFn) {
                $base = array_merge($base, $extraFn($r));
            }
            $out[] = $base;
        }
        return $out;
    }
}