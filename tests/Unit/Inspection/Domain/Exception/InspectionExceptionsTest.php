<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Domain\Exception;

use Inspection\Domain\Exception\{
  ChecklistArchivedException,
  ChecklistInUseException,
  ChecklistNotFoundException,
  ChecklistReferenceCodeAlreadyExistsException,
  InspectionAlreadyCancelledException,
  InspectionAlreadyClosedException,
  InspectionAlreadySubmittedException,
  InspectionAttachmentNotFoundException,
  InspectionNotFoundException,
  InspectionNotSubmittedException,
  NonConformityAlreadyResolvedException,
  NonConformityNotFoundException
};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function str_contains;

/**
 * Test Inspection module domain exceptions.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ChecklistArchivedException::class)]
#[CoversClass(ChecklistInUseException::class)]
#[CoversClass(ChecklistNotFoundException::class)]
#[CoversClass(ChecklistReferenceCodeAlreadyExistsException::class)]
#[CoversClass(InspectionAlreadyCancelledException::class)]
#[CoversClass(InspectionAlreadyClosedException::class)]
#[CoversClass(InspectionAlreadySubmittedException::class)]
#[CoversClass(InspectionAttachmentNotFoundException::class)]
#[CoversClass(InspectionNotFoundException::class)]
#[CoversClass(InspectionNotSubmittedException::class)]
#[CoversClass(NonConformityAlreadyResolvedException::class)]
#[CoversClass(NonConformityNotFoundException::class)]
final class InspectionExceptionsTest extends TestCase
{
  private const string ID = 'abc-123';

  #[Test]
  public function inspectionNotFoundCarriesTheId(): void
  {
    $exception = InspectionNotFoundException::withId(self::ID);

    self::assertInstanceOf(RuntimeException::class, $exception);
    self::assertTrue(str_contains($exception->getMessage(), self::ID));
    self::assertTrue(str_contains($exception->getMessage(), 'not found'));
  }

  #[Test]
  public function inspectionAlreadySubmittedCarriesTheId(): void
  {
    $exception = InspectionAlreadySubmittedException::withId(self::ID);

    self::assertTrue(str_contains($exception->getMessage(), self::ID));
    self::assertTrue(str_contains($exception->getMessage(), 'already submitted'));
  }

  #[Test]
  public function inspectionAlreadyClosedCarriesTheId(): void
  {
    $exception = InspectionAlreadyClosedException::withId(self::ID);

    self::assertTrue(str_contains($exception->getMessage(), self::ID));
    self::assertTrue(str_contains($exception->getMessage(), 'already closed'));
  }

  #[Test]
  public function inspectionAlreadyCancelledCarriesTheId(): void
  {
    $exception = InspectionAlreadyCancelledException::withId(self::ID);

    self::assertTrue(str_contains($exception->getMessage(), self::ID));
    self::assertTrue(str_contains($exception->getMessage(), 'already cancelled'));
  }

  #[Test]
  public function inspectionNotSubmittedCarriesTheId(): void
  {
    $exception = InspectionNotSubmittedException::withId(self::ID);

    self::assertTrue(str_contains($exception->getMessage(), self::ID));
    self::assertTrue(str_contains($exception->getMessage(), 'must be submitted'));
  }

  #[Test]
  public function inspectionAttachmentNotFoundCarriesTheId(): void
  {
    $exception = InspectionAttachmentNotFoundException::withId(self::ID);

    self::assertTrue(str_contains($exception->getMessage(), self::ID));
    self::assertTrue(str_contains($exception->getMessage(), 'not found'));
  }

  #[Test]
  public function nonConformityNotFoundCarriesTheId(): void
  {
    $exception = NonConformityNotFoundException::withId(self::ID);

    self::assertTrue(str_contains($exception->getMessage(), self::ID));
    self::assertTrue(str_contains($exception->getMessage(), 'not found'));
  }

  #[Test]
  public function nonConformityAlreadyResolvedCarriesTheId(): void
  {
    $exception = NonConformityAlreadyResolvedException::withId(self::ID);

    self::assertTrue(str_contains($exception->getMessage(), self::ID));
    self::assertTrue(str_contains($exception->getMessage(), 'already resolved'));
  }

  #[Test]
  public function checklistNotFoundCarriesTheId(): void
  {
    $exception = ChecklistNotFoundException::withId(self::ID);

    self::assertTrue(str_contains($exception->getMessage(), self::ID));
    self::assertTrue(str_contains($exception->getMessage(), 'not found'));
  }

  #[Test]
  public function checklistArchivedCarriesTheId(): void
  {
    $exception = ChecklistArchivedException::withId(self::ID);

    self::assertTrue(str_contains($exception->getMessage(), self::ID));
    self::assertTrue(str_contains($exception->getMessage(), 'already archived'));
  }

  #[Test]
  public function checklistInUseCarriesTheId(): void
  {
    $exception = ChecklistInUseException::withId(self::ID);

    self::assertTrue(str_contains($exception->getMessage(), self::ID));
    self::assertTrue(str_contains($exception->getMessage(), 'existing inspections'));
  }

  #[Test]
  public function checklistReferenceCodeAlreadyExistsCarriesTheCode(): void
  {
    $exception = ChecklistReferenceCodeAlreadyExistsException::withReferenceCode('CHK-1');

    self::assertTrue(str_contains($exception->getMessage(), 'CHK-1'));
    self::assertTrue(str_contains($exception->getMessage(), 'already exists'));
  }
}
