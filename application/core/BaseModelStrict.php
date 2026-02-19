<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class BaseModelStrict extends CI_Model
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Helper untuk Sorting
     */
    protected function applySorting($sortBy, $sortOrder, $map, $default = [])
    {
        $column = $map[$sortBy] ?? $default[0];
        $order = strtoupper($sortOrder ?? '');
        if (!in_array($order, ['ASC', 'DESC'])) {
            $order = $default[1] ?? 'DESC';
        }
        $this->db->order_by($column, $order);
    }

    /**
     * Helper untuk Search
     */
    protected function applyGlobalSearch($search, $columns)
    {
        if (empty($search) || empty($columns)) return;

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

    /**
     * Helper Date Filter
     */
    protected function applyDateFilter($field, $filter)
    {
        if (!empty($filter['start_date'])) {
           $this->db->where($field . ' >=', $filter['start_date'] . ' 00:00:00');
        }
        if (!empty($filter['end_date'])) {
           $this->db->where($field . ' <=', $filter['end_date'] . ' 23:59:59');
        }
    }

    /**
     * Jalankan getIdsQuery lalu paginate. Child model cukup implementasi getIdsQuery().
     * Child yang override boleh tanpa type hint (PHP 8 mengizinkan parameter lebih lebar/contravariant).
     */
    public function getIds(array $f, int $limit, int $offset): array
    {
        $this->getIdsQuery($f);

        if ($limit > 0) {
            $this->db->limit($limit, $offset);
        }

        $query = $this->db->get();
        if (!$query) return [];
        return array_column($query->result_array(), 'id');
    }

    public function getList($f, $limit, $offset)
    {
        $ids = $this->getIds($f, $limit, $offset);
        if (empty($ids)) return [];
        return $this->getByIds($ids);
    }

    public function countAll($f)
    {
        $this->countAllQuery($f);

        $query = $this->db->get();
        $row = $query ? $query->row() : null;
        return $row ? (int) $row->total : 0;
    }

    // Placeholder — child model implements these instead of overriding getIds/countAll
    protected function getIdsQuery($f) {}
    protected function countAllQuery($f) {}
    public function getByIds($ids) { return []; }
}
