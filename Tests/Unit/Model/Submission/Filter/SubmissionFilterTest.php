<?php

declare(strict_types=1);

namespace Wwwision\Neos\Submissions\Tests\Unit\Model\Submission\Filter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Wwwision\Neos\Submissions\Model\Submission\Filter\SubmissionFilter;

#[CoversClass(SubmissionFilter::class)]
final class SubmissionFilterTest extends TestCase
{
    #[Test]
    public function default_filter_is_empty(): void
    {
        $filter = SubmissionFilter::default();

        self::assertTrue($filter->isEmpty());
        self::assertSame(['searchTerm' => '', 'formId' => ''], $filter->getArray());
    }

    #[Test]
    public function blank_search_term_is_treated_as_unset(): void
    {
        self::assertTrue(SubmissionFilter::create(searchTerm: '   ')->isEmpty());
    }

    #[Test]
    public function search_term_of_zero_is_kept(): void
    {
        $filter = SubmissionFilter::create(searchTerm: '0');

        self::assertFalse($filter->isEmpty());
        self::assertSame('0', $filter->searchTerm?->value);
    }

    #[Test]
    public function create_converts_scalar_arguments(): void
    {
        $filter = SubmissionFilter::create(searchTerm: 'Jane', formId: 'form');

        self::assertSame('Jane', $filter->searchTerm?->value);
        self::assertSame('form', $filter->formId?->value);
        self::assertSame(['searchTerm' => 'Jane', 'formId' => 'form'], $filter->getArray());
    }
}
