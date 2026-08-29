<?php

declare(strict_types=1);

namespace Wwwision\Neos\Submissions\Factory;

use Wwwision\Neos\Submissions\Model\Preset\Preset;
use Wwwision\Neos\Submissions\Ports\ForStoringSubmissions;

interface ForStoringSubmissionsFactory
{
    public function create(Preset $preset): ForStoringSubmissions;
}
