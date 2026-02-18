<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**

 * Dashboard API Controller

 * @property Dashboard_model $Dashboard_model

 * @property CI_Cache $cache

 */
class Dashboard extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Dashboard_model');
        $this->load->driver('cache', ['adapter' => 'file']);
    }

    public function freq_3p()
    {
        $filter = [
            'upt_id' => $this->input->get('upt_id', true) ?: 'all',
            'year'   => $this->input->get('year', true) ?: date('Y'),
        ];

        $cacheKey = "freq3p_{$filter['upt_id']}_{$filter['year']}";

        if (!$data = $this->cache->get($cacheKey)) {
            $data = $this->Dashboard_model->get_freq_3p($filter);
            $this->cache->save($cacheKey, $data, 3600); // Simpan 1 jam
        }

        return $this->jsonRes(200, ['success' => true, 'data' => $data]);
    }

    public function freq_permohonan()
    {
        $filter = [
            'upt_id' => $this->input->get('upt_id', true) ?: 'all',
            'year'   => $this->input->get('year', true) ?: date('Y'),
        ];

        $cacheKey = "freq_perm_{$filter['upt_id']}_{$filter['year']}";

        if (!$data = $this->cache->get($cacheKey)) {
            $data = $this->Dashboard_model->get_freq_permohonan($filter);
            $this->cache->save($cacheKey, $data, 3600);
        }

        return $this->jsonRes(200, ['success' => true, 'data' => $data]);
    }

     public function sla_ekspor()

    {
        return $this->handleSlaRequest('EX');
    }


    public function sla_impor()
    {
        return $this->handleSlaRequest('IM');
    }

    private function handleSlaRequest($jenis)
    {
        $filter = [
            'upt_id' => $this->input->get('upt_id', true),
            'year'   => date('Y'),
        ];

        $uptId    = $filter['upt_id'] ?: 'all';
        $cacheKey = "sla_v2_{$jenis}_{$uptId}_{$filter['year']}";
        if (!$data = $this->cache->get($cacheKey)) {
            $data = $this->Dashboard_model->get_sla_combined($jenis, $filter);
            $this->cache->save($cacheKey, $data, 3600);
        }

        return $this->jsonRes(200, [
            'success' => true,
            'data'    => $data
        ]);

    }

    public function pnbp()
    {
        $filter = [
            'upt_id' => $this->input->get('upt_id', true) ?: 'all',
            'jns'    => strtoupper($this->input->get('jns', true) ?: 'Y'),
            'year'   => (int) ($this->input->get('year', true) ?: date('Y')),
            'month'  => $this->input->get('month', true) ?: date('m'),
        ];

        $cacheKey = "pnbp_v2_{$filter['upt_id']}_{$filter['jns']}_{$filter['year']}_{$filter['month']}";
        if (!$data = $this->cache->get($cacheKey)) {
            $data = $this->Dashboard_model->get_pnbp($filter);
            $this->cache->save($cacheKey, $data, 43200); 
        }

        return $this->jsonRes(200, [
            'success' => true,
            'data'    => $data
        ]);
    }

    public function pnbp_potensi() 
{
    $uptId = $this->input->get('upt_id');
    $year  = $this->input->get('year') ?? date('Y');
    $month = $this->input->get('month') ?? date('m');

    $result = $this->Dashboard_model->get_potensi_simponi($uptId, $year, $month);

    // Jika Anda menggunakan MY_Controller yang punya fungsi jsonRes
    return $this->jsonRes(200, $result);

    // Atau jika menggunakan json standar:
    // return $this->json($result);
}

    public function top_komoditi()
    {
        $jenis = strtoupper($this->input->get('lingkup', true));
        $kar   = strtolower($this->input->get('karantina', true) ?: 'kt');
        $upt   = $this->input->get('upt_id', true) ?: 'all';
        $year  = date('Y');

        if (!in_array($jenis, ['EX', 'IM'])) {
            return $this->jsonRes(400, ['success' => false, 'message' => 'Lingkup invalid']);
        }

        $cacheKey = "top_kom_{$jenis}_{$kar}_{$upt}_{$year}";

        if (!$data = $this->cache->get($cacheKey)) {
            $filter = [
                'upt_id'    => $upt,
                'year'      => $year,
                'karantina' => $kar,
                'limit'     => 5,
            ];
            $data = $this->Dashboard_model->get_top_komoditi($jenis, $filter);
            $this->cache->save($cacheKey, $data, 3600);
        }

        return $this->jsonRes(200, ['success' => true, 'data' => $data]);
    }
}