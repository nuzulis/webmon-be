<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * @property Monitoring_model $Monitoring_model
 * @property ExcelSlaExporter $excelslaexporter
 */
class ExportMonitoring extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();

        // CLI only
        if (PHP_SAPI !== 'cli') {
            exit("This command can only be run via CLI\n");
        }

        $this->load->model('Monitoring_model');
        $this->load->library('ExcelSlaExporter');
        $this->load->helper('sla');
    }

    public function run()
    {
        echo "⏳ Export Monitoring SLA started...\n";

        $filters = [
            'upt'        => 'all',
            'karantina'  => 'kh',
            'permohonan' => 'DM',
            'start_date' => '2025-06-19',
            'end_date'   => '2025-06-26'
        ];

        $rows = $this->Monitoring_model->getData($filters);

        if (empty($rows)) {
            echo "⚠️ Tidak ada data\n";
            return;
        }

        $exporter = new ExcelSlaExporter();

        $exporter->setHeader([
            'UPT'        => $filters['upt'],
            'Karantina'  => strtoupper($filters['karantina']),
            'Permohonan' => $filters['permohonan'],
            'Periode'    => "{$filters['start_date']} s/d {$filters['end_date']}",
            'Dicetak'    => date('Y-m-d H:i:s')
        ]);

        $exporter->setTableHeader();

        foreach ($rows as $row) {
            $exporter->addRow([
                'no_aju'         => $row['no_aju'] ?? '',
                'no_dok'         => $row['no_dok_permohonan'] ?? '',
                'satpel'         => $row['satpel'] ?? '',
                'pengirim'       => $row['nama_pengirim'] ?? '',
                'penerima'       => $row['nama_penerima'] ?? '',
                'tgl_permohonan' => $row['tgl_dok_permohonan'] ?? '',
                'tgl_periksa'    => $row['tgl_periksa'] ?? null,
                'tgl_lepas'      => $row['tgl_lepas'] ?? null,
                'status'         => $row['status'] ?? '',
                'komoditas'      => $row['komoditas'] ?? '',
                'nama_umum'      => $row['nama_umum_tercetak'] ?? '',
                'p1'             => $row['p1'] ?? 0,
                'p2'             => $row['p2'] ?? 0,
                'satuan'         => $row['satuan'] ?? ''
            ]);
        }

        $path = FCPATH . 'exports/monitoring_sla_' . date('Ymd_His') . '.xlsx';

        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        $exporter->save($path);

        echo "✅ Export selesai\n";
        echo "📄 File: {$path}\n";
    }
}
