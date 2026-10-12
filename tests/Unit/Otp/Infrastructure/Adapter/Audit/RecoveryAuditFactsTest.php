<?php

declare(strict_types=1);

namespace Tests\Unit\Otp\Infrastructure\Adapter\Audit;

use Audit\Application\Contract\AuditRecordRequest;
use Audit\Application\Port\Inbound\AuditRecordingPort;
use DateTimeImmutable;
use Otp\Application\Contract\Event\Totp\{RecoveryCodeConsumedEvent, RecoveryCodesGeneratedEvent};
use Otp\Infrastructure\Adapter\Audit\AuditRecordEventAdapter;
use PHPUnit\Framework\TestCase;

use function array_keys;
use function get_object_vars;

/**
 * Class RecoveryAuditFactsTest
 *
 * Keeps recovery audit payloads limited to actor, count, date, replacement flag and use purpose.
 *
 * @category Test
 */
final class RecoveryAuditFactsTest extends TestCase
{
  public function testGenerationAndConsumptionExposeNoProofMaterial(): void
  {
    $time = new DateTimeImmutable('2026-10-11T10:00:00+00:00');
    $audit = $this->createMock(AuditRecordingPort::class);
    $requests = [];
    $audit->expects(self::exactly(2))->method('requestRecord')->willReturnCallback(static function (AuditRecordRequest $request) use (&$requests): void { $requests[] = $request; });
    $adapter = new AuditRecordEventAdapter($audit);
    $generated = new RecoveryCodesGeneratedEvent('user', 10, true, $time);
    $consumed = new RecoveryCodeConsumedEvent('user', 'login', $time);
    $adapter->onRecoveryCodesGenerated($generated);
    $adapter->onRecoveryCodeConsumed($consumed);
    self::assertSame('otp.recovery_codes_generated', $requests[0]->action);
    self::assertSame('user', $requests[0]->actorId);
    self::assertSame(['count' => 10, 'regenerated' => true, 'generatedAt' => '2026-10-11T10:00:00+00:00'], $requests[0]->metadata);
    self::assertSame('otp.recovery_code_consumed', $requests[1]->action);
    self::assertSame(['purpose' => 'login'], $requests[1]->metadata);
    foreach ($requests as $request) {
      self::assertSame($time, $request->occurredAt);
      self::assertNull($request->actorEmail);
      self::assertNull($request->ipAddress);
      self::assertSame([], $request->emailMetadata);
    }
    self::assertSame(['occurredAt', 'userId', 'count', 'regenerated', 'generatedAt'], array_keys(get_object_vars($generated)));
    self::assertSame(['occurredAt', 'userId', 'purpose'], array_keys(get_object_vars($consumed)));
  }
}
