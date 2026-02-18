<?php
defined('BASEPATH') OR exit('No direct script access allowed');

abstract class BaseModelStrict extends CI_Model
{
    public function getList($filter, $limit, $offset) {
        $ids = $this->getIds($filter, $limit, $offset);
        return $this->getByIds($ids);
    }

    public function getIds($filter, $limit, $offset) { return []; }
    public function getByIds($ids) { return []; }
    public function countAll($filter) { return 0; }

    protected function applyGlobalSearch($search, $columns = []) {
        if (empty($search) || empty($columns)) return;
        
        $this->db->group_start();
        foreach ($columns as $column) {
            $this->db->or_like($column, $search);
        }
        $this->db->group_end(); 
    }

    protected function applySorting($sortBy, $sortOrder, $sortMap = [], $defaultSort = ['id', 'DESC']) {
        if (empty($sortBy)) {
            $this->db->order_by($defaultSort[0], $defaultSort[1]);
            return;
        }
        $realColumn = isset($sortMap[$sortBy]) ? $sortMap[$sortBy] : null;
        if ($realColumn) {
            $order = (strtoupper($sortOrder) === 'DESC') ? 'DESC' : 'ASC';
            $this->db->order_by($realColumn, $order);
        } else {
            $this->db->order_by($defaultSort[0], $defaultSort[1]);
        }
    }

    protected function applyCommonFilter($filter) {
         /** @var MY_Controller $CI */
        $CI =& get_instance();
        $user = isset($CI->user) ? $CI->user : null;

        if (!$user) return;

        $userUpt = (string) ($user['upt'] ?? '');
        if ($userUpt !== '' && $userUpt !== '1000') {
            $this->db->where('p.kode_satpel', $userUpt);
        } elseif (!empty($filter['upt_id'])) {
            $this->db->where('p.kode_satpel', (string) $filter['upt_id']);
        }

        if (isset($user['detil']) && is_array($user['detil'])) {
            foreach ($user['detil'] as $r) {
                $role = $r['role_name'] ?? '';
                if ($role === 'DEP-KH') $this->db->where('p.jenis_karantina', 'H');
                if ($role === 'DEP-KT') $this->db->where('p.jenis_karantina', 'T');
                if ($role === 'DEP-KI') $this->db->where('p.jenis_karantina', 'I');
            }
        }
    }
}