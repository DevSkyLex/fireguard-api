<?php

declare(strict_types=1);

namespace Tests\Unit\Import\Application\UseCase\Query\GetImportJob;

use Import\Application\UseCase\Query\GetImportJob\GetImportJobQuery;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test GetImportJobQuery.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(GetImportJobQuery::class)]
final class GetImportJobQueryTest extends TestCase
{
  #[Test]
  public function itExposesAllConstructorValues(): void
  {
    $query = new GetImportJobQuery(userId: 'user-1', importJobId: 'job-1');

    self::assertSame('user-1', $query->userId);
    self::assertSame('job-1', $query->importJobId);
  }
}
