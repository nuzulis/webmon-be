<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'core/BaseModelStrict.php';

class PelepasanBlmByr_model extends BaseModelStrict
{
    private $_cachedCount = null;
    private $_filterHash  = null;
    private $_cachedData  = [];

    private function getTable(string $karantina): string
    {
        return match (strtoupper($karantina)) {
            'H'     => 'pn_pelepasan_kh',
            'I'     => 'pn_pelepasan_ki',
            default => 'pn_pelepasan_kt',
        };
    }

    private function buildWhereClause(array $f): string
    {
        $where = "p.is_batal = '0'
              AND p8.deleted_at = '1970-01-01 08:00:00'
              AND p8.nomor_seri IS NOT NULL
              AND p8.nomor_seri <> '*******'
              AND p8.dokumen_karantina_id NOT IN (37, 42)";

        if (!empty($f['upt']) && !in_array(strtolower($f['upt']), ['all', 'semua'])) {
            $upt = $this->db->escape($f['upt']);
            if (strlen($f['upt']) <= 4) {
                $like = $this->db->escape($f['upt'] . '%');
                $where .= " AND (p.upt_id = $upt OR p.kode_satpel LIKE $like)";
            } else {
                $where .= " AND p.kode_satpel = $upt";
            }
        }

        if (!empty($f['start_date'])) {
            $where .= ' AND p.tgl_aju >= ' . $this->db->escape($f['start_date'] . ' 00:00:00');
        }
        if (!empty($f['end_date'])) {
            $where .= ' AND p.tgl_aju <= ' . $this->db->escape($f['end_date'] . ' 23:59:59');
        }

        if (!empty($f['search'])) {
            $s = $this->db->escape('%' . $this->db->escape_like_str($f['search']) . '%');
            $where .= " AND (p.no_aju LIKE $s OR p8.nomor LIKE $s OR p.nama_pemohon LIKE $s)";
        }

        return $where;
    }

    private function paidSubquery(): string
    {
        return "
            SELECT DISTINCT k2.ptk_id
            FROM db_pnbp.kuitansis k2
            INNER JOIN db_pnbp.req_bills b2 ON b2.id = k2.req_bill_id
            WHERE k2.tipe_bayar = 'AKHIR'
              AND k2.deleted_at IS NULL
              AND b2.deleted_at IS NULL
              AND b2.ntpn IS NOT NULL
        ";
    }

    private function buildMainSql(string $table, string $where, string $order, int $limit = 0, int $offset = 0): string
    {
        $limitClause = $limit > 0 ? "LIMIT $limit OFFSET $offset" : '';

        return "
            SELECT
                p.id, p.no_aju, p.upt_id, p.tgl_aju, p.jenis_permohonan,
                p.nama_pemohon, p.nama_penerima, p.nama_pengirim,
                MAX(p8.nomor)        AS dok,
                MAX(p8.tanggal)      AS tgl_dok,
                MAX(p8.nomor_seri)   AS nomor_seri,
                MAX(k.nomor)         AS no_kuitansi,
                MAX(k.tanggal)       AS tgl_kuitansi,
                MAX(k.total_pnbp)    AS total_pnbp,
                MAX(b.kode_bill)     AS kode_bill,
                MAX(b.ntpn)          AS ntpn,
                MAX(mp.nama)         AS user_ttd,
                MAX(mu.nama)         AS upt,
                MAX(mu.nama_satpel)  AS nama_satpel
            FROM barantin.ptk p
            INNER JOIN barantin.$table p8 ON p8.ptk_id = p.id
            LEFT JOIN barantin.master_pegawai mp ON mp.id = p8.user_ttd_id
            LEFT JOIN barantin.master_upt mu ON p.kode_satpel = mu.id
            LEFT JOIN db_pnbp.kuitansis k
                ON k.ptk_id = p.id AND k.tipe_bayar = 'AKHIR' AND k.deleted_at IS NULL
            LEFT JOIN db_pnbp.req_bills b
                ON b.id = k.req_bill_id AND b.deleted_at IS NULL
            LEFT JOIN ({$this->paidSubquery()}) paid ON paid.ptk_id = p.id
            WHERE $where
              AND paid.ptk_id IS NULL
            GROUP BY p.id, p.no_aju, p.upt_id, p.tgl_aju, p.jenis_permohonan,
                     p.nama_pemohon, p.nama_penerima, p.nama_pengirim
            ORDER BY $order
            $limitClause
        ";
    }

    private function buildCountSql(string $table, string $where): string
    {
        return "
            SELECT COUNT(DISTINCT p.id) AS total
            FROM barantin.ptk p
            INNER JOIN barantin.$table p8 ON p8.ptk_id = p.id
            LEFT JOIN ({$this->paidSubquery()}) paid ON paid.ptk_id = p.id
            WHERE $where
              AND paid.ptk_id IS NULL
        ";
    }

    public function getIds(array $f, int $limit, int $offset): array
    {
        $table = $this->getTable($f['karantina'] ?? 'T');
        $where = $this->buildWhereClause($f);
        $order = $this->buildOrderClause($f);

        $query             = $this->db->query($this->buildMainSql($table, $where, $order, $limit, $offset));
        $this->_cachedData = $query ? $query->result_array() : [];

        $countRes           = $this->db->query($this->buildCountSql($table, $where));
        $this->_cachedCount = $countRes ? (int) ($countRes->row()->total ?? 0) : 0;
        $this->_filterHash  = md5(serialize($f) . $table);

        return array_column($this->_cachedData, 'id');
    }

    public function countAll($f)
    {
        $hash = md5(serialize($f) . $this->getTable($f['karantina'] ?? 'T'));

        if ($this->_filterHash === $hash && $this->_cachedCount !== null) {
            return $this->_cachedCount;
        }

        $table    = $this->getTable($f['karantina'] ?? 'T');
        $where    = $this->buildWhereClause($f);
        $countRes = $this->db->query($this->buildCountSql($table, $where));
        return $countRes ? (int) ($countRes->row()->total ?? 0) : 0;
    }

    public function getByIds($ids)
    {
        return !empty($this->_cachedData) ? $this->_cachedData : [];
    }

    public function getFullData(array $f): array
    {
        $table = $this->getTable($f['karantina'] ?? 'T');
        $where = $this->buildWhereClause($f);
        $order = $this->buildOrderClause($f);

        $res = $this->db->query($this->buildMainSql($table, $where, $order));
        return $res ? $res->result_array() : [];
    }

    private function buildOrderClause(array $f): string
    {
        $sortMap = ['tgl_aju' => 'p.tgl_aju', 'no_aju'  => 'p.no_aju'];
        $col   = $sortMap[$f['sort_by'] ?? ''] ?? 'p.tgl_aju';
        $order = strtoupper($f['sort_order'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
        return "$col $order";
    }
}
