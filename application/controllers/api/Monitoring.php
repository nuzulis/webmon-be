<?php
defined('BASEPATH') OR exit('No direct script access allowed');
ini_set('memory_limit', '512M');
/**
 * @property CI_Input         $input
 * @property Monitoring_model $Monitoring_model
 * @property Excel_handler    $excel_handler
 */
class Monitoring extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Monitoring_model');
        $this->load->library('excel_handler');
    }
    
    public function index()
    {
        $filter = [
            'upt'        => $this->input->get('upt', TRUE),
            'lingkup'    => $this->input->get('lingkup', TRUE),
            'karantina'  => strtoupper($this->input->get('karantina', TRUE)),
            'start_date' => $this->input->get('start_date', TRUE),
            'end_date'   => $this->input->get('end_date', TRUE),
        ];

        if (!in_array($filter['karantina'], ['H', 'I', 'T'], true)) {
            return $this->json(['success' => false, 'message' => 'Parameter karantina tidak valid'], 400);
        }

        $data = $this->Monitoring_model->getAll($filter);

        return $this->json(['success' => true, 'data' => $data]);
    }

    public function export_excel()
    {
        $karantinaRaw = strtoupper($this->input->get('karantina', TRUE));    
        $map = ['H'=>'H', 'I'=>'I', 'T'=>'T', 'KH'=>'H', 'KI'=>'I', 'KT'=>'T'];

        $filter = [
            'upt'        => $this->input->get('upt', TRUE),
            'lingkup'    => $this->input->get('lingkup', TRUE),
            'karantina' => $map[$karantinaRaw] ?? 'H',
            'start_date' => $this->input->get('start_date', TRUE),
            'end_date'   => $this->input->get('end_date', TRUE),
        ];

        if (!$filter['karantina']) {
            return $this->json([
                'success' => false,
                'message' => 'Jenis karantina tidak valid'
            ], 400);
        }
        $rows = $this->Monitoring_model->getFullData($filter);

        if (empty($rows)) {
            return $this->json([
                'success' => false,
                'message' => 'Data tidak ditemukan'
            ], 404);
        }

        $headers = [
            'No.', 'Nomor Aju', 'No.K.1.1', 'Satpel', 'Pengirim', 'Penerima',
            'Tgl Permohonan', 'Tgl Periksa Fisik', 'Tgl Lepas',
            'Status', 'SLA', 'Komoditas', 'Nama Tercetak',
            'Vol P1', 'Vol P2', 'Vol P3', 'Vol P4',
            'Vol P5', 'Vol P6', 'Vol P7', 'Vol P8', 'Satuan'
        ];

        $exportData = [];
        $lastAju = null;
        $no = 0;

        foreach ($rows as $r) {
            $isIdem = ($r['no_aju'] === $lastAju);
            if (!$isIdem) {
                $no++;
            }
            
            $exportData[] = [
                $isIdem ? '' : $no,
                $r['no_aju'] ?? '-',
                $r['no_dok'] ?? '-',
                $r['upt_full'] ?? '-',
                $r['nama_pengirim'] ?? '-',
                $r['nama_penerima'] ?? '-',
                $r['tgl_dok_permohonan'] ?? '-',
                $r['tgl_periksa'] ?? '-',
                $r['tanggal_lepas'] ?? '-',
                $r['status'] ?? 'Proses',
                $r['sla'] ?? '-',
                $r['komoditas'] ?? '-',
                $r['nama_umum_tercetak'] ?? '-',
                (float) ($r['p1'] ?? 0), 
                (float) ($r['p2'] ?? 0), 
                (float) ($r['p3'] ?? 0), 
                (float) ($r['p4'] ?? 0),
                (float) ($r['p5'] ?? 0), 
                (float) ($r['p6'] ?? 0), 
                (float) ($r['p7'] ?? 0), 
                (float) ($r['p8'] ?? 0),
                
                $r['satuan'] ?? '-'
            ];

            $lastAju = $r['no_aju'];
        }
        $title = "MONITORING SLA";
        $reportInfo = $this->buildReportHeader($title, $filter, $rows);

        $this->logActivity("EXPORT EXCEL MONITORING: " . $filter['karantina']);

        if (ob_get_length()) ob_end_clean();

        return $this->excel_handler->download(
            "Monitoring_Operasional_" . date('Ymd_His'),
            $headers,
            $exportData,
            $reportInfo
        );
    }
}