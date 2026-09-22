<?php

declare(strict_types=1);

namespace Wwwision\Neos\Submissions\Model\Preset;

use Wwwision\Neos\Submissions\LabelGenerator\FormLabelGenerator\FormLabelGenerator;
use Wwwision\Neos\Submissions\LabelGenerator\SubmissionLabelGenerator\SubmissionLabelGenerator;

final readonly class Preset
{
    /**
     * @param array<string, mixed> $options
     */
    public function __construct(
        public PresetId $id,
        public PresetTitle $title,
        public SubmissionLabelGenerator $submissionLabelGenerator,
        public FormLabelGenerator $formLabelGenerator,
        public array $options,
    ) {}
}
