<?php

declare(strict_types=1);

namespace Wwwision\Neos\Submissions\Model\Form;

final readonly class Form
{
    public function __construct(
        public FormId $id,
        public FormLabel $label,
    ) {}
}
