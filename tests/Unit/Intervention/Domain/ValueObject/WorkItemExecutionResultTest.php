<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Domain\ValueObject;

use DateTimeImmutable;
use Intervention\Domain\Exception\InterventionValidationException;
use Intervention\Domain\ValueObject\WorkItemExecutionResult;
use PHPUnit\Framework\TestCase;

/** Tests real execution dates and explicit factual outcomes. */
final class WorkItemExecutionResultTest extends TestCase
{
  public function testItPreservesActualDateAndTrimsExecutedWork(): void
  {
    $result = WorkItemExecutionResult::fromPayload($this->payload());
    self::assertSame('2026-09-30T15:00:00+02:00', $result->performedAt->format('c'));
    self::assertSame('Seal replaced', $result->workPerformed);
    self::assertSame('successful', $result->outcome);
  }

  public function testInvalidCalendarDateIsRejectedInsteadOfRolledForward(): void
  {
    $this->expectException(InterventionValidationException::class);
    WorkItemExecutionResult::fromPayload([...$this->payload(), 'performedAt' => '2026-02-30T15:00:00+02:00']);
  }

  public function testDateWithoutTimezoneIsRejected(): void
  {
    $this->expectException(InterventionValidationException::class);
    WorkItemExecutionResult::fromPayload([...$this->payload(), 'performedAt' => '2026-09-30T15:00:00']);
  }

  public function testFailedWorkIsAnExplicitFact(): void
  {
    self::assertSame('failed', WorkItemExecutionResult::fromPayload([...$this->payload(), 'outcome' => 'failed'])->outcome);
  }

  public function testAnActualExecutionCannotBeDatedAfterItsRecordingInstant(): void
  {
    $this->expectException(InterventionValidationException::class);
    WorkItemExecutionResult::fromPayload($this->payload())->assertAlreadyPerformed(new DateTimeImmutable('2026-09-29T00:00:00+00:00'));
  }

  /**
   * @return array<string,mixed>
   */
  private function payload(): array
  {
    return ['equipmentId' => '550e8400-e29b-41d4-a716-446655441511', 'performedAt' => '2026-09-30T15:00:00+02:00', 'outcome' => 'successful', 'workPerformed' => ' Seal replaced '];
  }
}
