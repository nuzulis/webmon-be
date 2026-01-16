<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'models/BaseModelStrict.php';

class Pemusnahan_model extends BaseModelStrict
{
    private array $alasanMap = [
        'alasan1' => 'Media Pembawa adalah jenis yang dilarang pemasukannya',
        'alasan2' => 'Media pembawa rusak/busuk',
        'alasan3' => 'Berasal dari negara/daerah tertular/berjangkit wabah HPHK/HPIK/OPTK',
        'alasan4' => 'Tidak dapat disembuhkan/dibebaskan setelah perlakuan',
        'alasan5' => 'Tidak dikeluarkan dari wilayah RI dalam waktu yang ditentukan',
        'alasan6' => 'Tidak memenuhi persyaratan keamanan dan mutu pangan/pakan',
    ];

    /* =====================================================
     * STEP 1 — AMBIL ID PTK SAJA (Optimized)
     * ===================================================== */
    public function getIds($f, $limit, $offset)
    {
        $this->db->select('p.id, MAX(mus.tanggal) AS last_tgl', false)
            ->from('pn_pemusnahan mus')
            ->join('ptk p', 'mus.ptk_id = p.id') // Menggunakan alias 'p' agar sinkron dengan parent
            ->where([
                'mus.deleted_at'  => '1970-01-01 08:00:00',
                'p.is_batal'      => '0',
                'mus.dokumen_karantina_id' => '35', // Dokumen P7
            ]);

        // applyCommonFilter akan menyuntikkan 'p.jenis_karantina' dll
        $this->applyCommonFilter($f, 'p');
        
        if (!empty($f['start_date']) && !empty($f['end_date'])) {
            $this->db->where('mus.tanggal >=', $f['start_date'] . ' 00:00:00');
            $this->db->where('mus.tanggal <=', $f['end_date'] . ' 23:59:59');
        }

        $this->db->group_by('p.id')
            ->order_by('last_tgl', 'DESC')
            ->limit($limit, $offset);

        $res = $this->db->get()->result_array();
        return array_column($res, 'id');
    }

    public function getByIds($ids, $is_export = false)
    {
        if (empty($ids)) return [];

        $karantina = strtoupper($this->input->get('karantina', true));
        $tabel_kom = "komoditas_" . ($karantina == 'H' ? 'hewan' : ($karantina == 'I' ? 'ikan' : 'tumbuhan'));
        $quotedIds = "'" . implode("','", $ids) . "'";

        // Base Selection
        $select = "
            p.id, p.tssm_id,
            mus.nomor, mus.tanggal AS tgl_p7,
            mu.nama AS nama_upt, mu.nama_satpel AS nama_satpel,
            p.nama_pengirim, p.nama_penerima,
            mn1.nama_en AS negara_asal, kab1.nama AS kota_kab_asal,
            mn2.nama_en AS negara_tujuan, kab2.nama AS kota_kab_tujuan,
            bam.petugas_pelaksana AS petugas, bam.tempat_musnah AS tempat, bam.metode_musnah AS metode,
            mus.alasan1, mus.alasan2, mus.alasan3, mus.alasan4, mus.alasan5, mus.alasan6,
            mus.alasan_lain AS alasanlain
        ";

        if ($is_export) {
            // VERSI EXCEL: Baris Komoditas Terurai
            $select .= ", kom.nama AS komoditas, pkom.nama_umum_tercetak AS tercetak, 
                         pkom.kode_hs AS hs, pkom.volume_lain AS volume, 
                         pkom.volumeP7 AS p7, ms.nama AS satuan";
        } else {
            // VERSI WEB: Digabung agar tabel ringkas
            $select .= ", k.komoditas, k.hs, k.volume, k.p7, k.satuan";
        }

        $this->db->select($select, false)
            ->from('pn_pemusnahan mus')
            ->join('ptk p', 'mus.ptk_id = p.id')
            ->join('master_upt mu', 'p.kode_satpel = mu.id')
            ->join('master_negara mn1', 'p.negara_asal_id = mn1.id', 'left')
            ->join('master_negara mn2', 'p.negara_tujuan_id = mn2.id', 'left')
            ->join('master_kota_kab kab1', 'p.kota_kab_asal_id = kab1.id', 'left')
            ->join('master_kota_kab kab2', 'p.kota_kab_tujuan_id = kab2.id', 'left')
            ->join('pn_pemusnahan bam', "mus.id = bam.pn_pemusnahan_id AND bam.dokumen_karantina_id = '36'", 'left');

        if ($is_export) {
            $this->db->join('ptk_komoditas pkom', 'p.id = pkom.ptk_id')
                     ->join("$tabel_kom kom", 'pkom.komoditas_id = kom.id')
                     ->join('master_satuan ms', 'pkom.satuan_lain_id = ms.id', 'left')
                     ->where('pkom.deleted_at', '1970-01-01 08:00:00');
        } else {
            $this->db->join("(
                SELECT 
                    pkom.ptk_id,
                    GROUP_CONCAT(kom.nama SEPARATOR '<br>') AS komoditas,
                    GROUP_CONCAT(pkom.kode_hs SEPARATOR '<br>') AS hs,
                    GROUP_CONCAT(pkom.volume_lain SEPARATOR '<br>') AS volume,
                    GROUP_CONCAT(pkom.volumeP7 SEPARATOR '<br>') AS p7,
                    GROUP_CONCAT(ms.nama SEPARATOR '<br>') AS satuan
                FROM ptk_komoditas pkom
                JOIN $tabel_kom kom ON pkom.komoditas_id = kom.id
                LEFT JOIN master_satuan ms ON pkom.satuan_lain_id = ms.id
                WHERE pkom.deleted_at = '1970-01-01 08:00:00'
                AND pkom.ptk_id IN ($quotedIds)
                GROUP BY pkom.ptk_id
            ) k", 'p.id = k.ptk_id', 'left');
        }

        $this->db->where("p.id IN ($quotedIds)", NULL, FALSE)
                 ->where('mus.dokumen_karantina_id', '35')
                 ->order_by('p.id', 'ASC')
                 ->order_by('tgl_p7', 'DESC');

        $rows = $this->db->get()->result_array();

        foreach ($rows as &$r) {
            $r['alasan_string'] = $this->buildAlasanString($r);
        }

        return $rows;
    }

    private function buildAlasanString(array $row): string
{
    $out = [];
    $map = [
        'alasan1' => "Media Pembawa dilarang pemasukannya",
        'alasan2' => "Media pembawa rusak/busuk",
        'alasan3' => "Berasal dari daerah wabah HPHK/HPIK/OPTK",
        'alasan4' => "Tidak dapat dibebaskan setelah perlakuan",
        'alasan5' => "Tidak dikeluarkan dari RI > 3 hari setelah penolakan",
        'alasan6' => "Tidak memenuhi syarat keamanan mutu pangan/pakan"
    ];

    foreach ($map as $field => $label) {
        if (!empty($row[$field]) && $row[$field] == '1') {
            $out[] = "- " . $label;
        }
    }

    if (!empty($row['alasanlain']) && $row['alasanlain'] !== '0') {
        $out[] = "- Lain-lain: " . strip_tags($row['alasanlain']);
    }

    // Gunakan PHP_EOL agar di Excel bisa otomatis ganti baris (wrap text)
    return $out ? implode(PHP_EOL, $out) : '-';
}

    public function countAll($f)
    {
        $this->db->from('pn_pemusnahan mus')
            ->join('ptk p', 'mus.ptk_id = p.id') // Gunakan alias p
            ->where([
                'mus.deleted_at' => '1970-01-01 08:00:00',
                'p.is_batal'   => '0',
                'mus.dokumen_karantina_id' => '35',
            ]);

        $this->applyCommonFilter($f, 'p');

        if (!empty($f['start_date']) && !empty($f['end_date'])) {
            $this->db->where('mus.tanggal >=', $f['start_date'] . ' 00:00:00');
            $this->db->where('mus.tanggal <=', $f['end_date'] . ' 23:59:59');
        }

        return $this->db->count_all_results();
    }

    private function buildAlasan(array $row): string
    {
        $out = [];
        foreach ($this->alasanMap as $field => $label) {
            if (!empty($row[$field]) && $row[$field] == '1') {
                $out[] = $label;
            }
        }
        if (!empty($row['alasanlain']) && $row['alasanlain'] !== '0') {
            $out[] = 'Lain-lain: ' . strip_tags($row['alasanlain']);
        }
        return $out ? implode('<br>', $out) : '-';
    }
}