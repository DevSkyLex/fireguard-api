<?php

declare(strict_types=1);

namespace Tests\Unit\Mission\Domain\Service;

use Mission\Domain\Exception\MissionConflictException;
use Mission\Domain\Service\MissionTransitionPolicy;
use Mission\Domain\ValueObject\MissionStatus;
use PHPUnit\Framework\Attributes\{DataProvider, Test};
use PHPUnit\Framework\TestCase;

final class MissionTransitionPolicyTest extends TestCase
{
  /**
   * @return iterable<string, array{MissionStatus, MissionStatus}>
   */
  public static function allowedTransitions(): iterable
  {
    yield 'prepare to planned' => [MissionStatus::DRAFT, MissionStatus::PLANNED];
    yield 'first field work' => [MissionStatus::PLANNED, MissionStatus::IN_PROGRESS];
    yield 'submit for review' => [MissionStatus::IN_PROGRESS, MissionStatus::SUBMITTED];
    yield 'request changes' => [MissionStatus::SUBMITTED, MissionStatus::CHANGES_REQUESTED];
    yield 'resume corrections' => [MissionStatus::CHANGES_REQUESTED, MissionStatus::IN_PROGRESS];
  }

  #[Test]
  #[DataProvider('allowedTransitions')]
  public function itAllowsWorkflowTransitions(MissionStatus $from, MissionStatus $to): void
  {
    new MissionTransitionPolicy()->assertAllowed($from, $to);
    self::addToAssertionCount(1);
  }

  #[Test]
  public function itKeepsPublishedMissionsImmutable(): void
  {
    $this->expectException(MissionConflictException::class);

    new MissionTransitionPolicy()->assertAllowed(MissionStatus::PUBLISHED, MissionStatus::IN_PROGRESS);
  }
}
