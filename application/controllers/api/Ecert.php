<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * @property CI_Input       $input
 * @property CI_Output      $output
 * @property CI_Config      $config
 * @property Ecert_model    $Ecert_model
 * @property CI_Session     $session
 * @property Excel_handler  $excel_handler
 */
class Ecert extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Ecert_model');
        $this->load->library('session');
    }

    public function index()
    {
        $filters = [
            'karantina'  => trim($this->input->get('karantina', TRUE)),
            'start_date' => $this->input->get('start_date', TRUE),
            'end_date'   => $this->input->get('end_date', TRUE),
            'negara'     => $this->input->get('negara', TRUE),
            'upt'        => $this->input->get('upt', TRUE),
            'search'     => $this->input->get('search', TRUE),
            'sort_by'    => $this->input->get('sort_by', TRUE),
            'sort_order' => $this->input->get('sort_order', TRUE),
        ];

        $page    = max((int) $this->input->get('page'), 1);
        $perPage = (int) $this->input->get('per_page') ?: 10;
        $offset  = ($page - 1) * $perPage;

        $ids   = $this->Ecert_model->getIds($filters, $perPage, $offset);
        $data  = $this->Ecert_model->getByIds($ids);
        $total = $this->Ecert_model->countAll($filters);

        return $this->json([
            'success' => true,
            'data'    => $this->formatData($data),
            'meta'    => [
                'page'       => $page,
                'per_page'   => $perPage,
                'total'      => $total,
                'total_page' => (int) ceil($total / $perPage)
            ]
        ], 200);
    }

    public function export_excel()
    {
        $filters = [
            'karantina'  => trim($this->input->get('karantina', TRUE)),
            'start_date' => $this->input->get('start_date', TRUE),
            'end_date'   => $this->input->get('end_date', TRUE),
            'negara'     => $this->input->get('negara', TRUE),
            'upt'        => $this->input->get('upt', TRUE),
            'search'     => $this->input->get('search', TRUE),
        ];
        if (empty($filters['karantina']) || empty($filters['start_date']) || empty($filters['end_date'])) {
            return $this->json([
                'success' => false,
                'message' => 'Parameter karantina, start_date, dan end_date wajib diisi'
            ], 400);
        }

        $rows = $this->Ecert_model->getFullData($filters);

        if (empty($rows)) {
            return $this->json([
                'success' => false,
                'message' => 'Tidak ada data untuk diekspor'
            ], 404);
        }

        $headers = [
            'No.',
            'No. Sertifikat',
            'Tanggal Sertifikat',
            'Komoditas',
            'Jumlah',
            'Satuan',
            'Negara Asal',
            'Destinasi (Indonesia)',
            'Pelabuhan Tujuan',
            'UPT Tujuan'
        ];

        $exportData = [];
        $no = 1;
        foreach ($rows as $r) {
            $exportData[] = [
                $no++,
                $r['no_cert'] ?? '-',
                $r['tgl_cert'] ?? '-',
                $r['komo_eng'] ?? '-',
                $r['jml_berat'] ?? '-',
                $r['satuan'] ?? '-',
                $r['neg_asal'] ?? '-',
                $r['tujuan'] ?? '-',
                $r['port_tujuan'] ?? '-',
                $r['upt'] ?? '-'
            ];
        }

        $title = "LAPORAN INCOMING E-CERT " . strtoupper($filters['karantina']);
        $reportInfo = [
            'judul'   => $title,
            'periode' => "Periode: " . ($filters['start_date'] ?? '-') . " s/d " . ($filters['end_date'] ?? '-'),
            'negara'  => !empty($filters['negara']) ? "Negara: " . $filters['negara'] : "Negara: Semua",
            'upt'     => !empty($filters['upt']) && $filters['upt'] !== 'all' ? "UPT: " . $filters['upt'] : "UPT: Semua",
            'cetak'   => "Dicetak: " . date('Y-m-d H:i:s') . " | Oleh: " . ($this->user['nama'] ?? 'Admin')
        ];

        $this->logActivity("EXPORT EXCEL: E-Cert Periode {$filters['start_date']} s/d {$filters['end_date']}");

        $this->load->library('excel_handler');
        return $this->excel_handler->download(
            "Ecert_" . strtoupper($filters['karantina']) . "_" . date('Ymd'),
            $headers,
            $exportData,
            $reportInfo
        );
    }

    private function formatData(array $rows): array
    {
        return array_map(function ($r) {
            return [
                'no_cert'      => $r['no_cert'] ?? '',
                'tgl_cert'     => $r['tgl_cert'] ?? '',
                'komoditas'    => $r['komo_eng'] ?? '',
                'jml_berat'    => $r['jml_berat'] ?? '',
                'satuan'       => $r['satuan'] ?? '',
                'negara_asal'  => $r['neg_asal'] ?? '',
                'tujuan'       => $r['tujuan'] ?? '',
                'port_tujuan'  => $r['port_tujuan'] ?? '',
                'upt'          => $r['upt'] ?? '',
                'id_cert'      => $r['id_cert'] ?? '',
                'data_from'    => $r['data_from'] ?? '',
            ];
        }, $rows);
    }
}