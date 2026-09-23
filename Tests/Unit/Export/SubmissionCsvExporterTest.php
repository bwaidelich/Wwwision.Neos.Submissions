<?php

declare(strict_types=1);

namespace Wwwision\Neos\Submissions\Tests\Unit\Export;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Wwwision\Neos\Submissions\Export\SubmissionCsvExporter;
use Wwwision\Neos\Submissions\Model\Submission\Submission;
use Wwwision\Neos\Submissions\Model\Submission\Submissions;

#[CoversClass(SubmissionCsvExporter::class)]
final class SubmissionCsvExporterTest extends TestCase
{
    private const HEADER = 'id,formId,formLabel,presetId,label,createdAt,archivedAt,protected';

    #[Test]
    public function export_of_empty_set_contains_only_the_header(): void
    {
        self::assertSame([self::HEADER], self::export());
    }

    #[Test]
    public function export_flattens_nested_data_and_fills_missing_columns(): void
    {
        $lines = self::export(
            self::submission('0f1b3d3e-7f1b-4a7e-8e6a-3d1c4f2a9b10', ['name' => 'Jane', 'address' => ['city' => 'Berlin']]),
            self::submission('1f1b3d3e-7f1b-4a7e-8e6a-3d1c4f2a9b11', ['name' => 'John', 'newsletter' => true]),
        );

        self::assertSame([
            self::HEADER . ',name,address.city,newsletter',
            '0f1b3d3e-7f1b-4a7e-8e6a-3d1c4f2a9b10,form,"Form label",preset,Label,2026-01-01T00:00:00+00:00,,false,Jane,Berlin,',
            '1f1b3d3e-7f1b-4a7e-8e6a-3d1c4f2a9b11,form,"Form label",preset,Label,2026-01-01T00:00:00+00:00,,false,John,,true',
        ], $lines);
    }

    #[Test]
    public function export_does_not_let_form_fields_overwrite_fixed_columns(): void
    {
        $lines = self::export(
            self::submission('0f1b3d3e-7f1b-4a7e-8e6a-3d1c4f2a9b10', ['id' => 'spoofed', 'formLabel' => 'spoofed', 'label' => 'spoofed label']),
        );

        self::assertSame([
            self::HEADER . ',data.id,data.formLabel,data.label',
            '0f1b3d3e-7f1b-4a7e-8e6a-3d1c4f2a9b10,form,"Form label",preset,Label,2026-01-01T00:00:00+00:00,,false,spoofed,spoofed,"spoofed label"',
        ], $lines);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function formulaCells(): iterable
    {
        yield 'formula' => ['=HYPERLINK("http://evil";"click")', '\'=HYPERLINK("http://evil";"click")'];
        yield 'dde' => ['=cmd|\' /C calc\'!A0', '\'=cmd|\' /C calc\'!A0'];
        yield 'plus' => ['+1+2', '\'+1+2'];
        yield 'minus' => ['-foo', '\'-foo'];
        yield 'at' => ['@SUM(A1)', '\'@SUM(A1)'];
        yield 'tab' => ["\tfoo", "'\tfoo"];
        yield 'negative number' => ['-12.5', '-12.5'];
        yield 'plain text' => ['Hello World', 'Hello World'];
        yield 'empty' => ['', ''];
    }

    #[Test]
    #[DataProvider('formulaCells')]
    public function export_neutralizes_cells_that_look_like_formulas(string $value, string $expectedCell): void
    {
        $lines = self::export(self::submission('0f1b3d3e-7f1b-4a7e-8e6a-3d1c4f2a9b10', ['field' => $value]));

        $cells = str_getcsv($lines[1], ',', '"', '');
        self::assertSame($expectedCell, $cells[8]);
    }

    #[Test]
    public function export_quotes_cells_with_delimiters_quotes_and_backslashes(): void
    {
        $lines = self::export(self::submission('0f1b3d3e-7f1b-4a7e-8e6a-3d1c4f2a9b10', ['field' => 'a "quoted", value \\" with backslash']));

        self::assertSame(
            '0f1b3d3e-7f1b-4a7e-8e6a-3d1c4f2a9b10,form,"Form label",preset,Label,2026-01-01T00:00:00+00:00,,false,"a ""quoted"", value \\"" with backslash"',
            $lines[1],
        );
    }

    /**
     * @return list<string>
     */
    private static function export(Submission ...$submissions): array
    {
        $stream = (new SubmissionCsvExporter())->export(Submissions::fromIterable($submissions));
        $csv = stream_get_contents($stream);
        fclose($stream);
        self::assertIsString($csv);
        return explode("\n", rtrim($csv, "\n"));
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function submission(string $id, array $data): Submission
    {
        return Submission::create(
            id: $id,
            presetId: 'preset',
            formId: 'form',
            formLabel: 'Form label',
            label: 'Label',
            protected: false,
            data: $data,
            createdAt: '2026-01-01T00:00:00+00:00',
            archivedAt: null,
        );
    }
}
