<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'models/BaseModelStrict.php';

class Penolakan_model extends BaseModelStrict
{
    private array $alasanMap = [
        'alasan1' => 'Tidak dapat melengkapi dokumen persyaratan dalam waktu yang ditetapkan',
        'alasan2' => 'Persyaratan dokumen lain tidak dapat dipenuhi dalam waktu yang ditetapkan',
        'alasan3' => 'Berasal dari negara/daerah/tempat yang dilarang',
        'alasan4' => 'Berasal dari negara/daerah tertular/berjangkit wabah penyakit',
        'alasan5' => 'Jenis media pembawa yang dilarang',
        'alasan6' => 'Sanitasi tidak baik / terkontaminasi / membahayakan',
        'alasan7' => 'Laporan pemeriksaan ditemukan HPHK/HPIK/OPTK',
        'alasan8' => 'Tidak bebas / tidak dapat dibebaskan dari HPHK/HPIK/OPTK',
    ];

    /* ================= STEP 1 — AMBIL ID ================= */
    public function getIds($f, $limit, $offset)
    {
        $this->db->select('p.id, MAX(p6.tanggal) AS last_tgl', false)
            ->from('ptk p')
            ->join('pn_penolakan p6', 'p.id = p6.ptk_id')
            ->where([
                'p.is_verifikasi' => '1',
                'p.is_batal'      => '0',
                'p6.deleted_at'   => '1970-01-01 08:00:00',
            ]);

        // Gunakan helper filter dari parent
        $this->applyCommonFilter($f, 'p');
        $this->applyDateFilter('p6.tanggal', $f);

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

        $this->db->select("
            p.id, p.no_aju, p.no_dok_permohonan, p.tgl_dok_permohonan,
            p6.nomor AS nomor_penolakan, p6.tanggal AS tgl_penolakan,
            mu.nama AS upt, mu.nama_satpel AS nama_satpel,
            p.nama_pengirim, p.nama_penerima,
            mn1.nama AS asal, mn3.nama AS kota_asal,
            mn2.nama AS tujuan, mn4.nama AS kota_tujuan,
            mp.nama AS petugas,
            p6.alasan1, p6.alasan2, p6.alasan3, p6.alasan4, 
            p6.alasan5, p6.alasan6, p6.alasan7, p6.alasan8, 
            p6.alasan_lain AS alasanlain,
            pkom.nama_umum_tercetak AS tercetak, 
            pkom.volume_lain AS volume,
            pkom.volumeP6 AS p6_vol,
            pkom.kode_hs AS hs,
            ms.nama AS satuan
        ", false);

        // Identifikasi Tabel Komoditas berdasarkan Karantina
        $tabelKom = 'komoditas_hewan';
        if ($karantina === 'KI') $tabelKom = 'komoditas_ikan';
        if ($karantina === 'KT') $tabelKom = 'komoditas_tumbuhan';

        $this->db->select("kom.nama AS komoditas");

        $this->db->from('ptk p')
            ->join('pn_penolakan p6', 'p.id = p6.ptk_id')
            ->join('master_upt mu', 'p.kode_satpel = mu.id')
            ->join('master_pegawai mp', 'p6.user_ttd_id = mp.id', 'left')
            ->join('ptk_komoditas pkom', 'p.id = pkom.ptk_id')
            ->join("$tabelKom kom", 'pkom.komoditas_id = kom.id')
            ->join('master_satuan ms', 'pkom.satuan_lain_id = ms.id', 'left')
            ->join('master_negara mn1', 'p.negara_asal_id = mn1.id')
            ->join('master_negara mn2', 'p.negara_tujuan_id = mn2.id', 'left')
            ->join('master_kota_kab mn3', 'p.kota_kab_asal_id = mn3.id', 'left')
            ->join('master_kota_kab mn4', 'p.kota_kab_tujuan_id = mn4.id', 'left');

        $this->db->where_in('p.id', $ids)
                 ->where('pkom.deleted_at', '1970-01-01 08:00:00')
                 ->order_by('p6.tanggal', 'DESC');

        $rows = $this->db->get()->result_array();

        foreach ($rows as &$r) {
            $r['alasan_string'] = $this->buildAlasan($r);
        }

        return $rows;
    }

   private function buildAlasan(array $row): string
    {
        $messages = [];
        foreach ($this->alasanMap as $key => $message) {
            // Cek jika field alasan1 - alasan8 bernilai '1'
            if (!empty($row[$key]) && $row[$key] === '1') {
                $messages[] = $message;
            }
        }

        if (!empty($row['alasanlain']) && $row['alasanlain'] !== '0') {
            $messages[] = "Lain-lain: " . strip_tags($row['alasanlain']);
        }

        // Gunakan PHP_EOL agar di Excel menjadi baris baru (Alt+Enter)
        return !empty($messages) ? implode(PHP_EOL, $messages) : "Tidak ada alasan yang diberikan";
    }

    
    public function countAll($f)
    {
        $this->db->select('COUNT(DISTINCT p.id) AS total', false)
            ->from('ptk p')
            ->join('pn_penolakan p6', 'p.id = p6.ptk_id')
            ->where([
                'p.is_verifikasi' => '1',
                'p.is_batal'      => '0',
                'p6.deleted_at'   => '1970-01-01 08:00:00',
            ]);

        $this->applyCommonFilter($f, 'p');
        $this->applyDateFilter('p6.tanggal', $f);

        $row = $this->db->get()->row();
        return $row ? (int) $row->total : 0;
    }

}