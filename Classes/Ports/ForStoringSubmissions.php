<?php

declare(strict_types=1);

namespace Wwwision\Neos\Submissions\Ports;

use Wwwision\Neos\Submissions\Model\Form\FormIds;
use Wwwision\Neos\Submissions\Model\Submission\Filter\Pagination;
use Wwwision\Neos\Submissions\Model\Submission\Filter\SubmissionFilter;
use Wwwision\Neos\Submissions\Model\Submission\Filter\SubmissionFilterResult;
use Wwwision\Neos\Submissions\Model\Submission\Submission;
use Wwwision\Neos\Submissions\Model\Submission\SubmissionId;

interface ForStoringSubmissions
{
    public function setup(): void;

    public function store(Submission $submission): void;

    public function remove(SubmissionId $submissionId): void;

    /**
     * Returns the submission with the given id, including archived submissions
     */
    public function findOne(SubmissionId $submissionId): Submission|null;

    /**
     * Returns all submissions matching the given filter, excluding archived submissions
     */
    public function find(SubmissionFilter $filter, Pagination|null $pagination = null): SubmissionFilterResult;

    /**
     * Returns the ids of all forms with at least one submission that has not been archived
     */
    public function findFormIds(): FormIds;
}
