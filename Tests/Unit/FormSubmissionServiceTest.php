<?php

declare(strict_types=1);

namespace Wwwision\Neos\Submissions\Tests\Unit;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Wwwision\Neos\Submissions\Command\ArchiveSubmission;
use Wwwision\Neos\Submissions\Command\ArchiveUnprotectedSubmissions;
use Wwwision\Neos\Submissions\Command\ProtectSubmission;
use Wwwision\Neos\Submissions\Command\UnprotectSubmission;
use Wwwision\Neos\Submissions\FormSubmissionService;
use Wwwision\Neos\Submissions\LabelGenerator\FormLabelGenerator\FormLabelGenerator;
use Wwwision\Neos\Submissions\LabelGenerator\SubmissionLabelGenerator\SubmissionLabelGenerator;
use Wwwision\Neos\Submissions\Model\Form\FormId;
use Wwwision\Neos\Submissions\Model\Form\FormIds;
use Wwwision\Neos\Submissions\Model\Form\FormLabel;
use Wwwision\Neos\Submissions\Model\Preset\Preset;
use Wwwision\Neos\Submissions\Model\Preset\PresetId;
use Wwwision\Neos\Submissions\Model\Preset\PresetTitle;
use Wwwision\Neos\Submissions\Model\Submission\Exception\SubmissionIsProtected;
use Wwwision\Neos\Submissions\Model\Submission\Filter\Pagination;
use Wwwision\Neos\Submissions\Model\Submission\Filter\SubmissionFilter;
use Wwwision\Neos\Submissions\Model\Submission\Filter\SubmissionFilterResult;
use Wwwision\Neos\Submissions\Model\Submission\Submission;
use Wwwision\Neos\Submissions\Model\Submission\SubmissionData;
use Wwwision\Neos\Submissions\Model\Submission\SubmissionId;
use Wwwision\Neos\Submissions\Model\Submission\SubmissionLabel;
use Wwwision\Neos\Submissions\Model\Submission\Submissions;
use Wwwision\Neos\Submissions\Ports\ForStoringSubmissions;

#[CoversClass(FormSubmissionService::class)]
final class FormSubmissionServiceTest extends TestCase
{
    public const NOW = '2026-09-23T10:00:00+00:00';

    private const ID_1 = '00000000-0000-4000-8000-000000000001';
    private const ID_2 = '00000000-0000-4000-8000-000000000002';
    private const ID_3 = '00000000-0000-4000-8000-000000000003';

    /**
     * @var array<string, Submission>
     */
    private array $storedSubmissions = [];

    private FormSubmissionService $service;

    protected function setUp(): void
    {
        $this->storedSubmissions = [];
        $storage = new class ($this->storedSubmissions) implements ForStoringSubmissions {
            /**
             * @param array<string, Submission> $submissions
             */
            public function __construct(private array &$submissions) {}

            public function setup(): void {}

            public function store(Submission $submission): void
            {
                $this->submissions[$submission->id->value] = $submission;
            }

            public function remove(SubmissionId $submissionId): void
            {
                unset($this->submissions[$submissionId->value]);
            }

            public function findOne(SubmissionId $submissionId): Submission|null
            {
                return $this->submissions[$submissionId->value] ?? null;
            }

            public function find(SubmissionFilter $filter, Pagination|null $pagination = null): SubmissionFilterResult
            {
                $matches = array_values(array_filter(
                    $this->submissions,
                    static fn(Submission $submission) => $submission->archivedAt === null && ($filter->formId === null || $submission->formId->value === $filter->formId->value),
                ));
                return SubmissionFilterResult::create(Submissions::fromIterable($matches), count($matches));
            }

            public function findFormIds(): FormIds
            {
                return FormIds::fromArray([]);
            }
        };
        $clock = new class implements ClockInterface {
            public function now(): DateTimeImmutable
            {
                return new DateTimeImmutable(FormSubmissionServiceTest::NOW);
            }
        };
        $preset = new Preset(
            PresetId::fromString('preset'),
            PresetTitle::fromString('Preset'),
            new class implements SubmissionLabelGenerator {
                public function generate(SubmissionId $submissionId, SubmissionData $data): SubmissionLabel
                {
                    return SubmissionLabel::fromString($submissionId->value);
                }
            },
            new class implements FormLabelGenerator {
                public function generate(FormId $formId, Preset $preset): FormLabel
                {
                    return FormLabel::fromString($formId->value);
                }
            },
            [],
        );
        $this->service = new FormSubmissionService($preset, $storage, $clock);
    }

    #[Test]
    public function archiveSubmission_sets_archivedAt_to_the_current_time(): void
    {
        $this->givenSubmission(self::ID_1);

        $this->service->handleArchiveSubmission(ArchiveSubmission::create(self::ID_1));

        self::assertSame(self::NOW, $this->storedSubmissions[self::ID_1]->archivedAt?->format(DATE_ATOM));
    }

    #[Test]
    public function archiveSubmission_fails_for_protected_submissions(): void
    {
        $this->givenSubmission(self::ID_1, protected: true);

        try {
            $this->service->handleArchiveSubmission(ArchiveSubmission::create(self::ID_1));
            self::fail('Expected exception was not thrown');
        } catch (SubmissionIsProtected) {
        }
        self::assertNull($this->storedSubmissions[self::ID_1]->archivedAt);
    }

    #[Test]
    public function archiveSubmission_fails_for_already_archived_submissions(): void
    {
        $this->givenSubmission(self::ID_1, archivedAt: '2026-01-01T00:00:00+00:00');

        $this->expectException(InvalidArgumentException::class);
        $this->service->handleArchiveSubmission(ArchiveSubmission::create(self::ID_1));
    }

    #[Test]
    public function getSubmission_fails_for_archived_submissions(): void
    {
        $this->givenSubmission(self::ID_1, archivedAt: '2026-01-01T00:00:00+00:00');

        $this->expectException(InvalidArgumentException::class);
        $this->service->getSubmission(SubmissionId::fromString(self::ID_1));
    }

    #[Test]
    public function archiveUnprotectedSubmissions_skips_protected_submissions_and_submissions_not_matching_the_filter(): void
    {
        $this->givenSubmission(self::ID_1);
        $this->givenSubmission(self::ID_2, protected: true);
        $this->givenSubmission(self::ID_3, formId: 'other-form');

        $numberOfArchivedSubmissions = $this->service->handleArchiveUnprotectedSubmissions(ArchiveUnprotectedSubmissions::create(SubmissionFilter::create(formId: 'form')));

        self::assertSame(1, $numberOfArchivedSubmissions);
        self::assertSame(self::NOW, $this->storedSubmissions[self::ID_1]->archivedAt?->format(DATE_ATOM));
        self::assertNull($this->storedSubmissions[self::ID_2]->archivedAt);
        self::assertNull($this->storedSubmissions[self::ID_3]->archivedAt);
    }

    #[Test]
    public function protectSubmission_and_unprotectSubmission_toggle_the_protected_flag(): void
    {
        $this->givenSubmission(self::ID_1);

        $this->service->handleProtectSubmission(ProtectSubmission::create(self::ID_1));
        self::assertTrue($this->storedSubmissions[self::ID_1]->protected);

        $this->service->handleUnprotectSubmission(UnprotectSubmission::create(self::ID_1));
        self::assertFalse($this->storedSubmissions[self::ID_1]->protected);
    }

    private function givenSubmission(string $id, bool $protected = false, string|null $archivedAt = null, string $formId = 'form'): void
    {
        $this->storedSubmissions[$id] = Submission::create(
            id: $id,
            presetId: 'preset',
            formId: $formId,
            formLabel: 'Form label',
            label: 'Label',
            protected: $protected,
            data: [],
            createdAt: '2026-01-01T00:00:00+00:00',
            archivedAt: $archivedAt,
        );
    }
}
