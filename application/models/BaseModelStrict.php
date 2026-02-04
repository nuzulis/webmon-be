<?php
defined('BASEPATH') OR exit('No direct script access allowed');

abstract class BaseModelStrict extends CI_Model
{
    /**
     * Helper untuk filter tanggal dengan range waktu penuh
     * @param string $field Nama kolom di database (misal: 'p.tgl_permohonan')
     * @param array $filter Data dari input GET ($f)
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

    public function getIds($filter, $limit, $offset)
    {
        $this->getIdsQuery($filter);
        $this->db->limit($limit, $offset);
        $res = $this->db->get()->result_array();
        return array_column($res, 'id');
    }

    public function getList($filter, $limit, $offset)
    {
        $ids = $this->getIds($filter, $limit, $offset);
        if (empty($ids)) return [];
        return $this->getByIds($ids);
    }

    public function countAll($filter)
    {
        $this->countAllQuery($filter);
        $res = $this->db->get()->row();
        return $res ? (int) $res->total : 0;
    }

    protected function getIdsQuery($filter) {}
    protected function countAllQuery($filter) {}
    public function getByIds($ids) { return []; }
}