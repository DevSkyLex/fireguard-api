<?php

declare(strict_types=1);

namespace Tests\Unit\Approval\Application\Contract\Gate;

use Approval\Application\Contract\Gate\ApprovalGateDecision;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test ApprovalGateDecision.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ApprovalGateDecision::class)]
final class ApprovalGateDecisionTest extends TestCase
{
  #[Test]
  public function testApplyNowIsNotDeferred(): void
  {
    $decision = ApprovalGateDecision::applyNow();

    self::assertFalse($decision->deferred);
    self::assertNull($decision->requestId);
    self::assertNull($decision->status);
    self::assertNull($decision->expiresAt);
  }

  #[Test]
  public function testDeferredCarriesRequestDetails(): void
  {
    $expiresAt = new DateTimeImmutable('2026-02-01T00:00:00+00:00');

    $decision = ApprovalGateDecision::deferred('req-1', 'pending', $expiresAt);

    self::assertTrue($decision->deferred);
    self::assertSame('req-1', $decision->requestId);
    self::assertSame('pending', $decision->status);
    self::assertSame($expiresAt, $decision->expiresAt);
  }
}
