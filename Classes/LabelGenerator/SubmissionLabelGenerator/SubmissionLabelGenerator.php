<?php

declare(strict_types=1);

namespace Wwwision\Neos\Submissions\LabelGenerator\SubmissionLabelGenerator;

use Wwwision\Neos\Submissions\Model\Submission\SubmissionData;
use Wwwision\Neos\Submissions\Model\Submission\SubmissionId;
use Wwwision\Neos\Submissions\Model\Submission\SubmissionLabel;

interface SubmissionLabelGenerator
{

    public function generate(SubmissionId $submissionId, SubmissionData $data): SubmissionLabel;

}
