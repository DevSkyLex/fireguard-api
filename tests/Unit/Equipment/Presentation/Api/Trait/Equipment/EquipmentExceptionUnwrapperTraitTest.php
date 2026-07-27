<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Presentation\Api\Trait\Equipment;

use Equipment\Domain\Exception\{
  AttachmentNotFoundException,
  EquipmentAlreadyDecommissionedException,
  EquipmentNotFoundException,
  EquipmentSerialNumberAlreadyExistsException,
  TagNotFoundException
};
use Equipment\Presentation\Api\Trait\Equipment\EquipmentExceptionUnwrapperTrait;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\{CoversTrait, DataProvider, Test};
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use RuntimeException;
use Shared\Application\Exception\MessengerRuntimeException;
use stdClass;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Throwable;

/**
 * Test EquipmentExceptionUnwrapperTraitTest.
 *
 * Equipment processors decide their HTTP status from what this trait digs
 * out of a messenger failure. The domain exception is buried two levels
 * down — inside a HandlerFailedException, itself wrapped by
 * MessengerRuntimeException — so a finder that only inspects the outer
 * throwable silently turns every 404 and 409 into a 500.
 *
 * @category Trait Tests
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversTrait(EquipmentExceptionUnwrapperTrait::class)]
final class EquipmentExceptionUnwrapperTraitTest extends TestCase
{
  // #region Constants
  private const string EQUIPMENT_ID = '550e8400-e29b-41d4-a716-446655475001';
  // #endregion

  // #region Methods
  /**
   * @return iterable<string, array{string, Throwable}>
   */
  public static function finderProvider(): iterable
  {
    yield 'equipment not found' => [
      'findEquipmentNotFoundException',
      EquipmentNotFoundException::withId(self::EQUIPMENT_ID),
    ];
    yield 'already decommissioned' => [
      'findEquipmentAlreadyDecommissionedException',
      EquipmentAlreadyDecommissionedException::withId(self::EQUIPMENT_ID),
    ];
    yield 'duplicate serial number' => [
      'findEquipmentSerialNumberAlreadyExistsException',
      EquipmentSerialNumberAlreadyExistsException::withSerialNumber('SN-1'),
    ];
    yield 'attachment not found' => [
      'findAttachmentNotFoundException',
      AttachmentNotFoundException::withId(self::EQUIPMENT_ID),
    ];
    yield 'tag not found' => [
      'findTagNotFoundException',
      TagNotFoundException::withId(self::EQUIPMENT_ID),
    ];
    yield 'invalid argument' => [
      'findInvalidArgumentException',
      new InvalidArgumentException('Unknown equipment type.'),
    ];
  }

  #[Test]
  #[DataProvider('finderProvider')]
  public function testFinderReturnsADirectlyThrownException(string $finder, Throwable $failure): void
  {
    self::assertSame($failure, $this->invoke($finder, $failure));
  }

  #[Test]
  #[DataProvider('finderProvider')]
  public function testFinderDigsTheExceptionOutOfAMessengerFailure(string $finder, Throwable $failure): void
  {
    $wrapped = MessengerRuntimeException::wrap(new HandlerFailedException(
      new Envelope(new stdClass()),
      [$failure],
    ));

    self::assertSame($failure, $this->invoke($finder, $wrapped));
  }

  #[Test]
  #[DataProvider('finderProvider')]
  public function testFinderReturnsNullWhenTheFailureIsUnrelated(string $finder, Throwable $failure): void
  {
    self::assertNull($this->invoke($finder, new RuntimeException('database is down')));
  }

  #[Test]
  public function testFinderFollowsThePreviousChain(): void
  {
    $expected = EquipmentNotFoundException::withId(self::EQUIPMENT_ID);
    $chained = new RuntimeException('outer', 0, new RuntimeException('inner', 0, $expected));

    self::assertSame($expected, $this->invoke('findEquipmentNotFoundException', $chained));
  }

  #[Test]
  public function testFinderIgnoresAWrappedExceptionOfAnotherType(): void
  {
    $wrapped = MessengerRuntimeException::wrap(new HandlerFailedException(
      new Envelope(new stdClass()),
      [TagNotFoundException::withId(self::EQUIPMENT_ID)],
    ));

    self::assertNull($this->invoke('findEquipmentNotFoundException', $wrapped));
  }

  private function invoke(string $finder, Throwable $exception): ?Throwable
  {
    $host = new class () {
      use EquipmentExceptionUnwrapperTrait;
    };

    $method = new ReflectionMethod($host, $finder);

    /** @var Throwable|null $result */
    $result = $method->invoke($host, $exception);

    return $result;
  }
  // #endregion
}
