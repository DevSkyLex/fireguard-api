<?php

declare(strict_types=1);

namespace Tests\Unit\Audit\Presentation\Api\Service;

use Audit\Application\Contract\AuditEventView;
use Audit\Presentation\Api\Service\AuditEventCsvWriter;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

use function explode;
use function fclose;
use function fopen;
use function rewind;
use function stream_get_contents;
use function trim;

/**
 * Test AuditEventCsvWriterTest.
 *
 * @category Service Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(AuditEventCsvWriter::class)]
final class AuditEventCsvWriterTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testWriteEmitsHeaderThenOneRowPerEvent(): void
  {
    $writer = new AuditEventCsvWriter();

    $first = $this->view(id: 'event-1', action: 'auth.login_success');
    $second = $this->view(id: 'event-2', action: 'auth.login_failed');

    $handle = fopen('php://memory', 'r+');
    self::assertIsResource($handle);

    $writer->write([$first, $second], $handle);

    rewind($handle);
    $csv = stream_get_contents($handle);
    fclose($handle);

    self::assertIsString($csv);

    $lines = explode("\n", trim($csv));
    self::assertCount(3, $lines);
    self::assertStringStartsWith('id,action,actor_type,actor_id,actor_email,actor_email_hash,subject_type,subject_id,client_id,tenant_id,ip_address,ip_hash,user_agent,metadata,occurred_at,recorded_at,chain_id,sequence,prev_hash,event_hash', $lines[0]);
    self::assertStringContainsString('event-1', $lines[1]);
    self::assertStringContainsString('auth.login_success', $lines[1]);
    self::assertStringContainsString('event-2', $lines[2]);
    self::assertStringContainsString('auth.login_failed', $lines[2]);
  }

  #[Test]
  public function testWriteEncodesMetadataAsJsonAndNeverLeaksNullFieldsAsThePhpNullWord(): void
  {
    $writer = new AuditEventCsvWriter();

    $view = $this->view(id: 'event-1', action: 'auth.login_success', metadata: ['reason' => 'bad_password']);

    $handle = fopen('php://memory', 'r+');
    self::assertIsResource($handle);

    $writer->write([$view], $handle);

    rewind($handle);
    $csv = stream_get_contents($handle);
    fclose($handle);

    self::assertIsString($csv);
    self::assertStringContainsString('"{""reason"":""bad_password""}"', $csv);
    self::assertStringNotContainsString('NULL', $csv);
  }

  /**
   * @param array<string, mixed> $metadata
   */
  private function view(string $id, string $action, array $metadata = []): AuditEventView
  {
    return new AuditEventView(
      id: $id,
      action: $action,
      actorType: 'user',
      actorId: null,
      actorEmail: null,
      actorEmailHash: null,
      subjectType: null,
      subjectId: null,
      clientId: null,
      tenantId: null,
      ipAddress: null,
      ipHash: null,
      userAgent: null,
      metadata: $metadata,
      occurredAt: '2026-01-01T00:00:00+00:00',
      recordedAt: '2026-01-01T00:00:01+00:00',
      chainId: 'global',
      sequence: 1,
      prevHash: null,
      eventHash: 'event-hash',
    );
  }
  // #endregion
}
