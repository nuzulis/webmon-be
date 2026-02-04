<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'models/BaseModelStrict.php';

class Monitoring_model extends BaseModelStrict 
{
    /* ================= HELPER MAPPING TABEL ================= */

    private function getTable($karantina) {
        $k = strtoupper(substr($karantina, -1)); 
        return match ($k) {
            'H' => 'pn_pelepasan_kh',
            'I' => 'pn_pelepasan_ki',
            'T' => 'pn_pelepasan_kt',
            default => 'pn_pelepasan_kh',
        };
    }

    private function getTableKom($karantina) {
        $k = strtoupper(substr($karantina, -1)); 
        return match ($k) {
            'H' => 'komoditas_hewan',
            'I' => 'komoditas_ikan',
            'T' => 'komoditas_tumbuhan',
            default => 'komoditas_hewan',
        };
    }

    private function applyFilters($f) 
    {
        $this->db->where(['p.is_verifikasi' => '1', 'p.is_batal' => '0']);

        if (!empty($f['upt']) && !in_array(strtolower($f['upt']), ['all', 'semua'])) {
            if (strlen($f['upt']) <= 4) {
                $this->db->where("p.upt_id", $f['upt']);
            } else {
                $this->db->where("p.kode_satpel", $f['upt']);
            }
        }
        
        if (!empty($f['karantina'])) $this->db->where('p.jenis_karantina', strtoupper(substr($f['karantina'], -1)));
        if (!empty($f['lingkup'])) $this->db->where('p.jenis_permohonan', $f['lingkup']);
        if (!empty($f['start_date']) && !empty($f['end_date'])) {
            $this->db->where('p1b.waktu_periksa >=', $f['start_date']);
            $this->db->where('p1b.waktu_periksa <', "DATE_ADD('{$f['end_date']}', INTERVAL 1 DAY)", FALSE);
        }

        if (!empty($f['search'])) {
            $q = $f['search'];
            $this->db->group_start()
                ->like('p.no_aju', $q)
                ->or_like('p.no_dok_permohonan', $q)
                ->or_like('mu.nama', $q)
            ->group_end();
        }
    }


    public function getIds($f, $limit, $offset) 
    {
        $this->db->select('p.id')
            ->from('ptk p')
            ->join('pn_fisik_kesehatan p1b', 'p.id = p1b.ptk_id')
            ->join('master_upt mu', 'p.kode_satpel = mu.id');

        $this->applyFilters($f);
        $this->db->order_by('p1b.waktu_periksa', 'DESC')->limit($limit, $offset);
        return array_column($this->db->get()->result_array(), 'id');
    }

    public function countAll($f): int 
    {
        $this->db->from('ptk p')
            ->join('pn_fisik_kesehatan p1b', 'p.id = p1b.ptk_id')
            ->join('master_upt mu', 'p.kode_satpel = mu.id');
        $this->applyFilters($f);
        return $this->db->count_all_results();
    }


    public function getByIds($ids) 
    {
        if (empty($ids)) return [];
        
        $quotedIds = implode(',', array_map([$this->db, 'escape'], $ids));
        $karInput  = $this->input->get('karantina', true);
        
        $tablePelepasan = $this->getTable($karInput);
        $tableKomoditas = $this->getTableKom($karInput);

        $this->db->select("
            p.id, 
            ANY_VALUE(p.no_aju) as no_aju, 
            ANY_VALUE(p.no_dok_permohonan) as no_dok, 
            ANY_VALUE(p.tgl_dok_permohonan) as tgl_dok_permohonan,
            ANY_VALUE(REPLACE(REPLACE(mu.nama, 
                'Balai Besar Karantina Hewan, Ikan, dan Tumbuhan', 'BBKHIT'), 
                'Balai Karantina Hewan, Ikan, dan Tumbuhan', 'BKHIT')) as upt_raw,
            ANY_VALUE(mu.nama_satpel) as nama_satpel,
            ANY_VALUE(p1b.waktu_periksa) as tgl_periksa,
            ANY_VALUE(p8.tanggal_lepas) as tgl_lepas,
            TIMESTAMPDIFF(MINUTE, ANY_VALUE(p1b.waktu_periksa), MIN(p8.tanggal_lepas)) as sla_menit,
            ANY_VALUE(k.komoditas) as komoditas,
            ANY_VALUE(CASE 
                WHEN s8.p8 IS NOT NULL THEN 'Pelepasan' 
                WHEN s8.p5 IS NOT NULL THEN 'Penahanan' 
                WHEN s8.p6 IS NOT NULL THEN 'Penolakan' 
                WHEN s8.p7 IS NOT NULL THEN 'Pemusnahan' 
                ELSE 'Proses'
            END) AS status
        ", false);

        $this->db->from('ptk p')
            ->join('master_upt mu', 'p.kode_satpel = mu.id', 'left')
            ->join('pn_fisik_kesehatan p1b', 'p.id = p1b.ptk_id', 'left')
            ->join('status8p s8', 'p.id = s8.id', 'left');

        $this->db->join("(
            SELECT pkom.ptk_id, GROUP_CONCAT(kom.nama SEPARATOR ', ') as komoditas
            FROM ptk_komoditas pkom
            JOIN {$tableKomoditas} kom ON pkom.komoditas_id = kom.id
            WHERE pkom.ptk_id IN ($quotedIds) AND pkom.deleted_at = '1970-01-01 08:00:00'
            GROUP BY pkom.ptk_id
        ) k", 'p.id = k.ptk_id', 'left');

        $this->db->join("(
            SELECT ptk_id, MIN(tanggal) as tanggal_lepas
            FROM {$tablePelepasan}
            WHERE ptk_id IN ($quotedIds)
            GROUP BY ptk_id
        ) p8", 'p.id = p8.ptk_id', 'left');

        $this->db->where_in('p.id', $ids)
                 ->group_by('p.id')
                 ->order_by('tgl_periksa', 'DESC');
        
        $rows = $this->db->get()->result_array();
        return $this->formatSlaText($rows);
    }


    public function getExportData($f) 
{
    $ids = $this->getIds($f, 100000, 0);
    if (empty($ids)) return [];

    $quotedIds = implode(',', array_map([$this->db, 'escape'], $ids));
    $karInput  = $f['karantina'] ?? $this->input->get('karantina', true);
    
    $tablePelepasan = $this->getTable($karInput);
    $tableKomoditas = $this->getTableKom($karInput);
    $this->db->select("
        p.no_aju, 
        p.no_dok_permohonan as no_dok, 
        p.nama_pengirim, 
        p.nama_penerima, 
        p.tgl_dok_permohonan, 
        p1b.waktu_periksa as tgl_periksa, 
        p8.tanggal_lepas,
        REPLACE(REPLACE(mu.nama, 
            'Balai Besar Karantina Hewan, Ikan, dan Tumbuhan', 'BBKHIT'), 
            'Balai Karantina Hewan, Ikan, dan Tumbuhan', 'BKHIT') as upt_raw, 
        mu.nama_satpel,
        kom.nama as komoditas, 
        pkom.nama_umum_tercetak, 
        pkom.volumeP1 as p1, pkom.volumeP2 as p2, pkom.volumeP3 as p3, pkom.volumeP4 as p4,
        pkom.volumeP5 as p5, pkom.volumeP6 as p6, pkom.volumeP7 as p7, pkom.volumeP8 as p8,
        ms.nama as satuan,
        -- Langsung hitung selisih tanpa MIN/ANY_VALUE karena p8 sudah di-filter di subquery
        TIMESTAMPDIFF(MINUTE, p1b.waktu_periksa, p8.tanggal_lepas) as sla_menit,
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
        ->join('pn_fisik_kesehatan p1b', 'p.id = p1b.ptk_id', 'left')
        ->join('status8p s8', 'p.id = s8.id', 'left')
        ->join('ptk_komoditas pkom', 'p.id = pkom.ptk_id')
        ->join("{$tableKomoditas} kom", 'pkom.komoditas_id = kom.id')
        ->join('master_satuan ms', 'pkom.satuan_lain_id = ms.id', 'left');

     $this->db->join("(
        SELECT ptk_id, MIN(tanggal) as tanggal_lepas
        FROM {$tablePelepasan}
        WHERE ptk_id IN ($quotedIds)
        GROUP BY ptk_id
    ) p8", 'p.id = p8.ptk_id', 'left');

    $this->db->where_in('p.id', $ids)
             ->where('pkom.deleted_at', '1970-01-01 08:00:00')
             ->order_by('tgl_periksa', 'DESC')
             ->order_by('p.no_aju', 'ASC');
    
    $rows = $this->db->get()->result_array();
    return $this->formatSlaText($rows);
}


    private function formatSlaText(array $rows): array 
    {
        foreach ($rows as &$r) {
            $min = (int) ($r['sla_menit'] ?? 0);
            $r['is_warning'] = ($min > 60); 

            $h = floor($min / 60);
            $m = $min % 60;
            $r['sla_text'] = ($min <= 0) ? '0m' : (($h > 0 ? "{$h}j " : "") . "{$m}m");
            $r['upt_full'] = ($r['upt_raw'] ?? '') . ' - ' . ($r['nama_satpel'] ?? '');
        }
        return $rows;
    }
}