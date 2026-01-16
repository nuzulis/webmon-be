<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
table { border-collapse: collapse; }
td, th { border:1px solid #000; padding:4px; }
</style>
</head>
<body>

<center>
<h4>LAPORAN OPERASIONAL <?= strtoupper($filters['karantina']) ?></h4>
<h4>PERMOHONAN <?= strtoupper($filters['permohonan']) ?></h4>
<h4>PERIODE <?= $filters['start_date'] ?> s/d <?= $filters['end_date'] ?></h4>
<h4>Dicetak <?= date('Y-m-d H:i:s') ?> oleh <?= $user['nama'] ?></h4>
</center>

<table>
<tr>
    <th>No</th>
    <th>Pengajuan</th>
    <th>No Aju</th>
    <th>No Dok</th>
    <th>UPT</th>
    <th>Komoditas</th>
    <th>HS</th>
    <th>Volume</th>
    <th>Satuan</th>
</tr>

<?php $no=1; foreach ($rows as $r): ?>
<tr>
    <td><?= $no++ ?></td>
    <td><?= $r->tssm_id ? 'SSM':'PTK' ?></td>
    <td><?= $r->no_aju ?></td>
    <td><?= $r->no_dok_permohonan ?></td>
    <td><?= $r->upt ?></td>
    <td><?= $r->komoditas ?></td>
    <td><?= $r->hs ?></td>
    <td><?= number_format($r->p1,3,',','.') ?></td>
    <td><?= $r->satuan ?></td>
</tr>
<?php endforeach; ?>

</table>
</body>
</html>
