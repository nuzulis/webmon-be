<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'core/BaseModelStrict.php';

class BatalPermohonan_model extends BaseModelStrict
{
    public function __construct()
{
    parent::__construct();
}

    public function getIds($f, $limit, $offset)
{
    $this->db->select('p.id', false)
        ->from('ptk p')
        ->join('master_upt mu_search', 'p.kode_satpel = mu_search.id', 'left')
        ->where('p.is_batal', '1');

    $this->applyManualFilter($f);

    $sortMap = [
        'no_dok_permohonan'  => 'p.no_dok_permohonan',
        'tgl_dok_permohonan' => 'p.tgl_dok_permohonan',
        'tgl_batal'          => 'p.deleted_at',
        'nama_pengirim'      => 'p.nama_pengirim',
        'alasan_batal'       => 'p.alasan_batal',
        'upt'                => 'mu_search.nama',
    ];

    $this->applySorting(
        $f['sort_by'] ?? null,
        $f['sort_order'] ?? 'DESC',
        $sortMap,
        ['p.deleted_at', 'DESC']
    );

    $this->db->limit($limit, $offset);
    return array_column($this->db->get()->result_array(), 'id');
}

public function countAll($f)
{
    $this->db->select('COUNT(*) AS total', false)
        ->from('ptk p')
        ->join('master_upt mu_search', 'p.kode_satpel = mu_search.id', 'left')
        ->where('p.is_batal', '1');

    $this->applyManualFilter($f);

    $res = $this->db->get()->row();
    return $res ? (int) $res->total : 0;
}

    public function getByIds($ids)
    {
        if (empty($ids)) return [];

        $this->db->select("
            p.id,
            p.no_dok_permohonan,
            p.tgl_dok_permohonan,
            p.nama_pengirim,
            p.nama_penerima,
            p.alasan_batal,
            p.deleted_at AS tgl_batal,
            mu.nama AS upt,
            mu.nama_satpel,
            mp.nama AS pembatal
        ", false);

        $this->db->from('ptk p')
            ->join('master_upt mu', 'p.kode_satpel = mu.id', 'left')
            ->join('master_pegawai mp', 'p.user_batal = mp.id', 'left')
            ->where_in('p.id', $ids)
            ->order_by('p.deleted_at', 'DESC');

        return $this->db->get()->result_array();
    }

   

    private function applyManualFilter($f)
    {
        if (!empty($f['karantina'])) {
            $this->db->where('p.jenis_karantina', strtoupper(trim($f['karantina'])));
        }
        if (!empty($f['upt_id']) && !in_array(strtolower($f['upt_id']), ['all', 'semua'])) {
            if (strlen($f['upt_id']) === 4) {
                $prefix = substr($f['upt_id'], 0, 2);
                $lastTwo = substr($f['upt_id'], 2, 2);
                
                if ($lastTwo === '00') {
                    $this->db->like('p.kode_satpel', $prefix, 'after');
                } else {
                    $this->db->where('p.kode_satpel', $f['upt_id']);
                }
            } else {
                $this->db->where('p.kode_satpel', $f['upt_id']);
            }
        }

        if (!empty($f['start_date'])) {
            $this->db->where('p.tgl_dok_permohonan >=', $f['start_date']);
        }
        if (!empty($f['end_date'])) {
            $this->db->where('p.tgl_dok_permohonan <=', $f['end_date']);
        }
        if (!empty($f['search'])) {
                $this->applyGlobalSearch($f['search'], [
                    'p.no_dok_permohonan',
                    'p.nama_pengirim',
                    'p.nama_penerima',
                    'p.alasan_batal',
                    'mu_search.nama',
                    'mu_search.nama_satpel'
        ]);
        }
    }

    public function getFullData($f)
    {
        $this->db->select('p.id')
            ->from('ptk p')
            ->where('p.is_batal', '1');

        $this->applyManualFilter($f);

        $ids = array_column($this->db->get()->result_array(), 'id');
        return $this->getByIds($ids);
    }
}