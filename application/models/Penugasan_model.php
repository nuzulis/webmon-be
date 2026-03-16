<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'core/BaseModelStrict.php';

class Penugasan_model extends BaseModelStrict
{
    public function __construct()
    {
        parent::__construct();
    }

    public function getIds(array $f, int $limit, int $offset): array
    {
        $this->applyIdsQuery($f);
        $this->db->limit($limit, $offset);
        $this->db->reconnect();
        $query = $this->db->get();
        if (!$query) return [];
        return array_column($query->result_array(), 'id');
    }

    public function getByIds($ids): array
    {
        if (empty($ids)) return [];

        $this->db->select("
            h.id,
            h.nomor AS nomor_surtug,
            h.tanggal AS tgl_surtug,
            p.no_dok_permohonan,
            p.tgl_dok_permohonan,
            mu.nama AS upt,
            mu.nama_satpel AS satpel,
            GROUP_CONCAT(DISTINCT mp1.nama ORDER BY mp1.nama SEPARATOR '<br>') AS nama_petugas,
            GROUP_CONCAT(DISTINCT mp1.nip ORDER BY mp1.nip SEPARATOR '<br>') AS nip_petugas,
            mp2.nama AS penandatangan,
            mp2.nip AS nip_ttd,
            GROUP_CONCAT(DISTINCT mpn.nama ORDER BY mpn.nama SEPARATOR ', ') AS jenis_tugas
        ", false)
        ->from('ptk_surtug_header h')
        ->join('ptk p', 'h.ptk_id = p.id')
        ->join('master_upt mu', 'p.upt_id = mu.id')
        ->join('ptk_surtug_petugas pp', 'h.id = pp.ptk_surtug_header_id')
        ->join('ptk_surtug_penugasan pnp', 'pp.id = pnp.ptk_surtug_petugas_id')
        ->join('master_penugasan mpn', 'pnp.penugasan_id = mpn.id')
        ->join('master_pegawai mp1', 'pp.petugas_id = mp1.id')
        ->join('master_pegawai mp2', 'h.penanda_tangan_id = mp2.id');

        $this->db->where_in('h.id', $ids);
        $this->db->group_by('h.id');
        $this->db->order_by('FIELD(h.id,' . implode(',', array_map('intval', $ids)) . ')', null, false);

        $query = $this->db->get();
        return $query ? $query->result_array() : [];
    }

    public function countAll($f): int
    {
        $filterByPetugas = !empty($f['petugas']);
        $needsUpt        = !empty($f['search']);

        $this->db->from('ptk_surtug_header h')
            ->join('ptk p', 'h.ptk_id = p.id');

        if ($needsUpt) {
            $this->db->join('master_upt mu', 'p.upt_id = mu.id');
        }

        if ($filterByPetugas) {
            $this->db->select('COUNT(DISTINCT h.id) AS total', false);
            $this->db->join('ptk_surtug_petugas pp', 'h.id = pp.ptk_surtug_header_id');
        } else {
            $this->db->select('COUNT(*) AS total', false);
        }

        $this->applyMinimalFilter($f, $needsUpt);

        $this->db->reconnect();
        $query = $this->db->get();
        $row = $query ? $query->row() : null;
        return $row ? (int) $row->total : 0;
    }

    public function getFullData($f): array
    {
        $this->db->select("
            p.no_aju,
            p.no_dok_permohonan,
            p.tgl_dok_permohonan,
            mu.nama_satpel AS satpel,
            mu.nama AS upt,
            h.nomor AS nomor_surtug,
            h.tanggal AS tgl_surtug,
            mp1.nama AS nama_petugas,
            mp1.nip AS nip_petugas,
            mpn.nama AS jenis_tugas,
            mn1.nama AS negara_asal,
            mn3.nama AS daerah_asal,
            mn2.nama AS negara_tujuan,
            mn4.nama AS daerah_tujuan,
            pkom.nama_umum_tercetak,
            pkom.kode_hs,
            pkom.volumeP1, pkom.volumeP2, pkom.volumeP3, pkom.volumeP4,
            pkom.volumeP5, pkom.volumeP6, pkom.volumeP7, pkom.volumeP8,
            ms.nama AS nama_satuan,
            CASE
                WHEN p.jenis_karantina = 'H' THEN kh.nama
                WHEN p.jenis_karantina = 'I' THEN ki.nama
                WHEN p.jenis_karantina = 'T' THEN kt.nama
                ELSE '-'
            END AS nama_komoditas
        ", false);

        $this->db->from('ptk p')
            ->join('master_upt mu', 'p.upt_id = mu.id')
            ->join('ptk_surtug_header h', 'p.id = h.ptk_id')
            ->join('ptk_surtug_petugas pp', 'h.id = pp.ptk_surtug_header_id')
            ->join('ptk_surtug_penugasan pnp', 'pp.id = pnp.ptk_surtug_petugas_id')
            ->join('master_penugasan mpn', 'pnp.penugasan_id = mpn.id')
            ->join('master_pegawai mp1', 'pp.petugas_id = mp1.id')
            ->join('ptk_komoditas pkom', "p.id = pkom.ptk_id AND pkom.deleted_at = '1970-01-01 08:00:00'", 'left')
            ->join('master_satuan ms', 'pkom.satuan_lain_id = ms.id', 'left')
            ->join('master_negara mn1', 'p.negara_asal_id = mn1.id', 'left')
            ->join('master_negara mn2', 'p.negara_tujuan_id = mn2.id', 'left')
            ->join('master_kota_kab mn3', 'p.kota_kab_asal_id = mn3.id', 'left')
            ->join('master_kota_kab mn4', 'p.kota_kab_tujuan_id = mn4.id', 'left')
            ->join('komoditas_hewan kh', 'pkom.komoditas_id = kh.id AND p.jenis_karantina = "H"', 'left')
            ->join('komoditas_ikan ki', 'pkom.komoditas_id = ki.id AND p.jenis_karantina = "I"', 'left')
            ->join('komoditas_tumbuhan kt', 'pkom.komoditas_id = kt.id AND p.jenis_karantina = "T"', 'left');

        $this->applyMinimalFilter($f, true);

        $this->db->order_by('h.tanggal', 'DESC');

        $this->db->reconnect();
        $query = $this->db->get();
        return $query ? $query->result_array() : [];
    }

    private function applyIdsQuery(array $f, bool $withTotal = false): void
    {
        $filterByPetugas = !empty($f['petugas']);
        $needsUpt        = !empty($f['search']);

        $select = $withTotal ? 'h.id, COUNT(*) OVER() AS _total' : 'h.id';
        $this->db->select($select, false)
            ->from('ptk_surtug_header h')
            ->join('ptk p', 'h.ptk_id = p.id');

        if ($needsUpt) {
            $this->db->join('master_upt mu', 'p.upt_id = mu.id');
        }
        if ($filterByPetugas) {
            $this->db->join('ptk_surtug_petugas pp', 'h.id = pp.ptk_surtug_header_id');
        }

        $this->applyMinimalFilter($f, $needsUpt);

        $sortMap = [
            'nomor_surtug' => 'h.nomor',
            'tgl_surtug'   => 'h.tanggal',
        ];
        $this->applySorting($f['sort_by'] ?? null, $f['sort_order'] ?? 'DESC', $sortMap, ['h.tanggal', 'DESC']);

        if ($filterByPetugas) {
            $this->db->group_by('h.id');
        }
    }

    private function getIdsAndTotal(array $f, int $limit, int $offset): array
    {
        $this->applyIdsQuery($f, true);
        $this->db->limit($limit, $offset);
        $query = $this->db->get();
        if (!$query || !$query->num_rows()) return [[], 0];
        $rows = $query->result_array();
        return [array_column($rows, 'id'), (int) $rows[0]['_total']];
    }

    private function applyMinimalFilter(array $f, bool $uptJoined = true): void
    {
        $this->db->where([
            'p.is_verifikasi' => '1',
            'p.is_batal'      => '0',
            'h.deleted_at'    => '1970-01-01 08:00:00',
        ]);

        if (!empty($f['petugas'])) {
            $this->db->where('pp.petugas_id', $f['petugas']);
        } elseif (!empty($f['karantina'])) {
            $kar  = strtoupper(trim($f['karantina']));
            $kode = strlen($kar) > 1 ? substr($kar, -1) : $kar;
            $this->db->where('p.jenis_karantina', $kode);
        }

        if (!empty($f['upt']) && !in_array(strtolower($f['upt']), ['all', 'semua', '1000'])) {
            $this->db->where('p.upt_id', substr($f['upt'], 0, 2) . '00');
        }

        if (!empty($f['start_date']) && !empty($f['end_date'])) {
            $this->db->where('h.tanggal >=', $f['start_date'] . ' 00:00:00');
            $this->db->where('h.tanggal <=', $f['end_date'] . ' 23:59:59');
        }

        if (!empty($f['search'])) {
            $cols = ['h.nomor', 'p.no_dok_permohonan'];
            if ($uptJoined) {
                $cols[] = 'mu.nama';
                $cols[] = 'mu.nama_satpel';
            }
            $this->applyGlobalSearch($f['search'], $cols);
        }
    }

    public function getPaginated(array $f, int $page = 1, int $perPage = 10): array
    {
        $offset = ($page - 1) * $perPage;

        $this->db->reconnect();
        [$ids, $total] = $this->getIdsAndTotal($f, $perPage, $offset);
        $data = $this->getByIds($ids);

        return ['data' => $data, 'total' => $total];
    }

    public function getForExport(array $f): array
    {
        return $this->getFullData($f);
    }
}
