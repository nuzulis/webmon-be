<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Csv_handler {

    private function sanitize($value): string
    {
        if ($value === null || $value === false) return '';
        $value = str_replace(["\r\n", "\r", "\n"], ' ', (string) $value);
        return trim($value);
    }

    private function makeRow(array $values): \OpenSpout\Common\Entity\Row
    {
        $cells = array_map(
            fn($v) => \OpenSpout\Common\Entity\Cell::fromValue($this->sanitize($v)),
            $values
        );
        return new \OpenSpout\Common\Entity\Row($cells);
    }

    private function downloadViaOpenSpout(array $headers, iterable $data, array $reportInfo): void
    {
        $options = new \OpenSpout\Writer\CSV\Options(
            FIELD_DELIMITER: ',',
            FIELD_ENCLOSURE: '"',
            SHOULD_ADD_BOM:  true,
        );

        $writer = new \OpenSpout\Writer\CSV\Writer($options);
        $writer->openToFile('php://output');

        foreach (['judul', 'upt', 'lingkup', 'periode', 'pencetak', 'source', 'report_id'] as $key) {
            if (!empty($reportInfo[$key])) {
                $writer->addRow($this->makeRow([$reportInfo[$key]]));
            }
        }

        $writer->addRow($this->makeRow(['']));
        $writer->addRow($this->makeRow($headers));

        foreach ($data as $row) {
            $writer->addRow($this->makeRow($row));
        }

        $writer->close();
    }

    private function writeRowNative($handle, array $values): void
    {
        $cells = array_map(fn($v) => $this->sanitize($v), $values);
        fputcsv($handle, $cells, ',', '"', '');
    }

    private function downloadViaNative(array $headers, iterable $data, array $reportInfo): void
    {
        $handle = fopen('php://output', 'w');
        fwrite($handle, "\xEF\xBB\xBF");

        foreach (['judul', 'upt', 'lingkup', 'periode', 'pencetak', 'source', 'report_id'] as $key) {
            if (!empty($reportInfo[$key])) {
                $this->writeRowNative($handle, [$reportInfo[$key]]);
            }
        }

        $this->writeRowNative($handle, ['']);
        $this->writeRowNative($handle, $headers);

        foreach ($data as $row) {
            $this->writeRowNative($handle, $row);
        }

        fclose($handle);
    }


    public function download(string $filename, array $headers, iterable $data, array $reportInfo = []): void
    {
        if (ob_get_level() > 0) ob_end_clean();

        $finalFilename = $filename . '_' . date('Ymd_His') . '.csv';

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $finalFilename . '"');
        header('Cache-Control: max-age=0');

        $useOpenSpout = class_exists(\OpenSpout\Writer\CSV\Writer::class)
                     && class_exists(\OpenSpout\Writer\CSV\Options::class);

        if ($useOpenSpout) {
            $this->downloadViaOpenSpout($headers, $data, $reportInfo);
        } else {
            log_message('info', '[Csv_handler] OpenSpout takde, direct ke fputcsv');
            $this->downloadViaNative($headers, $data, $reportInfo);
        }

        exit;
    }
}
