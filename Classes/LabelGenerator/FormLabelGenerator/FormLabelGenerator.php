<?php

declare(strict_types=1);

namespace Wwwision\Neos\Submissions\LabelGenerator\FormLabelGenerator;

use Wwwision\Neos\Submissions\Model\Form\FormId;
use Wwwision\Neos\Submissions\Model\Form\FormLabel;
use Wwwision\Neos\Submissions\Model\Preset\Preset;

interface FormLabelGenerator
{

    public function generate(FormId $formId, Preset $preset): FormLabel;

}
