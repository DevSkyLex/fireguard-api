<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Domain\Service;

use Intervention\Domain\Exception\InterventionConflictException;
use Intervention\Domain\Service\PublicationTransitionPolicy;
use Intervention\Domain\ValueObject\PublicationStatus;
use PHPUnit\Framework\Attributes\{DataProvider, Test};
use PHPUnit\Framework\TestCase;

final class PublicationTransitionPolicyTest extends TestCase
{
  /**
   * @return iterable<string, array{PublicationStatus, PublicationStatus}>
   */
  public static function allowedTransitions(): iterable
  {
    yield 'start processing' => [PublicationStatus::PENDING, PublicationStatus::PROCESSING];
    yield 'fail while pending' => [PublicationStatus::PENDING, PublicationStatus::FAILED];
    yield 'complete processing' => [PublicationStatus::PROCESSING, PublicationStatus::COMPLETED];
    yield 'fail while processing' => [PublicationStatus::PROCESSING, PublicationStatus::FAILED];
    yield 'retry a failure' => [PublicationStatus::FAILED, PublicationStatus::PENDING];
  }

  #[Test]
  #[DataProvider('allowedTransitions')]
  public function itAllowsWorkflowTransitions(PublicationStatus $from, PublicationStatus $to): void
  {
    new PublicationTransitionPolicy()->assertAllowed($from, $to);
    self::addToAssertionCount(1);
  }

  #[Test]
  public function itTreatsATransitionToTheSameStatusAsANoOp(): void
  {
    new PublicationTransitionPolicy()->assertAllowed(PublicationStatus::PROCESSING, PublicationStatus::PROCESSING);

    self::addToAssertionCount(1);
  }

  /**
   * @return iterable<string, array{PublicationStatus, PublicationStatus}>
   */
  public static function refusedTransitions(): iterable
  {
    yield 'completed is terminal' => [PublicationStatus::COMPLETED, PublicationStatus::PROCESSING];
    yield 'pending cannot skip straight to completed' => [PublicationStatus::PENDING, PublicationStatus::COMPLETED];
    yield 'processing cannot go back to pending' => [PublicationStatus::PROCESSING, PublicationStatus::PENDING];
    yield 'failed cannot go straight to processing' => [PublicationStatus::FAILED, PublicationStatus::PROCESSING];
    yield 'failed cannot go straight to completed' => [PublicationStatus::FAILED, PublicationStatus::COMPLETED];
  }

  #[Test]
  #[DataProvider('refusedTransitions')]
  public function itRefusesWorkflowIllegalTransitions(PublicationStatus $from, PublicationStatus $to): void
  {
    $this->expectException(InterventionConflictException::class);

    new PublicationTransitionPolicy()->assertAllowed($from, $to);
  }

  #[Test]
  public function itNeverAllowsTransitionsFromTheTerminalStatus(): void
  {
    self::assertSame([], new PublicationTransitionPolicy()->allowedFrom(PublicationStatus::COMPLETED));
  }

  #[Test]
  public function itListsTheWorkflowLegalTransitionsFromEachStatus(): void
  {
    $policy = new PublicationTransitionPolicy();

    self::assertSame(
      [PublicationStatus::PROCESSING, PublicationStatus::FAILED],
      $policy->allowedFrom(PublicationStatus::PENDING),
    );
    self::assertSame(
      [PublicationStatus::COMPLETED, PublicationStatus::FAILED],
      $policy->allowedFrom(PublicationStatus::PROCESSING),
    );
    self::assertSame(
      [PublicationStatus::PENDING],
      $policy->allowedFrom(PublicationStatus::FAILED),
    );
  }
}
