<?php
defined('BASEPATH') OR exit('No direct script access allowed');

use OpenSpout\Writer\CSV\Writer;
use OpenSpout\Writer\CSV\Options;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Cell;

class Csv_handler {

    /**
     * Sanitize a single cell value for RFC 4180 CSV:
     * - Cast to string
     * - Replace newlines with a space so they never break a CSV row
     * - Trim surrounding whitespace
     * OpenSpout then wraps the value in double-quotes and doubles any
     * internal double-quote automatically when the field contains the
     * delimiter, enclosure, or a newline character.
     */
    private function sanitize($value): string
    {
        if ($value === null || $value === false) return '';
        $value = str_replace(["\r\n", "\r", "\n"], ' ', (string) $value);
        return trim($value);
    }

    /**
     * Build an OpenSpout Row where every cell value is sanitized and
     * explicitly typed as a STRING so OpenSpout always wraps it in
     * double-quotes regardless of content.
     */
    private function makeRow(array $values): Row
    {
        $cells = array_map(
            fn($v) => Cell::fromValue($this->sanitize($v)),
            $values
        );
        return new Row($cells);
    }

    public function download($filename, $headers, $data, $reportInfo = []) {
        if (ob_get_level() > 0) ob_end_clean();

        $finalFilename = $filename . '_' . date('Ymd_His') . '.csv';

        // Headers must come before any output
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $finalFilename . '"');
        header('Cache-Control: max-age=0');

        // In OpenSpout v5 Options properties are readonly — pass via constructor.
        // SHOULD_ADD_BOM: true writes the UTF-8 BOM for Excel compatibility.
        $options = new Options(
            FIELD_DELIMITER: ',',
            FIELD_ENCLOSURE: '"',
            SHOULD_ADD_BOM:  true,
        );

        $writer = new Writer($options);
        $writer->openToFile('php://output');

        foreach (['judul', 'upt', 'lingkup', 'periode', 'pencetak', 'source', 'report_id'] as $key) {
            if (!empty($reportInfo[$key])) {
                $writer->addRow($this->makeRow([$reportInfo[$key]]));
            }
        }

        // blank separator row between report info and data
        $writer->addRow($this->makeRow(['']));

        $writer->addRow($this->makeRow($headers));

        foreach ($data as $rowData) {
            $writer->addRow($this->makeRow($rowData));
        }

        $writer->close();
        exit;
    }
}
