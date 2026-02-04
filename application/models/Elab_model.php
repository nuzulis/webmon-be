<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'models/BaseModelStrict.php';

class Elab_model extends BaseModelStrict
{
    public function getIds($f, $limit, $offset)
    {
        $this->db->select('pr.id, pr.tanggal AS last_tgl', false)
            ->from('`elab-barantin`.penerimaan pr');

        if (!empty($f['karantina'])) {
            $mappingKarantina = ['H' => 'Hewan', 'I' => 'Ikan', 'T' => 'Tumbuhan'];
            $valKarantina = $mappingKarantina[$f['karantina']] ?? null;
            if ($valKarantina) {
                $this->db->where("LOWER(pr.jenisKarantina) =", strtolower($valKarantina));
            }
        }
        if (!empty($f['upt_id']) && $f['upt_id'] !== 'Semua') {
            $uptKode = substr($f['upt_id'], 0, 2);
            $this->db->where('pr.uptKode', $uptKode);
        }

        if (!empty($f['start_date'])) {
            $this->db->where('pr.tanggal >=', $f['start_date'] . ' 00:00:00');
        }
        if (!empty($f['end_date'])) {
            $this->db->where('pr.tanggal <=', $f['end_date'] . ' 23:59:59');
        }
        $this->db->order_by('pr.tanggal', 'DESC');
        if ($limit < 5000) {
            $this->db->limit($limit, $offset);
        }

        $res = $this->db->get()->result_array();
        return array_column($res, 'id');
    }

    

        public function getByIds($ids)
{
    if (empty($ids)) return [];

    $this->db->select("
        pr.id,
        ANY_VALUE(COALESCE(upt.nama, CONCAT('UPT ', pr.uptKode))) AS upt,
        ANY_VALUE(COALESCE(upt.nama_satpel, CONCAT('Satpel ', pr.satpelKode))) AS satpel,
        ANY_VALUE(pr.nomor) AS no_verifikasi,
        ANY_VALUE(pr.tanggal) AS tgl_verifikasi,
        ANY_VALUE(pr.eksDataNomor) AS no_ba_sampling,
        ANY_VALUE(pr.namaPemilik) AS namaPemilik,
        ANY_VALUE(pr.jenisKegiatan) AS jenis_kegiatan,
        ANY_VALUE(pr.jenisKarantina) AS jenis_karantina,
        ANY_VALUE(pr.status) AS status,
        
        GROUP_CONCAT(DISTINCT pd.komoditasUmum SEPARATOR '||') AS komoditas_list,
        GROUP_CONCAT(COALESCE(sh.kodeSampel, '-') SEPARATOR '||') AS kode_sampel_list,
        GROUP_CONCAT(DISTINCT tu.deskripsi SEPARATOR '||') AS target_list,
        GROUP_CONCAT(DISTINCT muji.deskripsi SEPARATOR '||') AS metode_list,
        GROUP_CONCAT(DISTINCT COALESCE(sd.tanggalUji, '-') SEPARATOR '||') AS tgl_uji_list,
        GROUP_CONCAT(CONCAT(COALESCE(sd.hasilText, ''), ' ', COALESCE(sd.hasilTextSatuan, '')) SEPARATOR '||') AS hasil_full_list,
        GROUP_CONCAT(DISTINCT COALESCE(sd.namaAnalis1, '-') SEPARATOR '||') AS analis_list,
        GROUP_CONCAT(COALESCE(sd.nomorSt, '-') SEPARATOR '||') AS st_list,
        
        ANY_VALUE(hul.kesimpulan) AS kesimpulan,
        ANY_VALUE(hul.namaTtd) AS petugas_rilis
    ", false);

    $this->db->from('`elab-barantin`.penerimaan pr')
        ->join('`elab-barantin`.penerimaan_detil pd', 'pd.penerimaanId = pr.id', 'left')
        ->join('`elab-barantin`.sampel_header sh', 'sh.penerimaanDetilId = pd.id', 'left')
        ->join('`elab-barantin`.sampel_detil sd', 'sd.sampelHeaderId = sh.id', 'left')
        ->join('`elab-barantin`.target_uji tu', 'tu.id = sd.targetUjiId', 'left')
        ->join('`elab-barantin`.metode_uji muji', 'muji.id = sd.metodeUjiId', 'left')
        ->join('`elab-barantin`.hasil_uji_lab hul', 'hul.penerimaanId = pr.id', 'left')
        ->join('barantin.master_upt upt', 'pr.satpelKode = upt.id', 'left');

    $this->db->where_in('pr.id', $ids);
    $this->db->group_by('pr.id');
    $this->db->order_by('pr.tanggal', 'DESC');

    return $this->db->get()->result_array();
}

    public function countAll($f)
    {
        $this->db->from('`elab-barantin`.penerimaan pr');

        if (!empty($f['karantina'])) {
            $mappingKarantina = ['H' => 'Hewan', 'I' => 'Ikan', 'T' => 'Tumbuhan'];
            $valKarantina = $mappingKarantina[$f['karantina']] ?? null;
            if ($valKarantina) {
                $this->db->where("LOWER(pr.jenisKarantina) =", strtolower($valKarantina));
            }
        }

        if (!empty($f['upt_id']) && $f['upt_id'] !== 'Semua') {
            $uptKode = substr($f['upt_id'], 0, 2);
            $this->db->where('pr.uptKode', $uptKode);
        }

        if (!empty($f['start_date'])) {
            $this->db->where('pr.tanggal >=', $f['start_date'] . ' 00:00:00');
        }
        if (!empty($f['end_date'])) {
            $this->db->where('pr.tanggal <=', $f['end_date'] . ' 23:59:59');
        }

        return $this->db->count_all_results();
    }
    
}