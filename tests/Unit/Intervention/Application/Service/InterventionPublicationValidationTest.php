<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Application\Service;

use Intervention\Application\Port\Outbound\InterventionPublicationGuardPort;
use Intervention\Application\Service\InterventionPublicationValidation;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(InterventionPublicationValidation::class)]
final class InterventionPublicationValidationTest extends TestCase
{
  #[Test]
  public function itValidatesTheMergedGraphBeforeMutationsAndTheActualGraphBeforeCommit(): void
  {
    $order = [];
    $guard = $this->createMock(InterventionPublicationGuardPort::class);
    $guard->expects(self::once())->method('beginPublication')->with('org', 'intervention', [])->willReturnCallback(static function () use (&$order): void { $order[] = 'merged'; });
    $guard->expects(self::once())->method('finishPublication')->willReturnCallback(static function () use (&$order): void { $order[] = 'actual'; });
    $guard->expects(self::once())->method('endPublication')->willReturnCallback(static function () use (&$order): void { $order[] = 'leave'; });
    $result = new InterventionPublicationValidation([$guard])->publication('org', 'intervention', [], static function () use (&$order): int {
      $order[] = 'mutate';

      return 42;
    });
    self::assertSame(42, $result);
    self::assertSame(['merged', 'mutate', 'actual', 'leave'], $order);
  }

  #[Test]
  public function itAlwaysClearsScopedValidationAfterALateFailure(): void
  {
    $guard = $this->createMock(InterventionPublicationGuardPort::class);
    $guard->expects(self::once())->method('beginPublication');
    $guard->expects(self::never())->method('finishPublication');
    $guard->expects(self::once())->method('endPublication');
    $this->expectException(RuntimeException::class);
    new InterventionPublicationValidation([$guard])->publication('org', 'intervention', [], static fn () => throw new RuntimeException('late failure'));
  }

  #[Test]
  public function itRefusesMutationWhenAnotherOwnersGraphIsInvalid(): void
  {
    $first = $this->createMock(InterventionPublicationGuardPort::class);
    $first->expects(self::once())->method('beginPublication');
    $first->expects(self::once())->method('endPublication');
    $second = $this->createMock(InterventionPublicationGuardPort::class);
    $second->expects(self::once())->method('beginPublication')->willThrowException(new RuntimeException('invalid merged graph'));
    $second->expects(self::once())->method('endPublication');
    $mutated = false;

    try {
      new InterventionPublicationValidation([$first, $second])->publication('org', 'intervention', [], static function () use (&$mutated): void { $mutated = true; });
      self::fail('The invalid graph must abort publication.');
    } catch (RuntimeException $exception) {
      self::assertSame('invalid merged graph', $exception->getMessage());
      self::assertFalse($mutated);
    }
  }
}
