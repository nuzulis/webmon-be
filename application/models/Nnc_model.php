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
        if (!empty($f['search'])) {
            $this->db->join('ptk_komoditas pkom', 'p.id = pkom.ptk_id AND pkom.deleted_at = "1970-01-01 08:00:00"', 'left');
        }

        $this->applyManualFilter($f);

        $sortMap = [
            'no_aju'        => 'p.no_aju',
            'tgl_nnc'       => 'max_tanggal',
            'nomor_nnc'     => 'MAX(p6.nomor)',
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
        $quotedIds = implode(',', array_map([$this->db, 'escape'], $ids));
        $this->db->select("
            p.id, 
            p.no_aju, 
            p.no_dok_permohonan as no_dok, 
            p.tgl_dok_permohonan, 
            p.nama_pengirim, 
            p.nama_penerima,
            REPLACE(REPLACE(mu.nama, 'Balai Besar Karantina Hewan, Ikan, dan Tumbuhan', 'BBKHIT'), 
                'Balai Karantina Hewan, Ikan, dan Tumbuhan', 'BKHIT') as upt_raw,
            mu.nama_satpel,
            
            p6.tanggal AS tgl_penolakan, 
            p6.nomor AS nomor_penolakan,
            p6.information, 
            p6.consignment as consignment_desc, 
            p6.kepada,
            
            p6.specify1, p6.specify2,
            p6.specify3, p6.specify4,
            p6.specify5,

            mp.nama AS petugas, 
            mn1.nama AS asal, mn3.nama AS kota_asal, 
            mn2.nama AS tujuan, mn4.nama AS kota_tujuan
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
            SELECT ptk_id, 
                   GROUP_CONCAT(CONCAT('• ', nama_umum_tercetak, ' (', volumeP6, ' ', COALESCE(sat.nama, ''), ')') SEPARATOR '<br>') as komoditas
            FROM ptk_komoditas pk
            LEFT JOIN master_satuan sat ON pk.satuan_lain_id = sat.id
            WHERE ptk_id IN ($quotedIds) AND pk.deleted_at = '1970-01-01 08:00:00'
            GROUP BY ptk_id
        ) k", 'p.id = k.ptk_id', 'left', false);

        $this->db->select('k.komoditas');
        
        $this->db->where_in('p.id', $ids);
        $this->db->order_by('p6.tanggal', 'DESC');

        $rows = $this->db->get()->result_array();
        return $this->formatNncData($rows);
    }

    public function countAll($f): int
    {
        $this->db->select('COUNT(DISTINCT p.id) as total');
        $this->db->from('ptk p')
            ->join('pn_penolakan p6', 'p.id = p6.ptk_id');

        if (!empty($f['search'])) {
             $this->db->join('ptk_komoditas pkom', 'p.id = pkom.ptk_id AND pkom.deleted_at = "1970-01-01 08:00:00"', 'left');
        }

        $this->applyManualFilter($f);
        
        $res = $this->db->get()->row();
        return $res ? (int) $res->total : 0;
    }

    public function getFullData($f)
    {
        $ids = $this->getIds($f, 100000, 0);
        if (empty($ids)) return [];
        $this->db->select("
            p.no_aju, 
            p.nama_pengirim, p.nama_penerima,
            REPLACE(REPLACE(mu.nama, 'Balai Besar Karantina Hewan, Ikan, dan Tumbuhan', 'BBKHIT'), 
                'Balai Karantina Hewan, Ikan, dan Tumbuhan', 'BKHIT') as upt_raw,
            mu.nama_satpel,
            
            p6.tanggal AS tgl_penolakan, 
            p6.nomor AS nomor_penolakan,
            p6.information, 
            p6.consignment as consignment_desc, 
            p6.kepada,
            p6.specify1, p6.specify2, p6.specify3, p6.specify4, p6.specify5,

            mp.nama AS petugas, 
            mn1.nama AS asal, mn3.nama AS kota_asal, 
            mn2.nama AS tujuan, mn4.nama AS kota_tujuan,

            pkom.nama_umum_tercetak as komoditas,
            pkom.kode_hs,
            pkom.volumeP6 as volume,
            ms.nama as satuan
        ", false);

        $this->db->from('ptk p')
            ->join('pn_penolakan p6', 'p.id = p6.ptk_id')
            ->join('master_upt mu', 'p.kode_satpel = mu.id', 'left')
            ->join('master_pegawai mp', 'p6.user_ttd_id = mp.id', 'left')
            ->join('ptk_komoditas pkom', 'p.id = pkom.ptk_id') 
            ->join('master_satuan ms', 'pkom.satuan_lain_id = ms.id', 'left')
            ->join('master_negara mn1', 'p.negara_asal_id = mn1.id', 'left')
            ->join('master_negara mn2', 'p.negara_tujuan_id = mn2.id', 'left')
            ->join('master_kota_kab mn3', 'p.kota_kab_asal_id = mn3.id', 'left')
            ->join('master_kota_kab mn4', 'p.kota_kab_tujuan_id = mn4.id', 'left');

        $this->db->where_in('p.id', $ids)
                 ->where('pkom.deleted_at', '1970-01-01 08:00:00')
                 ->where('p6.deleted_at', '1970-01-01 08:00:00')
                 ->order_by('tgl_penolakan', 'DESC')
                 ->order_by('p.no_aju', 'ASC');

        $rows = $this->db->get()->result_array();
        return $this->formatNncData($rows, true);
    }

    private function applyManualFilter($f)
    {
        $this->db->where('p.is_verifikasi', '1');
        $this->db->where('p.is_batal', '0');
        $this->db->where('p6.deleted_at', '1970-01-01 08:00:00');
        $this->db->where('p6.dokumen_karantina_id', '32'); 

        if (!empty($f['upt']) && !in_array(strtolower($f['upt']), ['all', 'semua'])) {
            if (strlen($f['upt']) <= 4) {
                $this->db->where("p.upt_id", $f['upt']);
            } else {
                $this->db->where("p.kode_satpel", $f['upt']);
            }
        }

        if (!empty($f['karantina'])) {
             $this->db->where('p.jenis_karantina', strtoupper(substr($f['karantina'], -1)));
        }
        
        if (!empty($f['lingkup'])) {
            $this->db->where('p.jenis_permohonan', $f['lingkup']);
        }

        if (!empty($f['start_date']) && !empty($f['end_date'])) {
            $this->db->where('DATE(p6.tanggal) >=', $f['start_date']);
            $this->db->where('DATE(p6.tanggal) <=', $f['end_date']);
        }

        if (!empty($f['search'])) {
            $this->applyGlobalSearch($f['search'], [
                'p.no_aju',
                'p.no_dok_permohonan',
                'p6.nomor', 
                'p.nama_pengirim',
                'p.nama_penerima',
                'pkom.nama_umum_tercetak' 
            ]);
        }
    }

    private function formatNncData(array $rows, bool $isExcel = false): array 
    {
        $labels = [
            1 => 'Prohibited goods: ',
            2 => 'Problem with documentation (specify): ',
            3 => 'The goods were infected/infested/contaminated with pests (specify): ',
            4 => 'The goods do not comply with food safety (specify): ',
            5 => 'The goods do not comply with other SPS (specify): '
        ];

        foreach ($rows as &$r) {
            $messages = [];
            for ($i = 1; $i <= 5; $i++) {
                $val = $r["specify$i"] ?? '';
                if (!empty($val)) {
                    if ($isExcel) {
                        $messages[] = $labels[$i] . $val;
                    } else {
                        $messages[] = "<strong>" . $labels[$i] . "</strong> " . htmlspecialchars($val);
                    }
                }
            }
            $separator = $isExcel ? " | " : "<br>";
            $r['nnc_reason'] = !empty($messages) ? implode($separator, $messages) : '-';
            $r['nnc_reason_text'] = strip_tags(str_replace('<br>', ' | ', $r['nnc_reason']));
            $r['consignment_full'] = "The " . ($r['consignment_desc'] ?? '') . " lot was: " . ($r['information'] ?? '');
            $r['upt_full'] = ($r['upt_raw'] ?? '') . ' - ' . ($r['nama_satpel'] ?? '');
        }
        return $rows;
    }
}