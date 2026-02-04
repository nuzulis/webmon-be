<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class BaseModelStrict extends CI_Model
{
   public function getList(array $filter, int $limit, int $offset): array
    {
        $ids = $this->getIds($filter, $limit, $offset);
        return $this->getByIds($ids);
    }

    public function getIds(array $filter, int $limit, int $offset): array
    {
        $this->getIdsQuery($filter);

        $this->db
            ->order_by('last_tgl', 'DESC')
            ->limit($limit, $offset);

        return array_column(
            $this->db->get()->result_array(),
            'id'
        );
    }

    public function countAll(array $filter): int
    {
        $this->countAllQuery($filter);
        $res = $this->db->get()->row();
        return $res ? (int) $res->total : 0;
    }
    
    public function getByIds(array $ids): array 
    { 
        return [];
    }

    protected function getIdsQuery(array $filter): void {}
    protected function countAllQuery(array $filter): void {}
   protected function applyCommonFilter(array $filter): void
    {
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