<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * @property CI_Input        $input
 * @property CI_Output       $output
 * @property CI_Config       $config
 * @property Revisi_model    $Revisi_model
 * @property Excel_handler   $excel_handler
 */
class Revisi extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Revisi_model');
        $this->load->library('excel_handler');
    }

    public function index()
    {
        $page   = max((int) $this->input->get('page', true), 1);
        $limit  = max((int) $this->input->get('per_page', true), 10);
        $offset = ($page - 1) * $limit;
        $sortBy    = $this->input->get('sort_by', true) ?: 'tgl_dok';
        $sortOrder = strtoupper($this->input->get('sort_order', true)) === 'ASC' ? 'ASC' : 'DESC';

        $karantina = strtoupper($this->input->get('karantina', true));
        if (!in_array($karantina, ['H', 'I', 'T'], true)) {
            return $this->output
                ->set_status_header(400)
                ->set_content_type('application/json')
                ->set_output(json_encode(['error' => 'Invalid karantina type']));
        }

        $filter = [
            'upt'        => $this->input->get('upt', true),
            'karantina'  => $karantina,
            'start_date' => $this->input->get('start_date', true),
            'end_date'   => $this->input->get('end_date', true),
            'search'     => $this->input->get('search', true),
            'sort_by'    => $sortBy,
            'sort_order' => $sortOrder,
        ];

        $ids   = $this->Revisi_model->getIds($filter, $limit, $offset);
        $data  = $ids ? $this->Revisi_model->getByIds($ids, $filter['karantina'], $sortBy, $sortOrder) : [];
        $total = $this->Revisi_model->countAll($filter);

        return $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'data' => $data,
                'meta' => [
                    'page'       => $page,
                    'per_page'   => $limit,
                    'total'      => $total,
                    'total_page' => ceil($total / $limit),
                ],
            ]));
    }

    public function export_excel()
    {
        error_reporting(0);
        $filters = [
            'upt'        => $this->input->get('upt', TRUE),
            'karantina'  => strtoupper(trim($this->input->get('karantina', TRUE))),
            'start_date' => $this->input->get('start_date', TRUE),
            'end_date'   => $this->input->get('end_date', TRUE),
            'search'     => $this->input->get('search', true),
        ];

        if (!in_array($filters['karantina'], ['H','I','T'], true)) {
            return $this->json(400);
        }

        $total = $this->Revisi_model->countAll($filters);
        $ids   = $this->Revisi_model->getIds($filters, $total, 0);

        $rows = $ids
            ? $this->Revisi_model->getByIds($ids, $filters['karantina'])
            : [];

        $headers = [
            'No', 'Sumber', 'No. Aju', 'No. Dok Permohonan', 'Tgl Dok Permohonan', 
            'UPT', 'Satpel', 'No. Dokumen Revisi', 'No. Seri', 'Tgl Dokumen', 
            'Alasan Hapus/Revisi', 'Waktu Hapus', 'Penandatangan', 'Petugas Hapus'
        ];

        $exportData = [];
        $no = 1;
        $lastAju = null;

        foreach ($rows as $r) {
            $isIdem = ($r['no_aju'] === $lastAju);
            $alasanClean = str_replace(["\r", "\n", "\t"], " ", $r['alasan_delete'] ?? '');

            $exportData[] = [
                $isIdem ? '' : $no++,
                $isIdem ? 'Idem' : ($r['sumber'] ?? '-'),
                $isIdem ? 'Idem' : ($r['no_aju'] ?? '-'),
                $isIdem ? 'Idem' : ($r['no_dok_permohonan'] ?? '-'),
                $isIdem ? '' : ($r['tgl_dok_permohonan'] ?? '-'),
                $isIdem ? '' : ($r['upt'] ?? '-'),
                $isIdem ? '' : ($r['nama_satpel'] ?? '-'),
                
                $r['no_dok'] ?? '-',
                $r['nomor_seri'] ?? '-',
                $r['tgl_dok'] ?? '-',
                $alasanClean,
                $r['deleted_at'] ?? '-',
                $r['penandatangan'] ?? '-',
                $r['yang_menghapus'] ?? '-'
            ];
            $lastAju = $r['no_aju'];
        }

        $title = "LAPORAN REVISI - " . $filters['karantina'];
        $reportInfo = $this->buildReportHeader($title, $filters);

        return $this->excel_handler->download("Laporan_Revisi_Dokumen", $headers, $exportData, $reportInfo);
    }
}