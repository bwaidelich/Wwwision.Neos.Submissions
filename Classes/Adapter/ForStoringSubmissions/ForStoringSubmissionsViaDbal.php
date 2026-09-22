<?php

declare(strict_types=1);

namespace Wwwision\Neos\Submissions\Adapter\ForStoringSubmissions;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Generator;
use JsonException;
use RuntimeException;
use Wwwision\Neos\Submissions\Model\Form\FormId;
use Wwwision\Neos\Submissions\Model\Form\FormIds;
use Wwwision\Neos\Submissions\Model\Form\FormLabel;
use Wwwision\Neos\Submissions\Model\Preset\PresetId;
use Wwwision\Neos\Submissions\Model\Submission\Filter\Pagination;
use Wwwision\Neos\Submissions\Model\Submission\Filter\SubmissionFilter;
use Wwwision\Neos\Submissions\Model\Submission\Filter\SubmissionFilterResult;
use Wwwision\Neos\Submissions\Model\Submission\Submission;
use Wwwision\Neos\Submissions\Model\Submission\SubmissionId;
use Wwwision\Neos\Submissions\Model\Submission\SubmissionLabel;
use Wwwision\Neos\Submissions\Model\Submission\Submissions;
use Wwwision\Neos\Submissions\Ports\ForStoringSubmissions;

final readonly class ForStoringSubmissionsViaDbal implements ForStoringSubmissions
{
    public function __construct(
        private Connection $connection,
        private string $tableName,
    ) {}

    /**
     * Creates the underlying database table if it doesn't exist yet, or adds any missing columns/indexes to it.
     * Safe to call repeatedly (e.g. on every deployment).
     */
    public function setup(): void
    {
        $schemaManager = $this->connection->createSchemaManager();
        $fromSchema = $schemaManager->introspectSchema();
        $toSchema = clone $fromSchema;

        $table = $toSchema->hasTable($this->tableName) ? $toSchema->getTable($this->tableName) : $toSchema->createTable($this->tableName);
        self::ensureColumns($table);

        $schemaDiff = $schemaManager->createComparator()->compareSchemas($fromSchema, $toSchema);
        foreach ($this->connection->getDatabasePlatform()->getAlterSchemaSQL($schemaDiff) as $statement) {
            $this->connection->executeStatement($statement);
        }
    }

    private static function ensureColumns(Table $table): void
    {
        if (!$table->hasColumn('id')) {
            $table->addColumn('id', Types::STRING, ['length' => 40]);
        }
        if (!$table->hasColumn('preset_id')) {
            $table->addColumn('preset_id', Types::STRING, ['length' => PresetId::MAX_LENGTH]);
        }
        if (!$table->hasColumn('form_id')) {
            $table->addColumn('form_id', Types::STRING, ['length' => FormId::MAX_LENGTH]);
        }
        if (!$table->hasColumn('form_label')) {
            $table->addColumn('form_label', Types::STRING, ['length' => FormLabel::MAX_LENGTH]);
        }
        if (!$table->hasColumn('label')) {
            $table->addColumn('label', Types::STRING, ['length' => SubmissionLabel::MAX_LENGTH]);
        }
        if (!$table->hasColumn('data')) {
            $table->addColumn('data', Types::TEXT);
        }
        if (!$table->hasColumn('protected')) {
            $table->addColumn('protected', Types::BOOLEAN);
        }
        if (!$table->hasColumn('created_at')) {
            $table->addColumn('created_at', Types::STRING, ['length' => 32]);
        }
        if (!$table->hasColumn('archived_at')) {
            $table->addColumn('archived_at', Types::STRING, ['length' => 32, 'notnull' => false]);
        }
        if ($table->getPrimaryKey() === null) {
            $table->setPrimaryKey(['id']);
        }
        if (!$table->columnsAreIndexed(['form_id'])) {
            $table->addIndex(['form_id']);
        }
    }

    public function store(Submission $submission): void
    {
        $row = self::submissionToRow($submission);
        $columns = array_keys($row);
        $updateColumns = array_filter($columns, static fn(string $column): bool => $column !== 'id');
        $columnList = implode(', ', $columns);
        $valuesList = implode(', ', array_map(static fn(string $column): string => ':' . $column, $columns));

        $platform = $this->connection->getDatabasePlatform();
        $sql = match (true) {
            $platform instanceof AbstractMySQLPlatform => sprintf(
                'INSERT INTO %s (%s) VALUES (%s) ON DUPLICATE KEY UPDATE %s',
                $this->tableName,
                $columnList,
                $valuesList,
                implode(', ', array_map(static fn(string $column): string => sprintf('%1$s = VALUES(%1$s)', $column), $updateColumns)),
            ),
            $platform instanceof PostgreSQLPlatform || $platform instanceof SqlitePlatform => sprintf(
                'INSERT INTO %s (%s) VALUES (%s) ON CONFLICT (id) DO UPDATE SET %s',
                $this->tableName,
                $columnList,
                $valuesList,
                implode(', ', array_map(static fn(string $column): string => sprintf('%1$s = EXCLUDED.%1$s', $column), $updateColumns)),
            ),
            default => throw new RuntimeException(sprintf('Upsert is not supported for database platform "%s"', $platform::class), 1787993301),
        };
        $this->connection->executeStatement($sql, $row);
    }

    public function remove(SubmissionId $submissionId): void
    {
        $this->connection->delete($this->tableName, ['id' => $submissionId->value]);
    }

    public function findOne(SubmissionId $submissionId): Submission|null
    {
        $row = $this->connection->fetchAssociative(
            sprintf('SELECT * FROM %s WHERE id = :id', $this->tableName),
            ['id' => $submissionId->value],
        );
        if ($row === false) {
            return null;
        }
        return self::rowToSubmission($row);
    }

    public function find(SubmissionFilter $filter, Pagination|null $pagination = null): SubmissionFilterResult
    {
        $queryBuilder = $this->connection->createQueryBuilder()
            ->select('*')
            ->from($this->tableName);
        $this->applyFilter($queryBuilder, $filter);

        $totalCountResult = (clone $queryBuilder)->select('COUNT(*)')->executeQuery()->fetchOne();
        $totalCount = is_numeric($totalCountResult) ? (int) $totalCountResult : 0;

        // created_at is stored as a second-precision UTC string, so the id acts as a tiebreaker to keep pagination stable
        $queryBuilder->orderBy('created_at', 'DESC')->addOrderBy('id', 'DESC');
        if ($pagination !== null) {
            $queryBuilder->setMaxResults($pagination->resultsPerPage)->setFirstResult($pagination->offset);
        }
        $result = $queryBuilder->executeQuery();

        $submissions = Submissions::fromIterable((function () use ($result): Generator {
            foreach ($result->iterateAssociative() as $row) {
                yield self::rowToSubmission($row);
            }
        })());

        return SubmissionFilterResult::create($submissions, $totalCount);
    }

    public function findFormIds(): FormIds
    {
        $queryBuilder = $this->connection->createQueryBuilder()
            ->select('form_id')
            ->from($this->tableName)
            ->groupBy('form_id');
        $result = $queryBuilder->executeQuery();
        return FormIds::fromArray($result->fetchFirstColumn());
    }

    private function applyFilter(QueryBuilder $queryBuilder, SubmissionFilter $filter): void
    {
        if ($filter->formId !== null) {
            $queryBuilder
                ->andWhere('form_id = :formId')
                ->setParameter('formId', $filter->formId->value);
        }
        if ($filter->searchTerm !== null) {
            $queryBuilder
                ->andWhere("(label LIKE :searchTerm ESCAPE '!' OR data LIKE :searchTerm ESCAPE '!')")
                ->setParameter('searchTerm', '%' . self::escapeLikeValue($filter->searchTerm->value) . '%');
        }
    }

    /**
     * Escapes LIKE wildcards in a user-supplied search term so that "%" and "_" are matched literally
     */
    private static function escapeLikeValue(string $value): string
    {
        return addcslashes($value, '!%_');
    }

    /**
     * @return array<string, mixed>
     */
    private static function submissionToRow(Submission $submission): array
    {
        try {
            // keep unicode characters and slashes as-is so that the stored JSON can be searched via LIKE
            $data = json_encode($submission->data->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (JsonException $e) {
            throw new RuntimeException(sprintf('Failed to encode JSON data for submission "%s": %s', $submission->id->value, $e->getMessage()), 1787993243, $e);
        }
        return [
            'id' => $submission->id->value,
            'preset_id' => $submission->presetId->value,
            'form_id' => $submission->formId->value,
            'form_label' => $submission->formLabel->value,
            'label' => $submission->label->value,
            'data' => $data,
            'protected' => $submission->protected ? 1 : 0,
            'created_at' => self::formatDateTime($submission->createdAt),
            'archived_at' => $submission->archivedAt !== null ? self::formatDateTime($submission->archivedAt) : null,
        ];
    }

    /**
     * Timestamps are stored as UTC strings so that their lexicographic order matches the chronological order
     */
    private static function formatDateTime(DateTimeImmutable $dateTime): string
    {
        return $dateTime->setTimezone(new DateTimeZone('UTC'))->format(DATE_ATOM);
    }

    private static function parseDateTime(string $value): DateTimeImmutable
    {
        $dateTime = DateTimeImmutable::createFromFormat(DATE_ATOM, $value);
        if ($dateTime === false) {
            throw new RuntimeException(sprintf('Failed to parse date time string "%s"', $value), 1789995001);
        }
        return $dateTime->setTimezone(new DateTimeZone(date_default_timezone_get()));
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function rowToSubmission(array $row): Submission
    {
        $id = self::stringColumn($row, 'id');
        try {
            $data = json_decode(self::stringColumn($row, 'data'), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException(sprintf('Failed to decode JSON data for submission "%s": %s', $id, $e->getMessage()), 1787993221, $e);
        }
        if (!is_array($data)) {
            throw new RuntimeException(sprintf('Expected JSON data of submission "%s" to be an object, got %s', $id, get_debug_type($data)), 1789995007);
        }
        /** @var array<string, mixed> $data */
        $archivedAt = $row['archived_at'] ?? null;
        return Submission::create(
            id: $id,
            presetId: self::stringColumn($row, 'preset_id'),
            formId: self::stringColumn($row, 'form_id'),
            formLabel: self::stringColumn($row, 'form_label'),
            label: self::stringColumn($row, 'label'),
            protected: (bool) ($row['protected'] ?? false),
            data: $data,
            createdAt: self::parseDateTime(self::stringColumn($row, 'created_at')),
            archivedAt: $archivedAt !== null ? self::parseDateTime(self::stringColumn($row, 'archived_at')) : null,
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function stringColumn(array $row, string $column): string
    {
        $value = $row[$column] ?? null;
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        if (!is_string($value)) {
            throw new RuntimeException(sprintf('Expected column "%s" to be a string, got %s', $column, get_debug_type($value)), 1789995008);
        }
        return $value;
    }
}
