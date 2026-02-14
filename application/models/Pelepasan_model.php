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

    public function getIds($f, $limit, $offset)
    {
        $table = $this->getTable($f['karantina']);
        $this->db->select('p.id, MAX(p8.tanggal) as tgl_lepas', false); 
        $this->db->from('ptk p');
        $this->db->join("$table p8", 'p.id = p8.ptk_id');
        $this->applyManualFilters($f, $table);
        $this->db->group_by('p.id');
        $sortBy = $f['sort_by'] ?? 'tgl_lepas';
        $sortOrder = $f['sort_order'] ?? 'DESC';

        if ($sortBy === 'tgl_lepas') {
            $this->db->order_by('tgl_lepas', $sortOrder);
        } elseif ($sortBy === 'no_aju') {
            $this->db->order_by('p.no_aju', $sortOrder);
        } elseif ($sortBy === 'no_dok') {
            $this->db->order_by('p.no_dok_permohonan', $sortOrder);
        } else {
             $this->db->order_by('tgl_lepas', 'DESC');
        }

        $this->db->limit($limit, $offset);

        $query = $this->db->get();
        if (!$query) return [];

        return array_column($query->result_array(), 'id');
    }

    public function getByIds($ids)
    {
        if (empty($ids)) return [];

        $CI =& get_instance();
        $karantina = $CI->input->get('karantina', true);
        
        $table = $this->getTable($karantina);
        $quotedIds = implode(',', array_map([$this->db, 'escape'], $ids));

        $this->db->select("
            p.id, 
            p8.nomor AS nkt, 
            p8.nomor_seri AS seri, 
            p8.tanggal AS tanggal_lepas,
            mu.nama AS upt, 
            mu.nama_satpel AS satpel,
            p.no_aju,
            p.nama_pemohon,
            p.nama_penerima,
            mn2.nama AS tujuan,
            mn4.nama AS kota_tujuan,
            mn1.nama AS asal, 
            mn3.nama AS kota_asal,
            
            k.komoditas,
            k.volume,
            k.volumeP8,
            k.satuan,
            k.hs_concat as hs
        ", false);

        $this->db->from('ptk p')
            ->join("$table p8", 'p.id = p8.ptk_id')
            ->join('master_upt mu', 'p.kode_satpel = mu.id', 'left')
            ->join('master_negara mn1', 'p.negara_asal_id = mn1.id', 'left')
            ->join('master_negara mn2', 'p.negara_tujuan_id = mn2.id', 'left')
            ->join('master_kota_kab mn3', 'p.kota_kab_asal_id = mn3.id', 'left')
            ->join('master_kota_kab mn4', 'p.kota_kab_tujuan_id = mn4.id', 'left');

        $this->db->join("(
            SELECT ptk_id,
                   GROUP_CONCAT(CONCAT('• ', nama_umum_tercetak) SEPARATOR '<br>') as komoditas,
                   GROUP_CONCAT(volumeP8 SEPARATOR '<br>') as volumeP8,
                   GROUP_CONCAT(volume_lain SEPARATOR '<br>') as volume,
                   GROUP_CONCAT(COALESCE(sat.nama, '-') SEPARATOR '<br>') as satuan,
                   GROUP_CONCAT(kode_hs SEPARATOR '<br>') as hs_concat
            FROM ptk_komoditas pk
            LEFT JOIN master_satuan sat ON pk.satuan_lain_id = sat.id
            WHERE ptk_id IN ($quotedIds) AND pk.deleted_at = '1970-01-01 08:00:00'
            GROUP BY ptk_id
        ) k", 'p.id = k.ptk_id', 'left', false);

        $this->db->where_in('p.id', $ids);
        $this->db->order_by('p8.tanggal', 'DESC');

        return $this->db->get()->result_array();
    }

    public function countAll($f)
    {
        $table = $this->getTable($f['karantina']);
        
        $this->db->select('COUNT(DISTINCT p.id) as total');
        $this->db->from('ptk p')
            ->join("$table p8", 'p.id = p8.ptk_id');

        $this->applyManualFilters($f, $table);

        $res = $this->db->get()->row();
        return $res ? (int) $res->total : 0;
    }

    public function getFullData($f)
    {
        $ids = $this->getIds($f, 100000, 0);
        if (empty($ids)) return [];

        $table = $this->getTable($f['karantina']);
        $tabel_kom = $this->getTableKom($f['karantina']);
        $tabel_klas = $this->getTableKlas($f['karantina']);
        
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

            ms.nama AS satuan, pkom.harga_rp,
            
            (SELECT GROUP_CONCAT(CONCAT(nomor, ' (', segel, ')') SEPARATOR '; ') 
             FROM ptk_kontainer WHERE ptk_id = p.id AND deleted_at = '1970-01-01 08:00:00') AS kontainer_string,
             
            (SELECT GROUP_CONCAT(CONCAT(mjd.nama, ':', pdok.no_dokumen) SEPARATOR '; ')
             FROM ptk_dokumen pdok 
             JOIN master_jenis_dokumen mjd ON pdok.jenis_dokumen_id = mjd.id 
             WHERE pdok.ptk_id = p.id AND pdok.deleted_at = '1970-01-01 08:00:00') AS dokumen_pendukung_string
        ", false);

        $this->db->from('ptk p')
            ->join("$table p8", 'p.id = p8.ptk_id')
            ->join('ptk_komoditas pkom', "p.id = pkom.ptk_id AND pkom.deleted_at = '1970-01-01 08:00:00'")
            ->join("$tabel_kom kom", 'pkom.komoditas_id = kom.id', 'left')
            ->join("$tabel_klas klas", 'pkom.klasifikasi_id = klas.id', 'left')
            ->join('master_upt mu', 'p.kode_satpel = mu.id', 'left')
            ->join('master_satuan ms', 'pkom.satuan_lain_id = ms.id', 'left')
            ->join('master_pelabuhan pel_muat', 'p.pelabuhan_muat_id = pel_muat.id', 'left')
            ->join('master_pelabuhan pel_bongkar', 'p.pelabuhan_bongkar_id = pel_bongkar.id', 'left')
            ->join('master_moda_alat_angkut moda', 'p.moda_alat_angkut_terakhir_id = moda.id', 'left')
            ->join('master_jenis_kemasan mjk', 'p.kemasan_id = mjk.id', 'left')
            ->join('master_negara mn1', 'p.negara_asal_id = mn1.id', 'left')
            ->join('master_negara mn2', 'p.negara_tujuan_id = mn2.id', 'left')
            ->join('master_kota_kab mn3', 'p.kota_kab_asal_id = mn3.id', 'left')
            ->join('master_kota_kab mn4', 'p.kota_kab_tujuan_id = mn4.id', 'left');

        $this->db->where_in('p.id', $ids);
        $this->db->order_by('p.id', 'ASC'); 
        $this->db->order_by('pkom.id', 'ASC');

        return $this->db->get()->result_array();
    }

    private function applyManualFilters($f, $table)
{
    $this->db->where([
        'p.is_verifikasi' => '1',
        'p.is_batal'      => '0',
        'p8.deleted_at'   => '1970-01-01 08:00:00',
    ])->where("p8.nomor_seri != '*******'", null, false);
    if (!empty($f['upt']) && !in_array(strtolower($f['upt']), ['all', 'semua', 'undefined'])) {
        if (strlen($f['upt']) <= 4) {
            $this->db->group_start()
                     ->where("p.upt_id", $f['upt'])
                     ->or_like("p.kode_satpel", $f['upt'], 'after')
                     ->group_end();
        } else {
            $this->db->where("p.kode_satpel", $f['upt']);
        }
    }
    if (!empty($f['lingkup']) && strtolower($f['lingkup']) !== 'all') {
        $this->db->where('p.jenis_permohonan', strtoupper($f['lingkup']));
    }

    if (!empty($f['start_date']) && !empty($f['end_date'])) {
        $this->db->where("p8.tanggal >=", $f['start_date'] . ' 00:00:00');
        $this->db->where("p8.tanggal <=", $f['end_date'] . ' 23:59:59');
    }
    if (!empty($f['search'])) {
        $search = trim($f['search']);
        $s = $this->db->escape_str($this->db->escape_like_str($search));

        $this->db->group_start();
            $this->db->like('p.no_aju', $search);
            $this->db->or_like('p.no_dok_permohonan', $search);
            $this->db->or_like('p8.nomor', $search);
            $this->db->or_like('p.nama_pengirim', $search);
            $this->db->or_like('p.nama_penerima', $search);
            $this->db->or_like('p.nama_pemohon', $search);

            $this->db->or_where("EXISTS (
                SELECT 1 FROM ptk_komoditas sk
                WHERE sk.ptk_id = p.id
                AND sk.deleted_at = '1970-01-01 08:00:00'
                AND (sk.nama_umum_tercetak LIKE '%{$s}%' ESCAPE '!' OR sk.kode_hs LIKE '%{$s}%' ESCAPE '!')
            )", null, false);

            $this->db->or_where("EXISTS (SELECT 1 FROM master_negara mn WHERE mn.id = p.negara_asal_id AND mn.nama LIKE '%{$s}%' ESCAPE '!')", null, false);
            $this->db->or_where("EXISTS (SELECT 1 FROM master_negara mn2 WHERE mn2.id = p.negara_tujuan_id AND mn2.nama LIKE '%{$s}%' ESCAPE '!')", null, false);
            $this->db->or_where("EXISTS (SELECT 1 FROM master_kota_kab mk WHERE mk.id = p.kota_kab_asal_id AND mk.nama LIKE '%{$s}%' ESCAPE '!')", null, false);
            $this->db->or_where("EXISTS (SELECT 1 FROM master_kota_kab mk2 WHERE mk2.id = p.kota_kab_tujuan_id AND mk2.nama LIKE '%{$s}%' ESCAPE '!')", null, false);

            $this->db->or_where("EXISTS (
                SELECT 1 FROM master_upt mu WHERE mu.id = p.kode_satpel
                AND (mu.nama LIKE '%{$s}%' ESCAPE '!' OR mu.nama_satpel LIKE '%{$s}%' ESCAPE '!')
            )", null, false);
        $this->db->group_end();
    }
}
}