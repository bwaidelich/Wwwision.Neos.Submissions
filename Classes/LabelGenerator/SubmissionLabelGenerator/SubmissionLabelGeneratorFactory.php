<?php

declare(strict_types=1);

namespace Wwwision\Neos\Submissions\LabelGenerator\SubmissionLabelGenerator;

interface SubmissionLabelGeneratorFactory
{
    public function create(array $options): SubmissionLabelGenerator;

}
