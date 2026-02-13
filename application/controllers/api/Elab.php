<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * @property CI_Input      $input
 * @property Elab_model    $Elab_model
 * @property Excel_handler $excel_handler
 */
class Elab extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Elab_model');
        $this->load->library('excel_handler');
    }

    public function index()
    {
        $filters = [
            'upt_id'     => $this->input->get('upt', TRUE),
            'karantina'  => strtoupper(trim($this->input->get('karantina', TRUE))),
            'lingkup'    => strtoupper(trim($this->input->get('lingkup', TRUE))),
            'start_date' => $this->input->get('start_date', TRUE),
            'end_date'   => $this->input->get('end_date', TRUE),
            'search'     => $this->input->get('search', TRUE),
            'sort_by'    => $this->input->get('sort_by', TRUE),
            'sort_order' => $this->input->get('sort_order', TRUE),
        ];

        $this->applyScope($filters);

        $page    = max((int)$this->input->get('page'), 1);
        $perPage = (int)$this->input->get('per_page') ?: 10;
        $offset  = ($page - 1) * $perPage;
        $ids   = $this->Elab_model->getIds($filters, $perPage, $offset);
        $total = $this->Elab_model->countAll($filters);
        $rows  = [];

        if (!empty($ids)) {
            $dataRaw = $this->Elab_model->getByIds($ids);
            foreach ($dataRaw as $r) {
                $r['komoditas']       = str_replace('||', '<br>', $r['komoditas_list'] ?? '');
                $r['nama_target_uji'] = str_replace('||', '<br>', $r['target_list'] ?? '');
                $r['nama_metode_uji'] = str_replace('||', '<br>', $r['metode_list'] ?? '');
                $r['hasil_uji']       = str_replace('||', '<br>', $r['hasil_list'] ?? '');
                $rows[] = $r;
            }
        }

        return $this->output
            ->set_content_type('application/json', 'utf-8')
            ->set_output(json_encode([
                'success' => true,
                'data'    => $rows,
                'meta'    => [
                    'page'       => $page,
                    'per_page'   => $perPage,
                    'total'      => $total,
                    'total_page' => (int) ceil($total / $perPage),
                ]
            ], JSON_UNESCAPED_UNICODE));
    }

    public function export_excel() 
    {
        $filters = [
            'karantina'  => $this->input->get('karantina', TRUE),
            'upt_id'     => $this->input->get('upt', TRUE),
            'lingkup'    => $this->input->get('lingkup', TRUE),
            'start_date' => $this->input->get('start_date', TRUE),
            'end_date'   => $this->input->get('end_date', TRUE),
            'search'     => $this->input->get('search', TRUE),
        ];
        $rows = $this->Elab_model->getFullData($filters);

        if (empty($rows)) {
            return $this->json([
                'success' => false,
                'message' => 'Data eLab tidak ditemukan'
            ], 404);
        }

        $headers = [
            'No.', 
            'No. Verifikasi', 'Tgl. Verifikasi', 'UPT / Satpel', 'Nama Pemilik',
            'Komoditas', 'ID Sampel', 'No. ST', 
            'Parameter Uji', 'Metode', 'Tgl Uji', 'Analis', 'Hasil Pengujian', 
            'Kesimpulan', 'Petugas Rilis'
        ];

        $exportData = [];
        $no = 1;

        foreach ($rows as $r) {
            $listKom    = explode('||', $r['komoditas_list'] ?? '');
            $listKode   = explode('||', $r['kode_sampel_list'] ?? '');
            $listST     = explode('||', $r['st_list'] ?? '');
            $listTarget = explode('||', $r['target_list'] ?? '');
            $listMetode = explode('||', $r['metode_list'] ?? '');
            $listTgl    = explode('||', $r['tgl_uji_list'] ?? '');
            $listAnalis = explode('||', $r['analis_list'] ?? '');
            $listHasil  = explode('||', $r['hasil_full_list'] ?? '');

            $rowCount = count($listTarget);
            
            for ($i = 0; $i < $rowCount; $i++) {
                $isFirst = ($i === 0); 

                $exportData[] = [
                    $isFirst ? $no : '', 
                    $isFirst ? $r['no_verifikasi'] : '', 
                    $isFirst ? $r['tgl_verifikasi'] : '', 
                    $isFirst ? ($r['upt'] . ' - ' . $r['satpel']) : '',
                    $isFirst ? $r['namaPemilik'] : '',
                    $listKom[$i] ?? ($listKom[0] ?? '-'),
                    $listKode[$i] ?? '-',
                    $listST[$i] ?? '-',
                    $listTarget[$i] ?? '-',
                    $listMetode[$i] ?? '-',
                    $listTgl[$i] ?? '-',
                    $listAnalis[$i] ?? '-',
                    $listHasil[$i] ?? '-',
                    $isFirst ? $r['kesimpulan'] : '',
                    $isFirst ? $r['petugas_rilis'] : ''
                ];
            }
            $no++; 
        }

        $title = "LAPORAN PENGUJIAN LABORATORIUM (e-LAB)";
        $reportInfo = $this->buildReportHeader($title, $filters, $rows);
        
        $this->logActivity("EXPORT EXCEL: ELAB LENGKAP PERIODE {$filters['start_date']}");

        return $this->excel_handler->download("eLab_Laporan_Lengkap", $headers, $exportData, $reportInfo);
    }
}