<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'core/BaseModelStrict.php';

class Pelepasan_model extends BaseModelStrict
{
    private function getTable($karantina)
    {
        return match (strtoupper($karantina)) {
            'H' => 'pn_pelepasan_kh',
            'I' => 'pn_pelepasan_ki',
            'T' => 'pn_pelepasan_kt',
            default => 'pn_pelepasan_kt',
        };
    }

    private function getTableKom($karantina)
    {
        return match (strtoupper($karantina)) {
            'H' => 'komoditas_hewan',
            'I' => 'komoditas_ikan',
            'T' => 'komoditas_tumbuhan',
            default => 'komoditas_tumbuhan',
        };
    }

    private function getTableKlas($karantina)
    {
        return match (strtoupper($karantina)) {
            'H' => 'klasifikasi_hewan',
            'I' => 'klasifikasi_ikan',
            'T' => 'klasifikasi_tumbuhan',
            default => 'klasifikasi_tumbuhan',
        };
    }

    public function getAll(array $f): array
    {
        $table     = $this->getTable($f['karantina']);
        $tabel_kom = $this->getTableKom($f['karantina']);

        $sql = "
            SELECT
                p.id, p.tssm_id, p.no_aju, p.no_dok_permohonan, p.tgl_dok_permohonan,
                p8.nomor AS nkt, p8.nomor_seri AS seri, p8.tanggal AS tanggal_lepas,
                mu.nama AS upt, mu.nama_satpel AS satpel,
                p.nama_pemohon, p.nama_pengirim, p.nama_penerima,
                mn1.nama AS asal, mn2.nama AS tujuan,
                mn3.nama AS kota_asal, mn4.nama AS kota_tujuan,
                GROUP_CONCAT(CONCAT('• ', pkom.nama_umum_tercetak) SEPARATOR '<br>') AS komoditas,
                GROUP_CONCAT(pkom.kode_hs               SEPARATOR '<br>') AS hs,
                GROUP_CONCAT(volume_lain SEPARATOR '<br>') as volume,
                GROUP_CONCAT(pkom.volumeP8              SEPARATOR '<br>') AS volumeP8,
                GROUP_CONCAT(COALESCE(sat.nama, '-')    SEPARATOR '<br>') AS satuan
            FROM ptk p
            JOIN $table p8
                ON p.id = p8.ptk_id
            LEFT JOIN ptk_komoditas pkom
                ON p.id = pkom.ptk_id AND pkom.deleted_at = '1970-01-01 08:00:00'
            LEFT JOIN $tabel_kom kom
                ON pkom.komoditas_id = kom.id
            LEFT JOIN master_satuan sat
                ON pkom.satuan_lain_id = sat.id
            LEFT JOIN master_upt mu
                ON p.kode_satpel = mu.id
            LEFT JOIN master_negara mn1
                ON p.negara_asal_id = mn1.id
            LEFT JOIN master_negara mn2
                ON p.negara_tujuan_id = mn2.id
            LEFT JOIN master_kota_kab mn3
                ON p.kota_kab_asal_id = mn3.id
            LEFT JOIN master_kota_kab mn4
                ON p.kota_kab_tujuan_id = mn4.id
            WHERE p.is_verifikasi = '1'
              AND p.is_batal       = '0'
              AND p8.deleted_at    = '1970-01-01 08:00:00'
              AND p8.nomor_seri   != '*******'
        ";

        $params = [];

        if (!empty($f['upt']) && !in_array(strtolower($f['upt']), ['all', 'semua', 'undefined'], true)) {
            $field    = (substr($f['upt'], -2) === '00') ? 'p.upt_id' : 'p.kode_satpel';
            $sql     .= " AND $field = ?";
            $params[] = $f['upt'];
        }

        if (!empty($f['lingkup']) && strtolower($f['lingkup']) !== 'all') {
            $sql     .= " AND p.jenis_permohonan = ?";
            $params[] = strtoupper($f['lingkup']);
        }

        if (!empty($f['start_date']) && !empty($f['end_date'])) {
            $sql     .= " AND p8.tanggal BETWEEN ? AND ?";
            $params[] = $f['start_date'] . ' 00:00:00';
            $params[] = $f['end_date']   . ' 23:59:59';
        }

        $sql .= "
            GROUP BY p.id, p8.nomor, p8.nomor_seri, p8.tanggal,
                     mu.nama, mu.nama_satpel,
                     mn1.nama, mn2.nama, mn3.nama, mn4.nama
            ORDER BY p8.tanggal DESC
        ";

        $this->db->reconnect();
        $query = $this->db->query($sql, $params);
        return $query ? $query->result_array() : [];
    }
    public function getFullData($f)
    {
        $table      = $this->getTable($f['karantina']);
        $tabel_kom  = $this->getTableKom($f['karantina']);
        $tabel_klas = $this->getTableKlas($f['karantina']);
        $this->db->reconnect();

        $this->db->select("
            p.id, p.tssm_id, p.no_aju, p.tgl_aju, p.no_dok_permohonan, p.tgl_dok_permohonan,
            p8.nomor AS nkt, p8.nomor_seri AS seri, p8.tanggal AS tanggal_lepas,
            mu.nama AS upt, mu.nama_satpel AS satpel,
            p.nama_tempat_pemeriksaan, p.alamat_tempat_pemeriksaan, p.tgl_pemeriksaan,
            p.nama_pemohon, p.alamat_pemohon, p.nomor_identitas_pemohon,
            p.nama_pengirim, p.alamat_pengirim, p.nomor_identitas_pengirim,
            p.nama_penerima, p.alamat_penerima, p.nomor_identitas_penerima,
            mn1.nama AS asal, mn3.nama AS kota_asal, pel_muat.nama AS pelabuhanasal,
            mn2.nama AS tujuan, mn4.nama AS kota_tujuan, pel_bongkar.nama AS pelabuhantuju,
            moda.nama AS moda, p.nama_alat_angkut_terakhir, p.no_voyage_terakhir,
            mjk.deskripsi AS kemas, p.jumlah_kemasan AS total_kemas, p.tanda_khusus,
            klas.deskripsi AS klasifikasi, kom.nama AS komoditas, pkom.nama_umum_tercetak, pkom.kode_hs AS hs,
            pkom.volumeP1 AS vol_p1, pkom.volumeP2 AS vol_p2, pkom.volumeP3 AS vol_p3, pkom.volumeP4 AS vol_p4,
            pkom.volumeP5 AS vol_p5, pkom.volumeP6 AS vol_p6, pkom.volumeP7 AS vol_p7, pkom.volumeP8 AS vol_p8,
            pkom.volume_lain,
            pkom.nettoP1 AS net_p1, pkom.nettoP2 AS net_p2, pkom.nettoP3 AS net_p3, pkom.nettoP4 AS net_p4,
            pkom.nettoP5 AS net_p5, pkom.nettoP6 AS net_p6, pkom.nettoP7 AS net_p7, pkom.nettoP8 AS net_p8,
            pkom.satuan_netto_id, ms_netto.nama AS satuan_netto,
            pkom.satuan_bruto_id, ms_bruto.nama AS satuan_bruto,
            pkom.satuan_lain_id, ms_lain.nama AS satuan_lain,
            pkom.harga_rp,
            (SELECT GROUP_CONCAT(CONCAT(nomor, ' (', segel, ')') SEPARATOR '; ')
             FROM ptk_kontainer WHERE ptk_id = p.id AND deleted_at = '1970-01-01 08:00:00') AS kontainer_string,
            (SELECT GROUP_CONCAT(CONCAT(mjd.nama, ':', pdok.no_dokumen) SEPARATOR '; ')
             FROM ptk_dokumen pdok
             JOIN master_jenis_dokumen mjd ON pdok.jenis_dokumen_id = mjd.id
             WHERE pdok.ptk_id = p.id AND pdok.deleted_at = '1970-01-01 08:00:00') AS dokumen_pendukung_string
        ", false);

        $this->db->from('ptk p')
            ->join("$table p8", 'p.id = p8.ptk_id')
            ->join('ptk_komoditas pkom', "p.id = pkom.ptk_id AND pkom.deleted_at = '1970-01-01 08:00:00'", 'left')
            ->join("$tabel_kom kom",  'pkom.komoditas_id = kom.id', 'left')
            ->join("$tabel_klas klas", 'pkom.klasifikasi_id = klas.id', 'left')
            ->join('master_upt mu', 'p.kode_satpel = mu.id', 'left')
            ->join('master_satuan ms_netto', 'pkom.satuan_netto_id = ms_netto.id', 'left')
            ->join('master_satuan ms_bruto', 'pkom.satuan_bruto_id = ms_bruto.id', 'left')
            ->join('master_satuan ms_lain',  'pkom.satuan_lain_id = ms_lain.id', 'left')
            ->join('master_pelabuhan pel_muat',    'p.pelabuhan_muat_id = pel_muat.id', 'left')
            ->join('master_pelabuhan pel_bongkar', 'p.pelabuhan_bongkar_id = pel_bongkar.id', 'left')
            ->join('master_moda_alat_angkut moda', 'p.moda_alat_angkut_terakhir_id = moda.id', 'left')
            ->join('master_jenis_kemasan mjk', 'p.kemasan_id = mjk.id', 'left')
            ->join('master_negara mn1', 'p.negara_asal_id = mn1.id', 'left')
            ->join('master_negara mn2', 'p.negara_tujuan_id = mn2.id', 'left')
            ->join('master_kota_kab mn3', 'p.kota_kab_asal_id = mn3.id', 'left')
            ->join('master_kota_kab mn4', 'p.kota_kab_tujuan_id = mn4.id', 'left');

        $this->applyFilter($f);
        $this->db->order_by('p.id',    'ASC');
        $this->db->order_by('pkom.id', 'ASC');

        $query = $this->db->get();

        log_message('debug', '[Pelepasan_model::getFullData] SQL: ' . $this->db->last_query());

        if (!$query) {
            log_message('error', '[Pelepasan_model::getFullData] Query failed');
            return [];
        }

        $rows = $query->result_array();
        log_message('debug', '[Pelepasan_model::getFullData] Row count: ' . count($rows));

        return $rows;
    }

    private function applyFilter($f)
    {
        $this->db->where([
            'p.is_verifikasi' => '1',
            'p.is_batal'      => '0',
            'p8.deleted_at'   => '1970-01-01 08:00:00',
        ])->where("p8.nomor_seri != '*******'", null, false);

        if (!empty($f['upt']) && !in_array(strtolower($f['upt']), ['all', 'semua', 'undefined'], true)) {
            $field = (substr($f['upt'], -2) === '00') ? 'p.upt_id' : 'p.kode_satpel';
            $this->db->where($field, $f['upt']);
        }

        if (!empty($f['lingkup']) && strtolower($f['lingkup']) !== 'all') {
            $this->db->where('p.jenis_permohonan', strtoupper($f['lingkup']));
        }

        $this->applyDateFilter('p8.tanggal', $f);

    }
}