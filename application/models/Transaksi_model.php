<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'core/BaseModelStrict.php';

class Transaksi_model extends BaseModelStrict
{
    public function __construct()
    {
        parent::__construct();
    }

    private function applyKarantinaJoin(string $karantina): bool
    {
        $k = strtolower($karantina);
        if ($k === 'kh' || $k === 'h') {
            $this->db->join('komoditas_hewan kom', 'pkom.komoditas_id = kom.id');
            $this->db->join('klasifikasi_hewan klas', 'pkom.klasifikasi_id = klas.id');
            return true;
        } elseif ($k === 'ki' || $k === 'i') {
            $this->db->join('komoditas_ikan kom', 'pkom.komoditas_id = kom.id');
            $this->db->join('klasifikasi_ikan klas', 'pkom.klasifikasi_id = klas.id');
            return true;
        } elseif ($k === 'kt' || $k === 't') {
            $this->db->join('komoditas_tumbuhan kom', 'pkom.komoditas_id = kom.id');
            $this->db->join('klasifikasi_tumbuhan klas', 'pkom.klasifikasi_id = klas.id');
            return true;
        }
        return false;
    }

    private function buildBaseQuery(array $f): void
    {
        $this->db->from('ptk p')
            ->join('master_upt mu',        'p.kode_satpel = mu.id',          'left')
            ->join('ptk_komoditas pkom',   'p.id = pkom.ptk_id',             'left')
            ->join('master_satuan ms',     'pkom.satuan_lain_id = ms.id',    'left')
            ->join('master_negara mn1',    'p.negara_asal_id = mn1.id',      'left')
            ->join('master_negara mn2',    'p.negara_tujuan_id = mn2.id',    'left')
            ->join('master_kota_kab mn3',  'p.kota_kab_asal_id = mn3.id',   'left')
            ->join('master_kota_kab mn4',  'p.kota_kab_tujuan_id = mn4.id', 'left');

        $this->applyKarantinaJoin($f['karantina'] ?? '');

        $this->db->where([
            'p.is_verifikasi' => '1',
            'p.is_batal'      => '0',
            'p.deleted_at'    => '1970-01-01 08:00:00',
            'pkom.deleted_at' => '1970-01-01 08:00:00',
        ]);

        if (!empty($f['upt']) && !in_array(strtolower($f['upt']), ['all', 'semua', 'undefined'])) {
            if (strlen($f['upt']) <= 4) {
                $this->db->group_start()
                         ->where('p.upt_id', $f['upt'])
                         ->or_like('p.kode_satpel', $f['upt'], 'after')
                         ->group_end();
            } else {
                $this->db->where('p.kode_satpel', $f['upt']);
            }
        }

        if (!empty($f['karantina']) && !in_array(strtolower($f['karantina']), ['all', ''])) {
            $this->db->where('p.jenis_karantina', substr(strtoupper($f['karantina']), 0, 1));
        }

        $lingkup = $f['lingkup'] ?? ($f['permohonan'] ?? '');
        if (!empty($lingkup) && !in_array(strtolower($lingkup), ['all', 'semua', ''])) {
            $this->db->where('p.jenis_permohonan', strtoupper($lingkup));
        }

        $start = !empty($f['start_date']) ? $f['start_date'] : date('Y-m-d');
        $end   = !empty($f['end_date'])   ? $f['end_date']   : date('Y-m-d');
        $this->db->where('p.tgl_dok_permohonan >=', $start . ' 00:00:00');
        $this->db->where('p.tgl_dok_permohonan <=', $end   . ' 23:59:59');
    }

    private function komoditasCol(string $karantina): string
    {
        return !in_array(strtolower($karantina), ['all', '', 'semua', 'undefined'])
            ? 'kom.nama'
            : 'pkom.nama_umum_tercetak';
    }

    public function getAll(array $f): array
    {
        $komoditasCol = $this->komoditasCol($f['karantina'] ?? '');

        $this->db->select("
            p.id,
            ANY_VALUE(IF(p.tssm_id IS NOT NULL, 'SSM', 'PTK')) AS sumber,
            ANY_VALUE(p.no_aju) AS no_aju,
            ANY_VALUE(p.tgl_aju) AS tgl_aju,
            ANY_VALUE(p.no_dok_permohonan) AS no_dok,
            ANY_VALUE(p.tgl_dok_permohonan) AS tgl_dok,
            ANY_VALUE(mu.nama) AS upt,
            ANY_VALUE(mu.nama_satpel) AS satpel,
            ANY_VALUE(p.nama_pengirim) AS pengirim,
            ANY_VALUE(p.nama_penerima) AS penerima,
            ANY_VALUE(CONCAT(COALESCE(mn1.nama,''), ' - ', COALESCE(mn3.nama,''))) AS asal_kota,
            ANY_VALUE(CONCAT(COALESCE(mn2.nama,''), ' - ', COALESCE(mn4.nama,''))) AS tujuan_kota,
            ANY_VALUE(p.nama_tempat_pemeriksaan) AS tempat_periksa,
            ANY_VALUE(p.tgl_pemeriksaan) AS tgl_periksa,
            ANY_VALUE(p.alamat_tempat_pemeriksaan) AS alamat_periksa,
            GROUP_CONCAT({$komoditasCol} SEPARATOR '<br>') AS komoditas,
            GROUP_CONCAT(pkom.kode_hs SEPARATOR '<br>') AS hs,
            GROUP_CONCAT(pkom.volume_lain SEPARATOR '<br>') AS volume,
            GROUP_CONCAT(COALESCE(ms.nama, '-') SEPARATOR '<br>') AS satuan
        ", false);

        $this->buildBaseQuery($f);
        $this->db->group_by('p.id');

        $query = $this->db->get();
        return $query ? $query->result_array() : [];
    }

    public function getAllForExcel(array $f): array
    {
        $komoditasCol = $this->komoditasCol($f['karantina'] ?? '');

        $this->db->select("
            p.id,
            IF(p.tssm_id IS NOT NULL, 'SSM', 'PTK') AS sumber,
            p.no_aju,
            p.tgl_aju,
            p.no_dok_permohonan AS no_dok,
            p.tgl_dok_permohonan AS tgl_dok,
            mu.nama AS upt,
            mu.nama_satpel AS satpel,
            p.nama_pengirim AS pengirim,
            p.nama_penerima AS penerima,
            CONCAT(COALESCE(mn1.nama,''), ' - ', COALESCE(mn3.nama,'')) AS asal_kota,
            CONCAT(COALESCE(mn2.nama,''), ' - ', COALESCE(mn4.nama,'')) AS tujuan_kota,
            p.nama_tempat_pemeriksaan AS tempat_periksa,
            p.tgl_pemeriksaan AS tgl_periksa,
            p.alamat_tempat_pemeriksaan AS alamat_periksa,
            {$komoditasCol} AS komoditas,
            pkom.kode_hs AS hs,
            pkom.volume_lain AS volume,
            COALESCE(ms.nama, '-') AS satuan
        ", false);

        $this->buildBaseQuery($f);

        $query = $this->db->get();
        return $query ? $query->result_array() : [];
    }
}
