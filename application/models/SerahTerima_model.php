<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'models/BaseModelStrict.php';

class SerahTerima_model extends BaseModelStrict
{
    private function applyFilters($f) {
        $this->db->where([
            'p.is_verifikasi' => '1',
            'p.is_batal'      => '0',
            'p.deleted_at'    => '1970-01-01 08:00:00',
            'pkom.deleted_at' => '1970-01-01 08:00:00',
        ]);

        if (!empty($f['upt']) && $f['upt'] !== 'all') {
            $this->db->where("LEFT(ba.upt_tujuan_id, 2) =", substr($f['upt'], 0, 2), false);
        }

        if (!empty($f['karantina'])) {
            $this->db->where('ba.jenis_karantina', $f['karantina']);
        }

        if (!empty($f['start_date']) && !empty($f['end_date'])) {
            $this->db->where('ba.tanggal >=', $f['start_date']);
            $this->db->where('ba.tanggal <=', $f['end_date'] . ' 23:59:59');
        }
        
        if (!empty($f['search'])) {
            $q = $f['search'];
            $this->db->group_start();
                $this->db->like('ba.nomor', $q);
                $this->db->or_like('ba.instansi_pihak1', $q);
                $this->db->or_like('mu.nama', $q);
                $this->db->or_like('mp1.nama', $q);
                $this->db->or_like('mp2.nama', $q);
                $this->db->or_like('kom.nama', $q);
            $this->db->group_end();
        }
    }

    public function getIds($f, $limit, $offset) {
        $sortMap = [
            'nomor_ba' => 'ba.nomor',
            'tgl_ba'   => 'ba.tanggal',
        ];
        $sortBy = $sortMap[$f['sort_by'] ?? ''] ?? 'ba.tanggal';
        $sortOrder = ($f['sort_order'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';

        $kar = strtoupper($f['karantina'] ?? 'H');
        $tabel_kom = "komoditas_" . ($kar == 'H' ? 'hewan' : ($kar == 'I' ? 'ikan' : 'tumbuhan'));

        $this->db->select('p.id', false)
            ->from('ptk p')
            ->join('ba_penyerahan_mp ba', 'p.id = ba.ptk_id')
            ->join('ptk_komoditas pkom', 'p.id = pkom.ptk_id')
            ->join("$tabel_kom kom", 'pkom.komoditas_id = kom.id', 'left')
            ->join('master_upt mu', 'ba.upt_tujuan_id = mu.id', 'left')
            ->join('master_pegawai mp1', 'ba.user_asal_id = mp1.id', 'left')
            ->join('master_pegawai mp2', 'ba.user_tujuan_id = mp2.id', 'left');

        $this->applyFilters($f);

        $this->db->group_by('p.id, ba.nomor, ba.tanggal')
            ->order_by($sortBy, $sortOrder)
            ->limit($limit, $offset);

        return array_column($this->db->get()->result_array(), 'id');
    }

    public function countAll($f) {
        $kar = strtoupper($f['karantina'] ?? 'H');
        $tabel_kom = "komoditas_" . ($kar == 'H' ? 'hewan' : ($kar == 'I' ? 'ikan' : 'tumbuhan'));

        $this->db->select('COUNT(DISTINCT p.id) AS total', false)
            ->from('ptk p')
            ->join('ba_penyerahan_mp ba', 'p.id = ba.ptk_id')
            ->join('ptk_komoditas pkom', 'p.id = pkom.ptk_id')
            ->join("$tabel_kom kom", 'pkom.komoditas_id = kom.id', 'left')
            ->join('master_upt mu', 'ba.upt_tujuan_id = mu.id', 'left')
            ->join('master_pegawai mp1', 'ba.user_asal_id = mp1.id', 'left')
            ->join('master_pegawai mp2', 'ba.user_tujuan_id = mp2.id', 'left');

        $this->applyFilters($f);
        
        $res = $this->db->get()->row();
        return $res ? (int) $res->total : 0;
    }

    public function getByIds($ids, $karantina = null, $sortBy = 'tgl_ba', $sortOrder = 'DESC')
    {
        if (empty($ids)) return [];
        $sortMap = [
            'nomor_ba' => 'ba.nomor',
            'tgl_ba'   => 'ba.tanggal',
        ];
        $orderBy = $sortMap[$sortBy] ?? 'ba.tanggal';
        $order = strtoupper($sortOrder) === 'ASC' ? 'ASC' : 'DESC';

        $this->db->select("
            p.id,
            ANY_VALUE(IF(p.tssm_id IS NOT NULL, 'SSM', 'PTK')) AS sumber,
            ANY_VALUE(ba.nomor) AS nomor_ba,
            ANY_VALUE(ba.tanggal) AS tgl_ba,
            ANY_VALUE(ba.instansi_pihak1) AS instansi_asal,
            ANY_VALUE(ba.jenis_karantina) AS jns_kar,
            ANY_VALUE(ba.info_tambahan) AS keterangan,
            ANY_VALUE(mu.nama) AS upt_tujuan,
            ANY_VALUE(mj.nama) AS media_pembawa,
            ANY_VALUE(mp1.nama) AS petugas_penyerah,
            ANY_VALUE(mp2.nama) AS petugas_penerima,
            ANY_VALUE(p1.no_dok_permohonan) AS no_aju_tujuan,
            ANY_VALUE(p1.tgl_dok_permohonan) AS tgl_aju_tujuan,
            GROUP_CONCAT(DISTINCT kom.nama SEPARATOR '<br>') AS komoditas
        ", false);

        $this->db->from('ptk p')
            ->join('ba_penyerahan_mp ba', 'p.id = ba.ptk_id')
            ->join('ptk p1', 'ba.ptk_id_penerima = p1.id', 'left')
            ->join('ptk_komoditas pkom', 'p.id = pkom.ptk_id')
            ->join('master_upt mu', 'ba.upt_tujuan_id = mu.id', 'left')
            ->join('master_jenis_media_pembawa mj', 'ba.jenis_mp_id = mj.id', 'left')
            ->join('master_pegawai mp1', 'ba.user_asal_id = mp1.id', 'left')
            ->join('master_pegawai mp2', 'ba.user_tujuan_id = mp2.id', 'left');

        $kar = strtoupper($karantina ?: 'H');
        $tableKom = "komoditas_" . ($kar == 'H' ? 'hewan' : ($kar == 'I' ? 'ikan' : 'tumbuhan'));
        $this->db->join("$tableKom kom", 'pkom.komoditas_id = kom.id', 'left');

        $ids_string = implode(',', array_map([$this->db, 'escape'], $ids));
        $this->db->where("p.id IN ($ids_string)", NULL, FALSE);

        $this->db->group_by('p.id, ba.nomor, ba.tanggal')->order_by($orderBy, $order);

        return $this->db->get()->result_array();
    }
}