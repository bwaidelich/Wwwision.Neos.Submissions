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

    public function findOne(SubmissionId $submissionId): Submission|null;

    public function find(SubmissionFilter $filter, Pagination|null $pagination = null): SubmissionFilterResult;

    public function findFormIds(): FormIds;

}
