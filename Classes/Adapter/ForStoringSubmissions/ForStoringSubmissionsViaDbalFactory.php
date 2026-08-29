<?php

declare(strict_types=1);

namespace Wwwision\Neos\Submissions\Adapter\ForStoringSubmissions;

use Doctrine\DBAL\Connection;
use Wwwision\Neos\Submissions\Factory\ForStoringSubmissionsFactory;
use Wwwision\Neos\Submissions\Model\Preset\Preset;

final readonly class ForStoringSubmissionsViaDbalFactory implements ForStoringSubmissionsFactory
{


    public function __construct(
        private Connection $connection,
    ) {
    }

    public function create(Preset $preset): ForStoringSubmissionsViaDbal
    {
        $tableName = $preset->options['tableName'] ?? ('wwwision_neos_submissions_' . $preset->id->value);
        return new ForStoringSubmissionsViaDbal($this->connection, $tableName);
    }
}
