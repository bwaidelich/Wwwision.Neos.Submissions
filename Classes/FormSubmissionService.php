<?php

declare(strict_types=1);

namespace Wwwision\Neos\Submissions;

use Closure;
use Psr\Clock\ClockInterface;
use Wwwision\Neos\Submissions\Command\AddSubmission;
use Wwwision\Neos\Submissions\Command\RegenerateLabels;
use Wwwision\Neos\Submissions\Model\Form\Form;
use Wwwision\Neos\Submissions\Model\Form\Forms;
use Wwwision\Neos\Submissions\Model\Preset\Preset;
use Wwwision\Neos\Submissions\Model\Submission\Filter\Pagination;
use Wwwision\Neos\Submissions\Model\Submission\Filter\SubmissionFilter;
use Wwwision\Neos\Submissions\Model\Submission\Filter\SubmissionFilterResult;
use Wwwision\Neos\Submissions\Model\Submission\Submission;
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
            $this->forStoringSubmissions->store($submission->with(
                formLabel: $this->preset->formLabelGenerator->generate($submission->formId, $this->preset),
                label: $this->preset->submissionLabelGenerator->generate($submission->id, $submission->data))
            );
            if ($progressCallback !== null) {
                $progressCallback($submission);
            }
        }
    }

    public function findSubmissions(SubmissionFilter $filter, Pagination|null $pagination = null): SubmissionFilterResult
    {
        return $this->forStoringSubmissions->find($filter, $pagination);
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
