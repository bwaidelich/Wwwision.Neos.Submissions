<?php

declare(strict_types=1);

namespace Wwwision\Neos\Submissions;

use Closure;
use InvalidArgumentException;
use Psr\Clock\ClockInterface;
use Wwwision\Neos\Submissions\Command\AddSubmission;
use Wwwision\Neos\Submissions\Command\ArchiveSubmission;
use Wwwision\Neos\Submissions\Command\ArchiveUnprotectedSubmissions;
use Wwwision\Neos\Submissions\Command\ProtectSubmission;
use Wwwision\Neos\Submissions\Command\RegenerateLabels;
use Wwwision\Neos\Submissions\Command\UnprotectSubmission;
use Wwwision\Neos\Submissions\Model\Form\Form;
use Wwwision\Neos\Submissions\Model\Form\Forms;
use Wwwision\Neos\Submissions\Model\Preset\Preset;
use Wwwision\Neos\Submissions\Model\Submission\Exception\SubmissionIsProtected;
use Wwwision\Neos\Submissions\Model\Submission\Filter\Pagination;
use Wwwision\Neos\Submissions\Model\Submission\Filter\SubmissionFilter;
use Wwwision\Neos\Submissions\Model\Submission\Filter\SubmissionFilterResult;
use Wwwision\Neos\Submissions\Model\Submission\Submission;
use Wwwision\Neos\Submissions\Model\Submission\SubmissionId;
use Wwwision\Neos\Submissions\Ports\ForStoringSubmissions;

final class FormSubmissionService
{
    public function __construct(
        private Preset $preset,
        private ForStoringSubmissions $forStoringSubmissions,
        private ClockInterface $clock,
    ) {}

    public function handleAddSubmission(AddSubmission $command): void
    {
        $submissionLabel = $this->preset->submissionLabelGenerator->generate($command->submissionId, $command->data);
        $formLabel = $this->preset->formLabelGenerator->generate($command->formId, $this->preset);
        $submission = Submission::create(
            id: $command->submissionId,
            presetId: $this->preset->id,
            formId: $command->formId,
            formLabel: $formLabel,
            label: $submissionLabel,
            protected: false,
            data: $command->data,
            createdAt: $this->clock->now(),
            archivedAt: null,
        );
        $this->forStoringSubmissions->store($submission);
    }

    /**
     * @param Closure(Submission): void|null $progressCallback
     */
    public function handleRegenerateLabels(RegenerateLabels $command, Closure|null $progressCallback = null): void
    {
        foreach ($this->forStoringSubmissions->find(SubmissionFilter::create(formId: $command->formId)) as $submission) {
            $this->forStoringSubmissions->store(
                $submission->with(
                    formLabel: $this->preset->formLabelGenerator->generate($submission->formId, $this->preset),
                    label: $this->preset->submissionLabelGenerator->generate($submission->id, $submission->data),
                ),
            );
            if ($progressCallback !== null) {
                $progressCallback($submission);
            }
        }
    }

    /**
     * @throws SubmissionIsProtected if the submission is protected
     */
    public function handleArchiveSubmission(ArchiveSubmission $command): void
    {
        $submission = $this->getSubmission($command->submissionId);
        if ($submission->protected) {
            throw SubmissionIsProtected::cannotBeArchived($submission->id);
        }
        $this->forStoringSubmissions->store($submission->with(archivedAt: $this->clock->now()));
    }

    /**
     * Archives all unprotected submissions matching the given filter
     *
     * @return int the number of archived submissions
     */
    public function handleArchiveUnprotectedSubmissions(ArchiveUnprotectedSubmissions $command): int
    {
        $now = $this->clock->now();
        $numberOfArchivedSubmissions = 0;
        foreach ($this->forStoringSubmissions->find($command->filter) as $submission) {
            if ($submission->protected) {
                continue;
            }
            $this->forStoringSubmissions->store($submission->with(archivedAt: $now));
            $numberOfArchivedSubmissions++;
        }
        return $numberOfArchivedSubmissions;
    }

    public function handleProtectSubmission(ProtectSubmission $command): void
    {
        $submission = $this->getSubmission($command->submissionId);
        if ($submission->protected) {
            return;
        }
        $this->forStoringSubmissions->store($submission->with(protected: true));
    }

    public function handleUnprotectSubmission(UnprotectSubmission $command): void
    {
        $submission = $this->getSubmission($command->submissionId);
        if (!$submission->protected) {
            return;
        }
        $this->forStoringSubmissions->store($submission->with(protected: false));
    }

    public function findSubmissions(SubmissionFilter $filter, Pagination|null $pagination = null): SubmissionFilterResult
    {
        return $this->forStoringSubmissions->find($filter, $pagination);
    }

    /**
     * @throws InvalidArgumentException if the submission does not exist or has been archived
     */
    public function getSubmission(SubmissionId $id): Submission
    {
        $submission = $this->forStoringSubmissions->findOne($id);
        if ($submission === null || $submission->archivedAt !== null) {
            throw new InvalidArgumentException(sprintf('Submission "%s" not found for preset "%s"', $id->value, $this->preset->id->value), 1789994644);
        }
        return $submission;
    }

    public function findForms(): Forms
    {
        $forms = [];
        foreach ($this->forStoringSubmissions->findFormIds() as $formId) {
            $forms[] = new Form($formId, $this->preset->formLabelGenerator->generate($formId, $this->preset));
        }
        return Forms::fromArray($forms);
    }
}
