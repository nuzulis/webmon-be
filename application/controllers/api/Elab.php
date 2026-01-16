<?php
defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * @property Elab_model $Elab_model
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

    /**
     * GET /api/elab
     */
    public function index()
    {
        /* 1. JWT & Feature Check: 
           Sudah ditangani otomatis oleh parent::__construct() di MY_Controller.
           Jika token mati atau user tidak punya akses 'operasional.elab', 
           program akan berhenti sebelum masuk ke method ini.
        */

        /* ===== FILTER ===== */
        $filters = [
            'upt_id'     => $this->input->get('upt', TRUE), // Samakan key dengan applyScope
            'karantina'  => strtoupper(trim($this->input->get('karantina', TRUE))),
            'start_date' => $this->input->get('start_date', TRUE),
            'end_date'   => $this->input->get('end_date', TRUE),
        ];

        // 2. Keamanan Wilayah Otomatis
        // Memastikan user UPT tidak bisa melihat data UPT lain meskipun mereka mencoba lewat URL
        $this->applyScope($filters);

        /* ===== PAGINATION ===== */
        $page    = max((int)$this->input->get('page'), 1);
        $perPage = (int)$this->input->get('per_page');
        $perPage = $perPage > 0 ? min($perPage, 25) : 20;
        $offset  = ($page - 1) * $perPage;

        /* ===== PROSES DATA ===== */
        // Menggunakan getList dari BaseModelStrict untuk alur yang lebih bersih
        $rows  = $this->Elab_model->getList($filters, $perPage, $offset);
        $total = $this->Elab_model->countAll($filters);

        /* ===== RESPONSE ===== */
        // Menggunakan helper json dari parent (deny) atau manual
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
        /* 1. Ambil Filter */
        $filters = [
            'upt_id'     => $this->input->get('upt', TRUE),
            'karantina'  => strtoupper(trim($this->input->get('karantina', TRUE))),
            'start_date' => $this->input->get('start_date', TRUE),
            'end_date'   => $this->input->get('end_date', TRUE),
        ];

        $this->applyScope($filters);

        /* 2. Ambil Data (Tanpa Limit untuk Export) */
        // Kita ambil ID dulu, lalu ambil detailnya
        $ids = $this->Elab_model->getIds($filters, 5000, 0); // Limit besar untuk export
        $rows = $this->Elab_model->getByIds($ids);

        if (empty($rows)) {
            return $this->jsonRes(404, ['success' => false, 'message' => 'Data eLab tidak ditemukan']);
        }

        /* 3. Mapping Header Excel (Sesuai native) */
        $headers = [
            'No.', 'UPT', 'No. BA Sampling', 'No. Verifikasi', 'Tgl. Verifikasi',
            'Komoditas', 'Target Uji', 'Metode', 'Hasil Uji', 'Kesimpulan', 'Petugas'
        ];

        /* 4. Mapping Data dengan Logika Idem */
    $exportData = [];
    $no = 1;
    $last_doc = ''; // Variabel bantu untuk menyimpan nomor dokumen baris sebelumnya

    foreach ($rows as $r) {
        // Tentukan kolom unik yang jadi acuan (misal: no_verifikasi / nomor penerimaan)
        $current_doc = $r['no_verifikasi']; 

        if ($current_doc === $last_doc) {
            // Jika dokumen sama dengan baris sebelumnya
            $display_no = ''; // Nomor urut dikosongkan (idem)
            $display_doc = ''; // Nomor dokumen dikosongkan jika ingin terlihat bersih
        } else {
            // Jika dokumen berbeda (dokumen baru)
            $display_no = $no++; // Tampilkan nomor dan naikkan urutannya
            $display_doc = $current_doc;
        }

        $exportData[] = [
            $display_no, // Kolom No (bisa kosong jika idem)
            ($r['upt'] ?? '') . ' - ' . ($r['satpel'] ?? ''),
            $r['no_ba_sampling'],
            $display_doc, // Kolom Nomor Verifikasi (kosong jika idem)
            $r['tgl_verifikasi'],
            $r['komoditas'],
            $r['nama_target_uji'],
            $r['nama_metode_uji'],
            $r['hasil_uji'],
            $r['kesimpulan'],
            $r['petugas']
        ];

        // Update last_doc untuk perbandingan di baris berikutnya
        $last_doc = $current_doc;
    }

        /* 5. Build Header Info & Download */
        $title = "E LABORATORIUM " . ($filters['karantina'] ?: 'SEMUA JENIS');
        $reportInfo = $this->buildReportHeader($title, $filters, $rows);

        // Logging aktivitas export
        $this->logActivity("EXPORT EXCEL: ELAB PERIODE {$filters['start_date']}");

        return $this->excel_handler->download("eLab_Export", $headers, $exportData, $reportInfo);
    }
}