<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\EventDispatcher;

use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Infrastructure\EventDispatcher\ReflectionIntegrationEventSnapshotAdapter;
use TypeError;

/**
 * Class ReflectionIntegrationEventSnapshotAdapterTest
 *
 * Protects constructor invariants and public-only access during historical event restoration.
 *
 * @category Unit Test
 */
#[CoversClass(ReflectionIntegrationEventSnapshotAdapter::class)]
final class ReflectionIntegrationEventSnapshotAdapterTest extends TestCase
{
  // #region Tests
  /**
   * Method testRestorationRunsTheContractsConstructorValidation
   *
   * A copied snapshot cannot bypass a constructor's identifier invariant.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testRestorationRunsTheContractsConstructorValidation(): void
  {
    $contract = new class ('accepted') {
      public function __construct(public string $identifier)
      {
        if ('accepted' !== $identifier) {
          throw new InvalidArgumentException('Invalid event identifier.');
        }
      }
    };

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Invalid event identifier.');
    new ReflectionIntegrationEventSnapshotAdapter()->copy((object) ['identifier' => 'rejected'], $contract::class);
  }

  /**
   * Method testRestorationKeepsStrictConstructorParameterTypes
   *
   * Public field lookup cannot weaken the contract's scalar type requirement.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testRestorationKeepsStrictConstructorParameterTypes(): void
  {
    $contract = new class ('accepted') {
      public function __construct(public string $identifier)
      {
      }
    };

    $this->expectException(TypeError::class);
    new ReflectionIntegrationEventSnapshotAdapter()->copy((object) ['identifier' => 42], $contract::class);
  }

  /**
   * Method testPrivateStateWithoutAPublicAccessorIsRejected
   *
   * Historical conversion refuses unreadable state instead of bypassing visibility.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testPrivateStateWithoutAPublicAccessorIsRejected(): void
  {
    $event = new class () {
      private string $identifier = 'private';

      public function publicValue(): string
      {
        return $this->identifier;
      }
    };
    $contract = new class ('accepted') {
      public function __construct(public string $identifier)
      {
      }
    };

    self::assertSame('private', $event->publicValue());

    $this->expectException(LogicException::class);
    $this->expectExceptionMessage('Integration event snapshot has no public value for identifier.');
    new ReflectionIntegrationEventSnapshotAdapter()->copy($event, $contract::class);
  }

  /**
   * Method testAContractWithoutARestorationConstructorIsRejected
   *
   * Unsupported contract shapes fail before any object can be populated.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testAContractWithoutARestorationConstructorIsRejected(): void
  {
    $contract = new class () {
    };

    $this->expectException(LogicException::class);
    $this->expectExceptionMessage('Integration event contracts require a typed restoration constructor.');
    new ReflectionIntegrationEventSnapshotAdapter()->copy((object) [], $contract::class);
  }
  // #endregion
}
