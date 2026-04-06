<?php
defined('BASEPATH') OR exit('No direct script access allowed');
ini_set('memory_limit', '512M');
/**
 * @property CI_Input         $input
 * @property Permohonan_model $Permohonan_model
 * @property Excel_handler    $excel_handler
 */
class Permohonan extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Permohonan_model');
        $this->load->library('excel_handler');
        $this->load->helper('sla');
    }

    public function index()
    {
        $filters = [
            'upt'        => $this->input->get('upt', TRUE),
            'karantina'  => strtoupper(trim($this->input->get('karantina', TRUE))),
            'lingkup'    => $this->input->get('lingkup', TRUE),
            'start_date' => $this->input->get('start_date', TRUE),
            'end_date'   => $this->input->get('end_date', TRUE),
        ];

        if (!empty($filters['karantina'])) {
            if (!in_array($filters['karantina'], ['H', 'I', 'T'], true)) {
                return $this->json([
                    'success' => false,
                    'message' => 'Karantina tidak valid'
                ], 400);
            }
        }

        $data = $this->Permohonan_model->getAll($filters);

        return $this->json([
            'success' => true,
            'data'    => $data,
        ], 200);
    }

    public function export_excel()
    {
        $filters = [
            'upt'        => $this->input->get('upt', TRUE),
            'karantina'  => strtoupper(trim($this->input->get('karantina', TRUE))),
            'lingkup'    => $this->input->get('lingkup', TRUE),
            'start_date' => $this->input->get('start_date', TRUE),
            'end_date'   => $this->input->get('end_date', TRUE),
        ];

        $rows = $this->Permohonan_model->getForExcel($filters);

        if (empty($rows)) {
            return $this->json([
                'success' => false,
                'message' => 'Data tidak ditemukan'
            ], 404);
        }

        $headers = [
            'No.', 'Sumber', 'No. Aju', 'Tgl Aju', 'No. K.1.1', 'Tgl K.1.1',
            'UPT', 'Satpel', 'Tempat Periksa', 'Pemohon', 'Pengirim', 'Penerima',
            'Asal', 'Tujuan', 'Alat Angkut', 'Kemasan', 'Jml',
            'Komoditas', 'Nama Tercetak', 'HS Code',
            'P1', 'P2', 'P3', 'P4', 'P5',
            'Satuan', 'Nilai (Rp)', 'SLA', 'Risiko'
        ];

        $exportData = [];
        $no = 1;
        $lastAju = null;
        $now = date('Y-m-d H:i:s');

        foreach ($rows as $r) {
            $isIdem = ($lastAju !== null && $r['no_aju'] === $lastAju);
            
            $slaLabel = '';
            if (!$isIdem) {
                $sla = hitung_sla($r['tgl_periksa'], $now);
                $slaLabel = $sla ? $sla['label'] : '-';
            }

            $exportData[] = [
                $isIdem ? '' : $no,
                ($r['tssm_id'] ? 'SSM' : 'PTK'),
                ($r['no_aju'] ?? ''),
                ($r['tgl_aju'] ?? ''),
                ($r['no_dok_permohonan'] ?? ''),
                ($r['tgl_dok_permohonan'] ?? ''),
                ($r['upt'] ?? ''),
                ($r['satpel'] ?? ''),
                ($r['nama_tempat_pemeriksaan'] ?? ''),
                ($r['nama_pemohon'] ?? ''),
                ($r['nama_pengirim'] ?? ''),
                ($r['nama_penerima'] ?? ''),
                ($r['asal'] ?: $r['kota_asal']),
                ($r['tujuan'] ?: $r['kota_tujuan']),
                ($r['nama_alat_angkut_terakhir'] ?? ''),
                ($r['kemas'] ?? ''),
                ($r['total_kemas'] ?? ''),
                $r['komoditas'] ?? '',
                $r['nama_umum_tercetak'] ?? '',
                $r['hs'] ?? '',
                (float) ($r['p1'] ?? 0),
                (float) ($r['p2'] ?? 0),
                (float) ($r['p3'] ?? 0),
                (float) ($r['p4'] ?? 0),
                (float) ($r['p5'] ?? 0),
                $r['satuan'] ?? '',
                $r['harga_rp'] ?? '',
                $slaLabel,
                $r['risiko'] ?? ''
            ];
            
            if (!$isIdem) {
                $no++;
            }

            $lastAju = $r['no_aju'];
        }

        $title = "LAPORAN PERMOHONAN KARANTINA " . ($filters['karantina'] ?: 'ALL');
        $reportInfo = $this->buildReportHeader($title, $filters, $rows);

        $this->logActivity("EXPORT EXCEL: Permohonan {$filters['karantina']}");

        return $this->excel_handler->download(
            "Laporan_Permohonan",
            $headers,
            $exportData,
            $reportInfo
        );
    }
}