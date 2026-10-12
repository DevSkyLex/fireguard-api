<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Infrastructure\Adapter\Idempotency\ResponseProjection;

use Inspection\Application\UseCase\Command\Checklist\ArchiveChecklist\ArchiveChecklistCommand;
use Inspection\Application\UseCase\Command\Inspection\CloseInspection\CloseInspectionCommand;
use Inspection\Application\UseCase\Command\Inspection\PatchCanonicalInspection\PatchCanonicalInspectionCommand;
use Inspection\Application\UseCase\Query\Checklist\GetChecklist\GetChecklistQuery;
use Inspection\Application\UseCase\Query\Inspection\GetInspection\GetInspectionQuery;
use Inspection\Application\UseCase\Query\Inspection\ReadCanonicalInspection\ReadCanonicalInspectionQuery;
use Inspection\Infrastructure\Adapter\Idempotency\ResponseProjection\InspectionHttpIdempotencyResponseProjectionAdapter;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Message\VoidResult;

/**
 * Test InspectionHttpIdempotencyResponseProjectionAdapterTest.
 *
 * Preserves the exact inspection and checklist read scopes after void-returning commands.
 *
 * @category Unit Tests
 */
#[CoversClass(InspectionHttpIdempotencyResponseProjectionAdapter::class)]
final class InspectionHttpIdempotencyResponseProjectionAdapterTest extends TestCase
{
  #[Test]
  public function itConstructsTheExactPostwriteInspectionAndChecklistReads(): void
  {
    $factory = new InspectionHttpIdempotencyResponseProjectionAdapter();
    self::assertEquals([new ReadCanonicalInspectionQuery('inspection')], $factory->queries('canonical_inspection_patch', new PatchCanonicalInspectionCommand('inspection', 2), new VoidResult()));
    self::assertEquals([new GetInspectionQuery('organization', 'inspection')], $factory->queries('inspection_close', new CloseInspectionCommand('organization', 'inspection'), new VoidResult()));
    self::assertEquals([new GetChecklistQuery('organization', 'checklist')], $factory->queries('inspection_archive_checklist', new ArchiveChecklistCommand('organization', 'checklist'), new VoidResult()));
    self::assertFalse($factory->supports('canonical_inspection_delete'));
  }
}
