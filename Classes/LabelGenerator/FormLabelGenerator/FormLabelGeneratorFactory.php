<?php

declare(strict_types=1);

namespace Wwwision\Neos\Submissions\LabelGenerator\FormLabelGenerator;

interface FormLabelGeneratorFactory
{
    /**
     * @param array<mixed> $options
     */
    public function create(array $options): FormLabelGenerator;
}
