<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class ElabDetail_model extends CI_Model
{
    /**
     * ============================
     * 1. HEADER / MASTER PENERIMAAN
     * ============================
     */
    public function getHeader($penerimaanId)
    {
        return $this->db
            ->select('
                pr.id,
                pr.nomor,
                pr.tanggal,
                pr.jenisKarantina,
                pr.jenisKegiatan,
                pr.jenisKegiatanLain,
                pr.namaPemilik,
                pr.nikPemilik,
                pr.tujuan,
                pr.verifikasi,
                pr.status,
                pr.catatan,
                pr.eksDataNomor,
                pr.eksDataTanggal,
                pr.createdAt,
                upt.nama AS upt,
                upt.nama_satpel AS satpel
            ')
            ->from('`elab-barantin`.penerimaan pr')
            ->join('barantin.master_upt upt', 'pr.satpelKode = upt.id', 'left')
            ->where('pr.id', $penerimaanId)
            ->get()
            ->row_array();
    }

    /**
     * ============================
     * 2. KOMODITAS / PENERIMAAN DETIL
     * ============================
     */
    public function getKomoditas($penerimaanId)
    {
        return $this->db
            ->select('
                id,
                komoditasUmum,
                komoditasLatin,
                identitasContoh,
                kodeContoh,
                jumlahContoh,
                kondisiContoh,
                keteranganContoh,
                volNetto,
                satuanNetto,
                volBruto,
                satuanBruto,
                createdAt
            ')
            ->from('`elab-barantin`.penerimaan_detil')
            ->where('penerimaanId', $penerimaanId)
            ->order_by('createdAt', 'ASC')
            ->get()
            ->result_array();
    }

    /**
     * ============================
     * 3. SAMPEL + TARGET + METODE + ANALIS
     * ============================
     */
    public function getSampel($penerimaanId)
    {
        return $this->db
            ->select('
                sh.id AS sampelHeaderId,
                sh.kodeSampel,
                sd.id AS sampelDetilId,
                tu.deskripsi AS targetUji,
                mu.deskripsi AS metodeUji,
                sd.tanggalUji,
                sd.hasilText,
                sd.hasilTextSatuan,
                sd.namaAnalis1,
                sd.namaAnalis2,
                sd.namaAnalis3,
                sd.nomorSt
            ')
            ->from('`elab-barantin`.penerimaan_detil pd')
            ->join('`elab-barantin`.sampel_header sh', 'sh.penerimaanDetilId = pd.id', 'left')
            ->join('`elab-barantin`.sampel_detil sd', 'sd.sampelHeaderId = sh.id', 'left')
            ->join('`elab-barantin`.target_uji tu', 'tu.id = sd.targetUjiId', 'left')
            ->join('`elab-barantin`.metode_uji mu', 'mu.id = sd.metodeUjiId', 'left')
            ->where('pd.penerimaanId', $penerimaanId)
            ->order_by('sd.tanggalUji', 'ASC')
            ->get()
            ->result_array();
    }

    /**
     * ============================
     * 4. HASIL UJI LAB (KESIMPULAN)
     * ============================
     */
    public function getHasilUji($penerimaanId)
    {
        return $this->db
            ->select('
                kesimpulan,
                namaTtd AS petugasRilis,
                createdAt
            ')
            ->from('`elab-barantin`.hasil_uji_lab')
            ->where('penerimaanId', $penerimaanId)
            ->get()
            ->row_array();
    }

    /**
     * ============================
     * 5. TIMELINE / LOG AKTIVITAS
     * ============================
     */
    public function getTimeline($penerimaanId)
    {
        return $this->db
            ->select('
                id,
                action,
                kegiatan,
                nomor,
                createdAt
            ')
            ->from('`elab-barantin`.log_history')
            ->where('penerimaanId', $penerimaanId)
            ->order_by('createdAt', 'DESC')
            ->get()
            ->result_array();
    }

    /**
     * ============================
     * 6. AGGREGATOR (1 CALL API)
     * ============================
     */
    public function getFullDetail($penerimaanId)
    {
        return [
            'header'     => $this->getHeader($penerimaanId),
            'komoditas'  => $this->getKomoditas($penerimaanId),
            'sampel'     => $this->getSampel($penerimaanId),
            'hasil_uji'  => $this->getHasilUji($penerimaanId),
            'timeline'   => $this->getTimeline($penerimaanId),
        ];
    }
}
