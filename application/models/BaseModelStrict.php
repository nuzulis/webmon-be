<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Base model STRICT untuk semua list Webmon
 * CI3-safe, Intelephense-safe
 */
abstract class BaseModelStrict extends CI_Model
{
    // ===== FUNGSI UTAMA (BRIDGE) =====
    
    /**
     * Tambahkan fungsi ini agar Controller bisa memanggil $this->model->getList()
     */
    public function getList($filter, $limit, $offset)
    {
        $ids = $this->getIds($filter, $limit, $offset);
        
        if (empty($ids)) {
            return [];
        }

        return $this->getByIds($ids);
    }

    // ===== CONTRACT (Wajib ada di Model Anak) =====
    abstract public function getIds($filter, $limit, $offset);
    abstract public function getByIds($ids);
    abstract public function countAll($filter);

    // ===== COMMON FILTER =====
    // BaseModelStrict.php

protected function applyCommonFilter($f)
{
    // Gunakan alias 'p' karena di query Monitoring_model Anda menulis "FROM ptk p"
    $alias = 'p'; 

    if (!empty($f['upt_id'])) {
        $this->db->where("$alias.upt_id", $f['upt_id']);
    }

    if (!empty($f['karantina'])) {
        $this->db->where("$alias.jenis_karantina", $f['karantina']);
    }

    if (!empty($f['permohonan'])) {
        $this->db->where("$alias.jenis_permohonan", $f['permohonan']);
    }
}

    protected function applyDateFilter($field, $f)
    {
        if (!empty($f['start_date'])) {
            $this->db->where("DATE($field) >=", $f['start_date']);
        }

        if (!empty($f['end_date'])) {
            $this->db->where("DATE($field) <=", $f['end_date']);
        }
    }
}