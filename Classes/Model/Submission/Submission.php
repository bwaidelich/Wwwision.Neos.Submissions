<?php

declare(strict_types=1);

namespace Wwwision\Neos\Submissions\Model\Submission;

use DateTimeImmutable;
use Webmozart\Assert\Assert;
use Wwwision\Neos\Submissions\Model\Form\FormId;
use Wwwision\Neos\Submissions\Model\Form\FormLabel;
use Wwwision\Neos\Submissions\Model\Preset\PresetId;

final readonly class Submission
{
    private function __construct(
        public SubmissionId $id,
        public PresetId $presetId,
        public FormId $formId,
        public FormLabel $formLabel,
        public SubmissionLabel $label,
        public SubmissionData $data,
        public bool $protected,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable|null $archivedAt,
    ) {}

    /**
     * @param SubmissionData|array<string, mixed> $data
     */
    public static function create(
        SubmissionId|string $id,
        PresetId|string $presetId,
        FormId|string $formId,
        FormLabel|string $formLabel,
        SubmissionLabel|string $label,
        bool $protected,
        SubmissionData|array $data,
        DateTimeImmutable|string $createdAt,
        DateTimeImmutable|string|null $archivedAt,
    ): self {
        if (is_string($id)) {
            $id = SubmissionId::fromString($id);
        }
        if (is_string($presetId)) {
            $presetId = PresetId::fromString($presetId);
        }
        if (is_string($formId)) {
            $formId = FormId::fromString($formId);
        }
        if (is_string($formLabel)) {
            $formLabel = FormLabel::fromString($formLabel);
        }
        if (is_string($label)) {
            $label = SubmissionLabel::fromString($label);
        }
        if (is_array($data)) {
            $data = SubmissionData::fromArray($data);
        }
        if (is_string($createdAt)) {
            $createdAtCandidate = DateTimeImmutable::createFromFormat(DATE_ATOM, $createdAt);
            Assert::isInstanceOf($createdAtCandidate, DateTimeImmutable::class);
            $createdAt = $createdAtCandidate;
        }
        if (is_string($archivedAt)) {
            $archivedAtCandidate = DateTimeImmutable::createFromFormat(DATE_ATOM, $archivedAt);
            Assert::isInstanceOf($archivedAtCandidate, DateTimeImmutable::class);
            $archivedAt = $archivedAtCandidate;
        }
        return new self($id, $presetId, $formId, $formLabel, $label, $data, $protected, $createdAt, $archivedAt);
    }

    /**
     * Returns a copy of this submission with the given fields replaced.
     * Arguments that are omitted (or null) keep their current value; to clear `archivedAt`, pass `null` explicitly.
     *
     * @param SubmissionData|array<string, mixed>|null $data
     */
    public function with(
        FormLabel|string|null $formLabel = null,
        SubmissionLabel|string|null $label = null,
        SubmissionData|array|null $data = null,
        bool|null $protected = null,
        DateTimeImmutable|string|false|null $archivedAt = false,
    ): self {
        if (is_string($formLabel)) {
            $formLabel = FormLabel::fromString($formLabel);
        }
        if (is_string($label)) {
            $label = SubmissionLabel::fromString($label);
        }
        if (is_array($data)) {
            $data = SubmissionData::fromArray($data);
        }
        if (is_string($archivedAt)) {
            $archivedAtCandidate = DateTimeImmutable::createFromFormat(DATE_ATOM, $archivedAt);
            Assert::isInstanceOf($archivedAtCandidate, DateTimeImmutable::class);
            $archivedAt = $archivedAtCandidate;
        }
        return new self(
            $this->id,
            $this->presetId,
            $this->formId,
            $formLabel ?? $this->formLabel,
            $label ?? $this->label,
            $data ?? $this->data,
            $protected ?? $this->protected,
            $this->createdAt,
            $archivedAt === false ? $this->archivedAt : $archivedAt,
        );
    }
}
