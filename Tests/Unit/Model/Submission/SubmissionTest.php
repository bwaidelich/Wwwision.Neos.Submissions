<?php

declare(strict_types=1);

namespace Wwwision\Neos\Submissions\Tests\Unit\Model\Submission;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Wwwision\Neos\Submissions\Model\Submission\Submission;

#[CoversClass(Submission::class)]
final class SubmissionTest extends TestCase
{
    private const ID = '0f1b3d3e-7f1b-4a7e-8e6a-3d1c4f2a9b10';

    #[Test]
    public function create_converts_scalar_arguments(): void
    {
        $submission = self::submission(archivedAt: '2026-01-02T03:04:05+00:00');

        self::assertSame(self::ID, $submission->id->value);
        self::assertSame('preset', $submission->presetId->value);
        self::assertSame('form', $submission->formId->value);
        self::assertSame('Form label', $submission->formLabel->value);
        self::assertSame('Label', $submission->label->value);
        self::assertSame(['name' => 'Jane'], $submission->data->toArray());
        self::assertFalse($submission->protected);
        self::assertSame('2026-01-01T00:00:00+00:00', $submission->createdAt->format(DATE_ATOM));
        self::assertSame('2026-01-02T03:04:05+00:00', $submission->archivedAt?->format(DATE_ATOM));
    }

    #[Test]
    public function with_keeps_archivedAt_if_argument_is_omitted(): void
    {
        $submission = self::submission(archivedAt: '2026-01-02T03:04:05+00:00');

        $changed = $submission->with(label: 'New label');

        self::assertSame('New label', $changed->label->value);
        self::assertSame('2026-01-02T03:04:05+00:00', $changed->archivedAt?->format(DATE_ATOM));
    }

    #[Test]
    public function with_clears_archivedAt_if_null_is_passed(): void
    {
        $submission = self::submission(archivedAt: '2026-01-02T03:04:05+00:00');

        self::assertNull($submission->with(archivedAt: null)->archivedAt);
    }

    #[Test]
    public function with_sets_archivedAt(): void
    {
        $submission = self::submission(archivedAt: null);

        $changed = $submission->with(archivedAt: '2026-05-06T07:08:09+00:00');

        self::assertSame('2026-05-06T07:08:09+00:00', $changed->archivedAt?->format(DATE_ATOM));
        self::assertSame('2026-05-06T07:08:09+00:00', $submission->with(archivedAt: new DateTimeImmutable('2026-05-06T07:08:09+00:00'))->archivedAt?->format(DATE_ATOM));
    }

    #[Test]
    public function with_keeps_unchanged_fields(): void
    {
        $submission = self::submission(archivedAt: null);

        $changed = $submission->with(formLabel: 'Other form', data: ['name' => 'John'], protected: true);

        self::assertSame(self::ID, $changed->id->value);
        self::assertSame('preset', $changed->presetId->value);
        self::assertSame('form', $changed->formId->value);
        self::assertSame('Other form', $changed->formLabel->value);
        self::assertSame('Label', $changed->label->value);
        self::assertSame(['name' => 'John'], $changed->data->toArray());
        self::assertTrue($changed->protected);
        self::assertSame($submission->createdAt, $changed->createdAt);
        self::assertNull($changed->archivedAt);
    }

    private static function submission(string|null $archivedAt): Submission
    {
        return Submission::create(
            id: self::ID,
            presetId: 'preset',
            formId: 'form',
            formLabel: 'Form label',
            label: 'Label',
            protected: false,
            data: ['name' => 'Jane'],
            createdAt: '2026-01-01T00:00:00+00:00',
            archivedAt: $archivedAt,
        );
    }
}
