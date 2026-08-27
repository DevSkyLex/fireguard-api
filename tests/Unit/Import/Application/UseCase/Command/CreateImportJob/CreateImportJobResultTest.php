<?php

declare(strict_types=1);

namespace Tests\Unit\Import\Application\UseCase\Command\CreateImportJob;

use DateTimeImmutable;
use Import\Application\UseCase\Command\CreateImportJob\CreateImportJobResult;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test CreateImportJobResult.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(CreateImportJobResult::class)]
final class CreateImportJobResultTest extends TestCase
{
  #[Test]
  public function itExposesAllConstructorValues(): void
  {
    $createdAt = new DateTimeImmutable('2026-01-05T00:00:00+00:00');

    $result = new CreateImportJobResult(
      importJobId: 'job-1',
      organizationId: 'org-1',
      kind: 'facility',
      status: 'pending',
      originalFilename: 'facilities.csv',
      createdAt: $createdAt,
    );

    self::assertSame('job-1', $result->importJobId);
    self::assertSame('org-1', $result->organizationId);
    self::assertSame('facility', $result->kind);
    self::assertSame('pending', $result->status);
    self::assertSame('facilities.csv', $result->originalFilename);
    self::assertSame($createdAt, $result->createdAt);
    self::assertFalse($result->dryRun);
  }
}
