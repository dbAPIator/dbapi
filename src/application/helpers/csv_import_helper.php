<?php

defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Parse a CSV document into a JSON:API create payload ({ data: [ ... ] }).
 *
 * Header row required. Column names must be insertable attributes of $resourceName.
 * Empty cells omit the attribute. Empty data rows are skipped.
 *
 * @param  string  $csvText
 * @param  string  $resourceName
 * @param  \dbAPI\API\Datamodel  $dm
 * @return object{data:list<object>}
 * @throws Exception
 */
function dbapi_parse_csv_import(string $csvText, string $resourceName, $dm): object
{
    if (str_starts_with($csvText, "\xEF\xBB\xBF")) {
        $csvText = substr($csvText, 3);
    }

    $lines = preg_split("/\r\n|\n|\r/", $csvText) ?: [];
    $lines = array_values(array_filter($lines, static fn ($l) => trim((string) $l) !== ''));
    if ($lines === []) {
        throw new Exception('Empty CSV', 400);
    }

    $headerLine = (string) array_shift($lines);
    $delimiter = substr_count($headerLine, ';') > substr_count($headerLine, ',') ? ';' : ',';
    $rawHeaders = str_getcsv($headerLine, $delimiter);
    if ($rawHeaders === false || $rawHeaders === [null] || $rawHeaders === []) {
        throw new Exception('CSV header row is required', 400);
    }

    $headers = [];
    foreach ($rawHeaders as $i => $h) {
        $h = trim((string) $h);
        $h = preg_replace('/^\xEF\xBB\xBF/', '', $h) ?? $h;
        if ($h === '') {
            throw new Exception('CSV header has an empty column name', 400);
        }
        if (str_contains($h, '.')) {
            throw new Exception(
                "CSV column '{$h}' is not supported for import (nested/relationship columns are not allowed)",
                400
            );
        }
        if (isset($headers[$h])) {
            throw new Exception("Duplicate CSV column: {$h}", 400);
        }
        $headers[$h] = $i;
    }

    $fields = $dm->get_fields($resourceName);
    if ($fields === null) {
        throw new Exception("Unknown resource: {$resourceName}", 404);
    }

    foreach (array_keys($headers) as $col) {
        if (! isset($fields[$col])) {
            throw new Exception("Unknown CSV column for {$resourceName}: {$col}", 400);
        }
        if (! $dm->field_is_insertable($resourceName, $col)) {
            throw new Exception("CSV column is not insertable for {$resourceName}: {$col}", 400);
        }
    }

    $data = [];
    $lineNo = 1; // header was line 1
    foreach ($lines as $line) {
        $lineNo++;
        $cells = str_getcsv((string) $line, $delimiter);
        if ($cells === false) {
            continue;
        }

        $attrs = new stdClass();
        $any = false;
        foreach ($headers as $col => $idx) {
            $raw = isset($cells[$idx]) ? (string) $cells[$idx] : '';
            if ($raw === '') {
                continue;
            }
            $attrs->{$col} = $raw;
            $any = true;
        }
        if (! $any) {
            continue;
        }

        $entry = new stdClass();
        $entry->type = $resourceName;
        $entry->attributes = $attrs;
        $data[] = $entry;
    }

    if ($data === []) {
        throw new Exception('CSV without data rows', 400);
    }

    $doc = new stdClass();
    $doc->data = $data;

    return $doc;
}

/**
 * True when the request Content-Type indicates a CSV create body.
 */
function dbapi_is_csv_content_type(?string $contentType): bool
{
    if ($contentType === null || $contentType === '') {
        return false;
    }
    $parts = explode(';', $contentType);
    $mime = strtolower(trim((string) ($parts[0] ?? '')));

    return in_array($mime, [
        'text/csv',
        'application/csv',
        'text/plain',
        'multipart/form-data',
    ], true);
}

/**
 * Read raw CSV text from the current HTTP request (body or multipart file).
 *
 * @throws Exception
 */
function dbapi_read_csv_request_body(?string $contentType): string
{
    $parts = explode(';', (string) $contentType);
    $mime = strtolower(trim((string) ($parts[0] ?? '')));

    if ($mime === 'multipart/form-data') {
        $file = null;
        if (isset($_FILES['file']) && is_array($_FILES['file'])) {
            $file = $_FILES['file'];
        } elseif (isset($_FILES['csv']) && is_array($_FILES['csv'])) {
            $file = $_FILES['csv'];
        }
        if ($file === null) {
            throw new Exception('CSV multipart upload requires file field "file" or "csv"', 400);
        }
        $err = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($err !== UPLOAD_ERR_OK) {
            throw new Exception('CSV upload failed', 400);
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || ! is_uploaded_file($tmp)) {
            throw new Exception('Invalid CSV upload', 400);
        }
        $text = (string) file_get_contents($tmp);
        if (trim($text) === '') {
            throw new Exception('Empty CSV', 400);
        }

        return $text;
    }

    $raw = file_get_contents('php://input');
    if (! is_string($raw) || trim($raw) === '') {
        throw new Exception('Empty CSV', 400);
    }

    return $raw;
}
