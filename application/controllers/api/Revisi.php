<?php
defined('BASEPATH') OR exit('No direct script access allowed');
ini_set('memory_limit', '512M');
/**
 * @property CI_Input        $input
 * @property CI_Output       $output
 * @property Revisi_model    $Revisi_model
 * @property Excel_handler   $excel_handler
 */
class Revisi extends MY_Controller
{
    public function __construct() {
        parent::__construct();
        $this->load->model('Revisi_model');
        $this->load->library('excel_handler');
    }

    public function index() {
        $karantina = strtoupper($this->input->get('karantina', true));

        if (!in_array($karantina, ['H', 'I', 'T'], true)) {
            return $this->output->set_status_header(400)
                ->set_content_type('application/json')
                ->set_output(json_encode(['error' => 'Invalid karantina type']));
        }

        $filter = [
            'upt'        => $this->input->get('upt', true),
            'karantina'  => $karantina,
            'lingkup'    => $this->input->get('lingkup', true),
            'start_date' => $this->input->get('start_date', true),
            'end_date'   => $this->input->get('end_date', true),
        ];

        $data = $this->Revisi_model->getAll($filter);

        return $this->output->set_content_type('application/json')
            ->set_output(json_encode(['data' => $data]));
    }

    public function export_excel() {
        error_reporting(0);
        $karantina = strtoupper(trim($this->input->get('karantina', TRUE)));

        if (!in_array($karantina, ['H', 'I', 'T'], true)) {
            return $this->json(400);
        }

        $filters = [
            'upt'        => $this->input->get('upt', TRUE),
            'karantina'  => $karantina,
            'lingkup'    => $this->input->get('lingkup', TRUE),
            'start_date' => $this->input->get('start_date', TRUE),
            'end_date'   => $this->input->get('end_date', TRUE),
        ];

        $rows = $this->Revisi_model->getForExcel($filters);

        $headers = [
            'No', 'Sumber', 'No. Aju', 'No. Dok Permohonan', 'Tgl Dok Permohonan',
            'UPT', 'Satpel', 'No. Dokumen Revisi', 'No. Seri', 'Tgl Dokumen',
            'Alasan Hapus/Revisi', 'Waktu Hapus', 'Penandatangan', 'Petugas Hapus'
        ];

        $exportData = [];
        $no      = 1;
        $lastAju = null;

        foreach ($rows as $r) {
            $isIdem       = ($r['no_aju'] === $lastAju);
            $alasanClean  = str_replace(["\r", "\n", "\t"], " ", $r['alasan_delete'] ?? '');

            $exportData[] = [
                $isIdem ? '' : $no++,
                $r['sumber'] ?? '-', $r['no_aju'] ?? '-',
                $r['no_dok_permohonan'] ?? '-', $r['tgl_dok_permohonan'] ?? '-',
                $r['upt'] ?? '-', $r['nama_satpel'] ?? '-',
                $r['no_dok'] ?? '-', $r['nomor_seri'] ?? '-',
                $r['tgl_dok'] ?? '-', $alasanClean,
                $r['deleted_at'] ?? '-', $r['penandatangan'] ?? '-',
                $r['yang_menghapus'] ?? '-'
            ];

            $lastAju = $r['no_aju'];
        }

        $title      = "LAPORAN REVISI - " . $filters['karantina'];
        $reportInfo = $this->buildReportHeader($title, $filters);
        return $this->excel_handler->download("Laporan_Revisi_Dokumen", $headers, $exportData, $reportInfo);
    }
}
