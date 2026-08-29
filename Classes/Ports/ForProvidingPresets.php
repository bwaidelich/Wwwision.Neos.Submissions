<?php

declare(strict_types=1);

namespace Wwwision\Neos\Submissions\Ports;

use Wwwision\Neos\Submissions\Model\Preset\Presets;

interface ForProvidingPresets
{

    public function getPresets(): Presets;

}
