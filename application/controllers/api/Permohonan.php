<?php
defined('BASEPATH') OR exit('No direct script access allowed');

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
            'search'     => $this->input->get('search', TRUE),
            'sort_by'    => $this->input->get('sort_by', TRUE),
            'sort_order' => $this->input->get('sort_order', TRUE),
        ];

        if (!empty($filters['karantina'])) {
            if (!in_array($filters['karantina'], ['H', 'I', 'T'], true)) {
                return $this->json([
                    'success' => false,
                    'message' => 'Karantina tidak valid'
                ], 400);
            }
        }

        $page    = max((int) $this->input->get('page'), 1);
        $perPage = (int) $this->input->get('per_page') ?: 10;
        $offset  = ($page - 1) * $perPage;
        $ids   = $this->Permohonan_model->getIds($filters, $perPage, $offset);
        $data  = $this->Permohonan_model->getByIds($ids, $filters['karantina']);
        $total = $this->Permohonan_model->countAll($filters);

        return $this->json([
            'success' => true,
            'data'    => $data,
            'meta'    => [
                'page'       => $page,
                'per_page'   => $perPage,
                'total'      => $total,
                'total_page' => (int) ceil($total / $perPage),
            ]
        ], 200);
    }

    public function export_excel()
    {
        $filters = $this->_get_filters();

        $ids  = $this->Permohonan_model->getIds($filters, 5000, 0);
        $rows = $this->Permohonan_model->getByIdsExport($ids, strtoupper($filters['karantina'] ?? 'H'));

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
                $isIdem ? 'Idem' : ($r['tssm_id'] ? 'SSM' : 'PTK'),
                $isIdem ? 'Idem' : ($r['no_aju'] ?? ''),
                $isIdem ? 'Idem' : ($r['tgl_aju'] ?? ''),
                $isIdem ? 'Idem' : ($r['no_dok_permohonan'] ?? ''),
                $isIdem ? 'Idem' : ($r['tgl_dok_permohonan'] ?? ''),
                $isIdem ? 'Idem' : ($r['upt'] ?? ''),
                $isIdem ? 'Idem' : ($r['satpel'] ?? ''),
                $isIdem ? 'Idem' : ($r['nama_tempat_pemeriksaan'] ?? ''),
                $isIdem ? 'Idem' : ($r['nama_pemohon'] ?? ''),
                $isIdem ? 'Idem' : ($r['nama_pengirim'] ?? ''),
                $isIdem ? 'Idem' : ($r['nama_penerima'] ?? ''),
                $isIdem ? 'Idem' : ($r['asal'] ?: $r['kota_asal']),
                $isIdem ? 'Idem' : ($r['tujuan'] ?: $r['kota_tujuan']),
                $isIdem ? 'Idem' : ($r['nama_alat_angkut_terakhir'] ?? ''),
                $isIdem ? 'Idem' : ($r['kemas'] ?? ''),
                $isIdem ? 'Idem' : ($r['total_kemas'] ?? ''),
                $r['komoditas'] ?? '',
                $r['nama_umum_tercetak'] ?? '',
                $r['hs'] ?? '',
                $r['p1'] ?? '',
                $r['p2'] ?? '',
                $r['p3'] ?? '',
                $r['p4'] ?? '',
                $r['p5'] ?? '',
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

        $title = "LAPORAN PERMOHONAN KARANTINA (K.1.1) - " . ($filters['karantina'] ?: 'ALL');
        $reportInfo = $this->buildReportHeader($title, $filters, $rows);

        $this->logActivity("EXPORT EXCEL: Permohonan {$filters['karantina']}");

        return $this->excel_handler->download(
            "Laporan_Permohonan",
            $headers,
            $exportData,
            $reportInfo
        );
    }

    private function _get_filters()
    {
        return [
            'upt'        => $this->input->get('upt', TRUE),
            'karantina'  => strtoupper(trim($this->input->get('karantina', TRUE))),
            'lingkup'    => $this->input->get('lingkup', TRUE),
            'start_date' => $this->input->get('start_date', TRUE),
            'end_date'   => $this->input->get('end_date', TRUE),
            'search'     => $this->input->get('search', TRUE),
        ];
    }
}