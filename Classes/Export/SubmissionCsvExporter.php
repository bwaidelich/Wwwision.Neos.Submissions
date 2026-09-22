<?php

declare(strict_types=1);

namespace Wwwision\Neos\Submissions\Export;

use JsonException;
use RuntimeException;
use Wwwision\Neos\Submissions\Model\Submission\Submission;
use Wwwision\Neos\Submissions\Model\Submission\Submissions;

/**
 * Renders a set of {@see Submission}s into a CSV file, streaming so that it is safe to use for large result sets.
 *
 * The submissions are read from the database exactly once. Since the set of dynamic (form-field) columns can only
 * be known once every submission has been seen, each flattened row is spooled to a local `php://temp` resource
 * while the column set is accumulated; a second, purely local pass over that spool then produces the final CSV.
 */
final class SubmissionCsvExporter
{
    private const DELIMITER = ',';
    private const ENCLOSURE = '"';

    /**
     * @var list<string>
     */
    private const FIXED_COLUMNS = ['id', 'formId', 'presetId', 'label', 'createdAt', 'archivedAt', 'protected'];

    /**
     * Prefix for form field columns whose name collides with one of the {@see self::FIXED_COLUMNS}
     */
    private const DATA_COLUMN_PREFIX = 'data.';

    /**
     * @return resource A read-only, rewound stream containing the rendered CSV file.
     */
    public function export(Submissions $submissions)
    {
        $spool = self::openTemporaryStream();
        $columns = array_fill_keys(self::FIXED_COLUMNS, true);
        foreach ($submissions as $submission) {
            $row = self::flattenSubmission($submission);
            foreach ($row as $column => $_) {
                $columns[$column] = true;
            }
            self::writeSpoolLine($spool, $row);
        }
        $header = array_keys($columns);

        $output = self::openTemporaryStream();
        self::writeCsvLine($output, $header);
        rewind($spool);
        while (($line = fgets($spool)) !== false) {
            $row = self::readSpoolLine($line);
            $cells = [];
            foreach ($header as $column) {
                $cells[] = self::neutralizeFormula($row[$column] ?? '');
            }
            self::writeCsvLine($output, $cells);
        }
        fclose($spool);
        rewind($output);
        return $output;
    }

    /**
     * @return resource
     */
    private static function openTemporaryStream()
    {
        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            throw new RuntimeException('Failed to open temporary stream for CSV export', 1798000003);
        }
        return $stream;
    }

    /**
     * @param resource $stream
     * @param list<string> $cells
     */
    private static function writeCsvLine($stream, array $cells): void
    {
        // an empty escape character disables PHP's proprietary backslash handling (RFC 4180 compliant output,
        // and the only non-deprecated option as of PHP 8.4)
        fputcsv($stream, $cells, self::DELIMITER, self::ENCLOSURE, '');
    }

    /**
     * Cells that spreadsheet applications would interpret as formulas (e.g. "=HYPERLINK(...)" or "=cmd|...")
     * are prefixed with a single quote so that they are treated as plain text (CSV injection mitigation).
     * Plain numbers (including negative ones) are left untouched.
     */
    private static function neutralizeFormula(string $cell): string
    {
        if ($cell === '' || is_numeric($cell)) {
            return $cell;
        }
        if (in_array($cell[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'" . $cell;
        }
        return $cell;
    }

    /**
     * @return array<string, string> flattened, already-stringified cell values, keyed by column name
     */
    private static function flattenSubmission(Submission $submission): array
    {
        $row = [
            'id' => $submission->id->value,
            'formId' => $submission->formId->value,
            'presetId' => $submission->presetId->value,
            'label' => $submission->label->value,
            'createdAt' => $submission->createdAt->format(DATE_ATOM),
            'archivedAt' => $submission->archivedAt?->format(DATE_ATOM) ?? '',
            'protected' => self::stringifyScalar($submission->protected),
        ];
        foreach (self::flattenData($submission->data->toArray()) as $key => $value) {
            // form fields must never overwrite the fixed submission columns
            if (in_array($key, self::FIXED_COLUMNS, true)) {
                $key = self::DATA_COLUMN_PREFIX . $key;
            }
            $row[$key] = $value;
        }
        return $row;
    }

    /**
     * @param array<int|string, mixed> $data
     * @return array<string, string>
     */
    private static function flattenData(array $data, string $prefix = ''): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            $flatKey = $prefix === '' ? (string) $key : $prefix . '.' . $key;
            if (is_array($value)) {
                $result += self::flattenData($value, $flatKey);
            } else {
                $result[$flatKey] = self::stringifyScalar($value);
            }
        }
        return $result;
    }

    private static function stringifyScalar(mixed $value): string
    {
        return match (true) {
            $value === null => '',
            is_bool($value) => $value ? 'true' : 'false',
            is_scalar($value), $value instanceof \Stringable => (string) $value,
            default => get_debug_type($value),
        };
    }

    /**
     * @param resource $spool
     * @param array<string, string> $row
     */
    private static function writeSpoolLine($spool, array $row): void
    {
        try {
            $encoded = json_encode($row, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException(sprintf('Failed to encode CSV export row: %s', $e->getMessage()), 1798000001, $e);
        }
        fwrite($spool, $encoded . "\n");
    }

    /**
     * @return array<string, string>
     */
    private static function readSpoolLine(string $line): array
    {
        try {
            /** @var array<string, string> $row */
            $row = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException(sprintf('Failed to decode spooled CSV export row: %s', $e->getMessage()), 1798000002, $e);
        }
        return $row;
    }
}
