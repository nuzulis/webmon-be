<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'models/BaseModelStrict.php';

class Monitoring_model extends BaseModelStrict 
{
    public function __construct()
    {
        parent::__construct();
    }
    private function getTable($karantina)
    {
        $k = strtoupper(substr($karantina ?? 'H', -1)); 
        return match ($k) {
            'H' => 'pn_pelepasan_kh',
            'I' => 'pn_pelepasan_ki',
            'T' => 'pn_pelepasan_kt',
            default => 'pn_pelepasan_kh',
        };
    }

    private function getTableKom($karantina)
    {
        $k = strtoupper(substr($karantina ?? 'H', -1)); 
        return match ($k) {
            'H' => 'komoditas_hewan',
            'I' => 'komoditas_ikan',
            'T' => 'komoditas_tumbuhan',
            default => 'komoditas_hewan',
        };
    }

    public function getIds($f, $limit, $offset)
    {
        $this->db->select('p.id', false)
            ->from('ptk p')
            ->join('pn_fisik_kesehatan p1b', 'p.id = p1b.ptk_id') 
            ->join('master_upt mu', 'p.kode_satpel = mu.id', 'left');
        if (!empty($f['search'])) {
            $karInput = $f['karantina'] ?? 'H';
            $tableKomoditas = $this->getTableKom($karInput);
            
            $this->db->join('ptk_komoditas pkom', "p.id = pkom.ptk_id", 'left');
            $this->db->join("$tableKomoditas kom", 'pkom.komoditas_id = kom.id', 'left');
        }

        $this->applyManualFilter($f);
        $sortMap = [
            'no_aju'        => 'p.no_aju',
            'no_dok'        => 'p.no_dok_permohonan',
            'tgl_dok'       => 'p.tgl_dok_permohonan',
            'tgl_periksa'   => 'MAX(p1b.waktu_periksa)',
            'upt'           => 'MAX(mu.nama)',
            'nama_pengirim' => 'p.nama_pengirim',
            'nama_penerima' => 'p.nama_penerima',
        ];

        $sortKey = $f['sort_by'] ?? null;
        $sortCol = $sortMap[$sortKey] ?? 'MAX(p1b.waktu_periksa)';
        $sortDir = strtoupper($f['sort_order'] ?? 'DESC');
        $sortDir = in_array($sortDir, ['ASC', 'DESC']) ? $sortDir : 'DESC';

        $this->db->order_by($sortCol, $sortDir);
        
        $this->db->group_by('p.id');

        if ($limit > 0) {
            $this->db->limit($limit, $offset);
        }

        $result = $this->db->get()->result_array();
        return array_column($result, 'id');
    }
    public function getByIds($ids)
    {
        if (empty($ids)) return [];
        
        $CI =& get_instance();
        $karInput = $CI->input->get('karantina', true) ?: 'H';
        
        $tablePelepasan = $this->getTable($karInput);
        $tableKomoditas = $this->getTableKom($karInput);
        $this->db->select("
            p.id, 
            p.no_aju, 
            p.no_dok_permohonan as no_dok, 
            p.tgl_dok_permohonan,
            p.nama_pengirim,
            p.nama_penerima,
            
            REPLACE(REPLACE(MAX(mu.nama), 
                'Balai Besar Karantina Hewan, Ikan, dan Tumbuhan', 'BBKHIT'), 
                'Balai Karantina Hewan, Ikan, dan Tumbuhan', 'BKHIT') as upt_raw,
            MAX(mu.nama_satpel) as nama_satpel,
            
            MAX(p1b.waktu_periksa) as tgl_periksa,
            MAX(p8.tanggal) as tgl_lepas,
            TIMESTAMPDIFF(MINUTE, MAX(p1b.waktu_periksa), MAX(p8.tanggal)) as sla_menit,
            
            GROUP_CONCAT(DISTINCT kom.nama SEPARATOR ', ') as komoditas,
            GROUP_CONCAT(DISTINCT ms.nama SEPARATOR ', ') as satuan,
            SUM(pkom.volume_lain) as volume,
            
            CASE 
                WHEN MAX(s8.p8) IS NOT NULL THEN 'Pelepasan' 
                WHEN MAX(s8.p5) IS NOT NULL THEN 'Penahanan' 
                WHEN MAX(s8.p6) IS NOT NULL THEN 'Penolakan' 
                WHEN MAX(s8.p7) IS NOT NULL THEN 'Pemusnahan' 
                ELSE 'Proses'
            END AS status
        ", false);

        $this->db->from('ptk p')
            ->join('master_upt mu', 'p.kode_satpel = mu.id', 'left')
            ->join('pn_fisik_kesehatan p1b', 'p.id = p1b.ptk_id', 'left')
            ->join('status8p s8', 'p.id = s8.id', 'left')
            ->join("$tablePelepasan p8", "p.id = p8.ptk_id AND p8.deleted_at = '1970-01-01 08:00:00'", 'left')
            ->join('ptk_komoditas pkom', "p.id = pkom.ptk_id AND pkom.deleted_at = '1970-01-01 08:00:00'", 'left')
            ->join("$tableKomoditas kom", 'pkom.komoditas_id = kom.id', 'left')
            ->join('master_satuan ms', 'pkom.satuan_lain_id = ms.id', 'left');

        $this->db->where_in('p.id', $ids);
        $this->db->group_by('p.id');
        $this->db->order_by('MAX(p1b.waktu_periksa)', 'DESC');
        
        $rows = $this->db->get()->result_array();
        return $this->formatSlaText($rows);
    }

    public function countAll($f): int 
    {
        $this->db->from('ptk p')
            ->join('pn_fisik_kesehatan p1b', 'p.id = p1b.ptk_id') 
            ->join('master_upt mu', 'p.kode_satpel = mu.id', 'left');
        
        if (!empty($f['search'])) {
            $karInput = $f['karantina'] ?? 'H';
            $tableKomoditas = $this->getTableKom($karInput);
            
            $this->db->join('ptk_komoditas pkom', "p.id = pkom.ptk_id", 'left');
            $this->db->join("$tableKomoditas kom", 'pkom.komoditas_id = kom.id', 'left');
        }

        $this->applyManualFilter($f);
        if (!empty($f['search'])) {
            $this->db->select('COUNT(DISTINCT p.id) as total');
            $res = $this->db->get()->row();
            return $res ? (int)$res->total : 0;
        }

        return $this->db->count_all_results();
    }

    private function applyManualFilter($f) 
    {
        $this->db->where('p.is_verifikasi', '1');
        $this->db->where('p.is_batal', '0');

        if (!empty($f['upt']) && !in_array(strtolower($f['upt']), ['all', 'semua'])) {
            if (strlen($f['upt']) <= 4) {
                $this->db->where("p.upt_id", $f['upt']);
            } else {
                $this->db->where("p.kode_satpel", $f['upt']);
            }
        }

        if (!empty($f['karantina'])) {
            $jenis = strtoupper(substr($f['karantina'], -1));
            $this->db->where('p.jenis_karantina', $jenis);
        }

        if (!empty($f['lingkup'])) {
            $this->db->where('p.jenis_permohonan', $f['lingkup']);
        }

        if (!empty($f['start_date']) && !empty($f['end_date'])) {
            $this->db->where('p1b.waktu_periksa >=', $f['start_date'] . ' 00:00:00');
            $this->db->where('p1b.waktu_periksa <=', $f['end_date'] . ' 23:59:59');
        }
        if (!empty($f['search'])) {
            $search = $f['search'];
            $columns = [
                'p.no_aju',
                'p.no_dok_permohonan',
                'p.nama_pengirim',
                'p.nama_penerima',
                'mu.nama',
                'mu.nama_satpel',
            ];
            $columns[] = 'kom.nama';

            $this->db->group_start();
            foreach ($columns as $index => $col) {
                if ($index === 0) {
                    $this->db->like($col, $search);
                } else {
                    $this->db->or_like($col, $search);
                }
            }
            $this->db->group_end();
        }
    }

    private function formatSlaText(array $rows): array 
    {
        foreach ($rows as &$r) {
            $r['upt_full'] = ($r['upt_raw'] ?? '') . ' - ' . ($r['nama_satpel'] ?? '');
            if (isset($r['sla_menit']) && $r['sla_menit'] !== null) {
                $min = (int) $r['sla_menit'];
                $r['is_warning'] = ($min > 60); 
                $h = floor($min / 60);
                $m = $min % 60;
                $r['sla'] = ($min <= 0) ? '0m' : (($h > 0 ? "{$h}j " : "") . "{$m}m");
            } else {
                $r['sla'] = '-';
                $r['is_warning'] = false;
            }
            $r['sla_text'] = $r['sla'];
        }
        return $rows;
    }
    public function getFullData($f)
    {
        $karInput = $f['karantina'] ?? 'H';
        $tablePelepasan = $this->getTable($karInput);
        $tableKomoditas = $this->getTableKom($karInput);
        $this->db->select("
            p.no_aju, 
            p.no_dok_permohonan as no_dok, 
            p.nama_pengirim, 
            p.nama_penerima, 
            p.tgl_dok_permohonan, 
            p1b.waktu_periksa as tgl_periksa, 
            p8.tanggal as tanggal_lepas,
            REPLACE(REPLACE(mu.nama, 
                'Balai Besar Karantina Hewan, Ikan, dan Tumbuhan', 'BBKHIT'), 
                'Balai Karantina Hewan, Ikan, dan Tumbuhan', 'BKHIT') as upt_raw, 
            mu.nama_satpel,
            
            kom.nama as komoditas, 
            pkom.nama_umum_tercetak, 
            pkom.volumeP1 as p1, pkom.volumeP2 as p2, pkom.volumeP3 as p3, pkom.volumeP4 as p4,
            pkom.volumeP5 as p5, pkom.volumeP6 as p6, pkom.volumeP7 as p7, pkom.volumeP8 as p8,
            ms.nama as satuan,
            
            TIMESTAMPDIFF(MINUTE, p1b.waktu_periksa, p8.tanggal) as sla_menit,
            
            CASE 
                WHEN s8.p8 IS NOT NULL THEN 'Pelepasan'
                WHEN s8.p5 IS NOT NULL THEN 'Penahanan'
                WHEN s8.p6 IS NOT NULL THEN 'Penolakan'
                WHEN s8.p7 IS NOT NULL THEN 'Pemusnahan'
                ELSE 'Proses'
            END AS status
        ", false);

        $this->db->from('ptk p')
            ->join('master_upt mu', 'p.kode_satpel = mu.id', 'left')
            ->join('pn_fisik_kesehatan p1b', 'p.id = p1b.ptk_id')
            ->join('status8p s8', 'p.id = s8.id', 'left');

        $this->db->join("$tablePelepasan p8", "p.id = p8.ptk_id AND p8.deleted_at = '1970-01-01 08:00:00'", 'left');
        $this->db->join('ptk_komoditas pkom', "p.id = pkom.ptk_id AND pkom.deleted_at = '1970-01-01 08:00:00'");
        $this->db->join("$tableKomoditas kom", 'pkom.komoditas_id = kom.id');
        $this->db->join('master_satuan ms', 'pkom.satuan_lain_id = ms.id', 'left'); 
        $this->applyManualFilter($f);
        
        $this->db->order_by('p1b.waktu_periksa', 'DESC');
        $this->db->order_by('p.no_aju', 'ASC');

        $rows = $this->db->get()->result_array();
        return $this->formatSlaText($rows);
    }
}