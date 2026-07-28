<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Presentation\Api\Trait\Inspection;

use Inspection\Domain\Exception\{
  ChecklistArchivedException,
  ChecklistInUseException,
  ChecklistNotFoundException,
  ChecklistReferenceCodeAlreadyExistsException,
  InspectionAlreadyCancelledException,
  InspectionAlreadyClosedException,
  InspectionAlreadySubmittedException,
  InspectionNotFoundException,
  InspectionNotSubmittedException,
  NonConformityAlreadyResolvedException,
  NonConformityNotFoundException
};
use Inspection\Presentation\Api\Trait\Inspection\InspectionExceptionUnwrapperTrait;
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
 * Test InspectionExceptionUnwrapperTraitTest.
 *
 * Inspection providers and processors pick their HTTP status from whatever
 * this trait digs out of a messenger failure: the domain exception sits two
 * levels down, inside a HandlerFailedException itself wrapped by
 * MessengerRuntimeException, so a finder that only looks at the outer
 * throwable would turn every 404 and 409 into a 500.
 *
 * @category Trait Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversTrait(InspectionExceptionUnwrapperTrait::class)]
final class InspectionExceptionUnwrapperTraitTest extends TestCase
{
  private const string ID = '550e8400-e29b-41d4-a716-446655440501';

  /**
   * @return iterable<string, array{string, Throwable}>
   */
  public static function finderProvider(): iterable
  {
    yield 'inspection not found' => ['findInspectionNotFoundException', InspectionNotFoundException::withId(self::ID)];
    yield 'inspection already closed' => ['findInspectionAlreadyClosedException', InspectionAlreadyClosedException::withId(self::ID)];
    yield 'inspection already cancelled' => ['findInspectionAlreadyCancelledException', InspectionAlreadyCancelledException::withId(self::ID)];
    yield 'inspection already submitted' => ['findInspectionAlreadySubmittedException', InspectionAlreadySubmittedException::withId(self::ID)];
    yield 'inspection not submitted' => ['findInspectionNotSubmittedException', InspectionNotSubmittedException::withId(self::ID)];
    yield 'non-conformity not found' => ['findNonConformityNotFoundException', NonConformityNotFoundException::withId(self::ID)];
    yield 'non-conformity already resolved' => ['findNonConformityAlreadyResolvedException', NonConformityAlreadyResolvedException::withId(self::ID)];
    yield 'checklist not found' => ['findChecklistNotFoundException', ChecklistNotFoundException::withId(self::ID)];
    yield 'checklist archived' => ['findChecklistArchivedException', ChecklistArchivedException::withId(self::ID)];
    yield 'checklist in use' => ['findChecklistInUseException', ChecklistInUseException::withId(self::ID)];
    yield 'duplicate reference code' => ['findChecklistReferenceCodeAlreadyExistsException', ChecklistReferenceCodeAlreadyExistsException::withReferenceCode('CHK-001')];
    yield 'invalid argument' => ['findInvalidArgumentException', new InvalidArgumentException('Unknown inspection result.')];
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
    $expected = InspectionNotFoundException::withId(self::ID);
    $chained = new RuntimeException('outer', 0, new RuntimeException('inner', 0, $expected));

    self::assertSame($expected, $this->invoke('findInspectionNotFoundException', $chained));
  }

  #[Test]
  public function testFinderIgnoresAWrappedExceptionOfAnotherType(): void
  {
    $wrapped = MessengerRuntimeException::wrap(new HandlerFailedException(
      new Envelope(new stdClass()),
      [ChecklistNotFoundException::withId(self::ID)],
    ));

    self::assertNull($this->invoke('findInspectionNotFoundException', $wrapped));
  }

  private function invoke(string $finder, Throwable $exception): ?Throwable
  {
    $host = new class () {
      use InspectionExceptionUnwrapperTrait;
    };

    $method = new ReflectionMethod($host, $finder);

    /** @var Throwable|null $result */
    $result = $method->invoke($host, $exception);

    return $result;
  }
}
