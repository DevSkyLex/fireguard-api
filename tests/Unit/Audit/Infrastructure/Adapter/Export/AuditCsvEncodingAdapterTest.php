<?php

declare(strict_types=1);

namespace Tests\Unit\Audit\Infrastructure\Adapter\Export;

use Audit\Application\Contract\{AuditEventView, AuditExportSizeLimitException};
use Audit\Infrastructure\Adapter\Export\AuditCsvEncodingAdapter;
use Audit\Presentation\Api\Service\AuditEventCsvWriter;
use PHPUnit\Framework\TestCase;

use function fclose;
use function fopen;
use function rewind;
use function str_repeat;
use function stream_get_contents;

/**
 * Proves byte compatibility and bounded receipt materialization.
 *
 * @category Test
 */
final class AuditCsvEncodingAdapterTest extends TestCase
{
  public function testMaterializedCsvRetainsEveryOriginalByte(): void
  {
    $row = $this->row('Unicode é, quote " and newline\n');
    $stream = fopen('php://temp', 'w+b');
    self::assertIsResource($stream);

    try {
      new AuditEventCsvWriter()->write([$row], $stream);
      rewind($stream);
      $original = stream_get_contents($stream);
      self::assertIsString($original);
      self::assertSame($original, new AuditCsvEncodingAdapter()->encode([$row]));
    } finally {
      fclose($stream);
    }
  }

  public function testOversizedCsvFailsBeforeReturningAnyApplicationResult(): void
  {
    $row = $this->row(str_repeat('x', 2 * 1024 * 1024));
    $rows = (static function () use ($row) {
      for ($index = 0; $index < 33; ++$index) {
        yield $row;
      }
    })();
    $this->expectException(AuditExportSizeLimitException::class);
    new AuditCsvEncodingAdapter()->encode($rows);
  }

  /**
   * @param string $value representative recorded metadata
   *
   * @return AuditEventView immutable ledger row
   */
  private function row(string $value): AuditEventView
  {
    return new AuditEventView('f00e8400-e29b-41d4-a716-4466554c0901', 'audit.example', 'user', 'user-id', null, 'email-hash', 'example', 'subject', null, null, null, 'ip-hash', null, ['value' => $value], '2026-10-12T10:00:00+00:00', '2026-10-12T10:00:01+00:00', 'global', 1, null, 'event-hash');
  }
}
