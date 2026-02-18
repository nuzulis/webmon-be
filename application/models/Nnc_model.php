<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'core/BaseModelStrict.php';

class Nnc_model extends BaseModelStrict
{
    public function __construct()
    {
        parent::__construct();
    }

    public function getIds($f, $limit, $offset)
    {
        $this->db->select('p.id, MAX(p6.tanggal) as max_tanggal', false)
            ->from('ptk p')
            ->join('pn_penolakan p6', 'p.id = p6.ptk_id');

        $this->applyManualFilter($f);

        $sortMap = [
            'no_aju'    => 'p.no_aju',
            'tgl_nnc'   => 'max_tanggal',
            'nomor_nnc' => 'MAX(p6.nomor)',
            'nama_pengirim' => 'p.nama_pengirim',
        ];

        $this->applySorting(
            $f['sort_by'] ?? null,
            $f['sort_order'] ?? 'DESC',
            $sortMap,
            ['max_tanggal', 'DESC']
        );

        $this->db->group_by('p.id');
        $this->db->limit($limit, $offset);

        $query = $this->db->get();
        return $query ? array_column($query->result_array(), 'id') : [];
    }

    public function getByIds($ids)
    {
        if (empty($ids)) return [];
        
        $CI =& get_instance();
        $kar = strtoupper($CI->input->get('karantina', TRUE) ?? 'H');
        $komTable = $kar === 'H' ? 'komoditas_hewan' : ($kar === 'I' ? 'komoditas_ikan' : 'komoditas_tumbuhan');
        $quotedIds = implode(',', array_map([$this->db, 'escape'], $ids));

        $this->db->select("
            p.id,
            ANY_VALUE(p.no_aju) AS no_aju,
            ANY_VALUE(p.no_dok_permohonan) AS no_dok_permohonan,
            ANY_VALUE(p.tgl_dok_permohonan) AS tgl_dok_permohonan,
            ANY_VALUE(p6.nomor) AS nomor_penolakan,
            MAX(p6.tanggal) AS tgl_penolakan,
            ANY_VALUE(mu.nama) AS upt,
            REPLACE(REPLACE(ANY_VALUE(mu.nama), 'Balai Besar Karantina Hewan, Ikan, dan Tumbuhan', 'BBKHIT'), 'Balai Karantina Hewan, Ikan, dan Tumbuhan', 'BKHIT') as upt_raw,
            ANY_VALUE(mu.nama_satpel) AS nama_satpel,
            ANY_VALUE(mp.nama) AS petugas,
            ANY_VALUE(p.nama_pengirim) AS nama_pengirim,
            ANY_VALUE(p.nama_penerima) AS nama_penerima,
            ANY_VALUE(k_data.komoditas_list) AS komoditas,
            ANY_VALUE(k_data.volume_list) AS volume,
            ANY_VALUE(k_data.satuan_list) AS satuan,
            MAX(p6.alasan1) AS alasan1, MAX(p6.alasan2) AS alasan2, MAX(p6.alasan3) AS alasan3, MAX(p6.alasan4) AS alasan4,
            MAX(p6.alasan5) AS alasan5, MAX(p6.alasan6) AS alasan6, MAX(p6.alasan7) AS alasan7, MAX(p6.alasan8) AS alasan8,
            MAX(p6.alasan_lain) AS alasan_lain,
            ANY_VALUE(p6.specify1) as specify1, ANY_VALUE(p6.specify2) as specify2, ANY_VALUE(p6.specify3) as specify3, 
            ANY_VALUE(p6.specify4) as specify4, ANY_VALUE(p6.specify5) as specify5,
            ANY_VALUE(p6.consignment) as consignment_desc, ANY_VALUE(p6.information) as information,
            ANY_VALUE(p6.kepada) as kepada,
            ANY_VALUE(mn1.nama) AS asal, ANY_VALUE(mn3.nama) AS kota_asal, 
            ANY_VALUE(mn2.nama) AS tujuan, ANY_VALUE(mn4.nama) AS kota_tujuan
        ", false);

        $this->db->from('ptk p')
            ->join('pn_penolakan p6', 'p.id = p6.ptk_id')
            ->join('master_upt mu', 'p.kode_satpel = mu.id', 'left')
            ->join('master_pegawai mp', 'p6.user_ttd_id = mp.id', 'left')
            ->join('master_negara mn1', 'p.negara_asal_id = mn1.id', 'left')
            ->join('master_negara mn2', 'p.negara_tujuan_id = mn2.id', 'left')
            ->join('master_kota_kab mn3', 'p.kota_kab_asal_id = mn3.id', 'left')
            ->join('master_kota_kab mn4', 'p.kota_kab_tujuan_id = mn4.id', 'left');

        $this->db->join("(
            SELECT pk.ptk_id, 
                   GROUP_CONCAT(CONCAT('• ', kt.nama) SEPARATOR '<br>') as komoditas_list,
                   GROUP_CONCAT(pk.volumeP6 SEPARATOR '<br>') as volume_list,
                   GROUP_CONCAT(COALESCE(ms.nama, '-') SEPARATOR '<br>') as satuan_list
            FROM ptk_komoditas pk
            JOIN $komTable kt ON pk.komoditas_id = kt.id
            LEFT JOIN master_satuan ms ON pk.satuan_lain_id = ms.id
            WHERE pk.ptk_id IN ($quotedIds) AND pk.deleted_at = '1970-01-01 08:00:00'
            GROUP BY pk.ptk_id
        ) k_data", 'p.id = k_data.ptk_id', 'left', false);

        $this->db->where_in('p.id', $ids)->group_by('p.id')->order_by('tgl_penolakan', 'DESC');
        $res = $this->db->get();
        return $res ? $this->formatNncData($res->result_array()) : [];
    }

    public function getFullData($f)
    {
        $ids = $this->getIds($f, 10000, 0);
        if (empty($ids)) return [];

        $kar = strtoupper($f['karantina'] ?? 'H');
        $komTable = $kar === 'H' ? 'komoditas_hewan' : ($kar === 'I' ? 'komoditas_ikan' : 'komoditas_tumbuhan');

        $this->db->select("
            p.id, p.no_aju, p.no_dok_permohonan, p.tgl_dok_permohonan,
            p6.nomor AS nomor_penolakan, p6.tanggal AS tgl_penolakan, p6.kepada, p6.consignment as consignment_desc, p6.information,
            REPLACE(REPLACE(mu.nama, 'Balai Besar Karantina Hewan, Ikan, dan Tumbuhan', 'BBKHIT'), 'Balai Karantina Hewan, Ikan, dan Tumbuhan', 'BKHIT') as upt_raw,
            mu.nama_satpel, p.nama_pengirim, p.nama_penerima, mp.nama AS petugas,
            kt.nama AS komoditas, pk.volumeP6 AS volume, ms.nama AS satuan, pk.kode_hs,
            p6.alasan1, p6.alasan2, p6.alasan3, p6.alasan4, p6.alasan5, p6.alasan6, p6.alasan7, p6.alasan8, p6.alasan_lain,
            p6.specify1, p6.specify2, p6.specify3, p6.specify4, p6.specify5,
            mn1.nama AS asal, mn3.nama AS kota_asal, mn2.nama AS tujuan, mn4.nama AS kota_tujuan
        ", false);

        $this->db->from('ptk p')
            ->join('pn_penolakan p6', 'p.id = p6.ptk_id')
            ->join('master_upt mu', 'p.kode_satpel = mu.id', 'left')
            ->join('master_pegawai mp', 'p6.user_ttd_id = mp.id', 'left')
            ->join('ptk_komoditas pk', 'p.id = pk.ptk_id')
            ->join("$komTable kt", 'pk.komoditas_id = kt.id', 'left')
            ->join('master_satuan ms', 'pk.satuan_lain_id = ms.id', 'left')
            ->join('master_negara mn1', 'p.negara_asal_id = mn1.id', 'left')
            ->join('master_negara mn2', 'p.negara_tujuan_id = mn2.id', 'left')
            ->join('master_kota_kab mn3', 'p.kota_kab_asal_id = mn3.id', 'left')
            ->join('master_kota_kab mn4', 'p.kota_kab_tujuan_id = mn4.id', 'left');

        $this->db->where_in('p.id', $ids)->where('pk.deleted_at', '1970-01-01 08:00:00')->order_by('p6.tanggal', 'DESC');
        $res = $this->db->get();
        return $res ? $this->formatNncData($res->result_array(), true) : [];
    }

    public function countAll($f): int
    {
        $this->db->select('COUNT(DISTINCT p.id) as total')->from('ptk p')->join('pn_penolakan p6', 'p.id = p6.ptk_id');
        $this->applyManualFilter($f);
        $res = $this->db->get()->row();
        return $res ? (int) $res->total : 0;
    }

    private function applyManualFilter($f)
    {
        $this->db->where(['p.is_verifikasi' => '1', 'p.is_batal' => '0', 'p6.deleted_at' => '1970-01-01 08:00:00']);
        if (!empty($f['upt']) && !in_array(strtolower($f['upt']), ['all', 'semua', 'undefined'])) {
            $this->db->where((strlen($f['upt']) <= 4 ? 'p.upt_id' : 'p.kode_satpel'), $f['upt']);
        }
        if (!empty($f['karantina'])) $this->db->where('p.jenis_karantina', strtoupper(substr($f['karantina'], -1)));
        if (!empty($f['lingkup'])) $this->db->where('p.jenis_permohonan', strtoupper($f['lingkup']));
        if (!empty($f['start_date']) && !empty($f['end_date'])) {
            $this->db->where('p6.tanggal >=', $f['start_date'] . ' 00:00:00')->where('p6.tanggal <=', $f['end_date'] . ' 23:59:59');
        }
        if (!empty($f['search'])) {
            $s = $this->db->escape_like_str(trim($f['search']));
            $this->db->group_start()->like('p.no_aju', $s)->or_like('p6.nomor', $s)->or_like('p.nama_pengirim', $s)->or_like('p.nama_penerima', $s)
                ->or_where("EXISTS (SELECT 1 FROM ptk_komoditas pk WHERE pk.ptk_id = p.id AND pk.deleted_at = '1970-01-01 08:00:00' AND pk.nama_umum_tercetak LIKE '%$s%')")
                ->group_end();
        }
    }

    private function formatNncData(array $rows, bool $isExcel = false): array 
    {
        $alasanMap = [
            'alasan1' => 'Tidak dapat melengkapi dokumen persyaratan dalam waktu yang ditetapkan',
            'alasan2' => 'Persyaratan dokumen lain tidak dapat dipenuhi',
            'alasan3' => 'Berasal dari negara/daerah/tempat yang dilarang',
            'alasan4' => 'Berasal dari daerah wabah',
            'alasan5' => 'Jenis media pembawa dilarang',
            'alasan6' => 'Sanitasi tidak baik',
            'alasan7' => 'Ditemukan HPHK/HPIK/OPTK',
            'alasan8' => 'Tidak bebas OPTK',
        ];
        $specifyLabels = [
            1 => 'Prohibited goods: ', 2 => 'Problem with documentation (specify): ',
            3 => 'The goods were infected/infested/contaminated (specify): ',
            4 => 'The goods do not comply with food safety (specify): ',
            5 => 'The goods do not comply with other SPS (specify): '
        ];
        foreach ($rows as &$r) {
            $messages = [];
            foreach ($alasanMap as $key => $text) {
                if (!empty($r[$key]) && $r[$key] === '1') $messages[] = $isExcel ? "- $text" : "• $text";
            }
            if (!empty($r['alasan_lain']) && $r['alasan_lain'] !== '0') $messages[] = "Lain-lain: " . $r['alasan_lain'];
            for ($i = 1; $i <= 5; $i++) {
                $val = $r["specify$i"] ?? '';
                if (!empty($val)) $messages[] = ($isExcel ? $specifyLabels[$i] : "<strong>".$specifyLabels[$i]."</strong> ") . htmlspecialchars($val);
            }
            $r['nnc_reason'] = !empty($messages) ? implode($isExcel ? " | " : "<br>", $messages) : '-';
            $r['nnc_reason_text'] = strip_tags(str_replace('<br>', ' | ', $r['nnc_reason']));
            $r['consignment_full'] = "The " . ($r['consignment_desc'] ?? 'specified') . " lot was: " . ($r['information'] ?? 'Rejected');
            $r['upt_full'] = ($r['upt_raw'] ?? '') . ' - ' . ($r['nama_satpel'] ?? '');
        }
        return $rows;
    }
}