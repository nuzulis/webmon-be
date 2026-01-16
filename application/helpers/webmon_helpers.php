<?php
defined('BASEPATH') OR exit('No direct script access allowed');

function build_alasan(array $row, array $map, string $lainKey = 'alasanlain'): string
{
    $out = [];

    foreach ($map as $field => $label) {
        if (!empty($row[$field]) && $row[$field] == '1') {
            $out[] = $label;
        }
    }

    if (!empty($row[$lainKey]) && $row[$lainKey] !== '0') {
        $out[] = 'Lain-lain: ' . strip_tags($row[$lainKey]);
    }

    return $out ? implode('<br>', $out) : '-';
}
