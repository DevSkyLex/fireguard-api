<?php

declare(strict_types=1);

namespace Inspection\Presentation\Api\Trait\Inspection;

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
use InvalidArgumentException;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Throwable;

trait InspectionExceptionUnwrapperTrait
{
  private function findInspectionNotFoundException(Throwable $exception): ?InspectionNotFoundException
  {
    return $this->findException($exception, InspectionNotFoundException::class);
  }

  private function findInspectionAlreadyClosedException(Throwable $exception): ?InspectionAlreadyClosedException
  {
    return $this->findException($exception, InspectionAlreadyClosedException::class);
  }

  private function findInspectionAlreadyCancelledException(Throwable $exception): ?InspectionAlreadyCancelledException
  {
    return $this->findException($exception, InspectionAlreadyCancelledException::class);
  }

  private function findInspectionAlreadySubmittedException(Throwable $exception): ?InspectionAlreadySubmittedException
  {
    return $this->findException($exception, InspectionAlreadySubmittedException::class);
  }

  private function findInspectionNotSubmittedException(Throwable $exception): ?InspectionNotSubmittedException
  {
    return $this->findException($exception, InspectionNotSubmittedException::class);
  }

  private function findNonConformityNotFoundException(Throwable $exception): ?NonConformityNotFoundException
  {
    return $this->findException($exception, NonConformityNotFoundException::class);
  }

  private function findNonConformityAlreadyResolvedException(Throwable $exception): ?NonConformityAlreadyResolvedException
  {
    return $this->findException($exception, NonConformityAlreadyResolvedException::class);
  }

  private function findChecklistNotFoundException(Throwable $exception): ?ChecklistNotFoundException
  {
    return $this->findException($exception, ChecklistNotFoundException::class);
  }

  private function findChecklistArchivedException(Throwable $exception): ?ChecklistArchivedException
  {
    return $this->findException($exception, ChecklistArchivedException::class);
  }

  private function findChecklistInUseException(Throwable $exception): ?ChecklistInUseException
  {
    return $this->findException($exception, ChecklistInUseException::class);
  }

  private function findChecklistReferenceCodeAlreadyExistsException(Throwable $exception): ?ChecklistReferenceCodeAlreadyExistsException
  {
    return $this->findException($exception, ChecklistReferenceCodeAlreadyExistsException::class);
  }

  private function findInvalidArgumentException(Throwable $exception): ?InvalidArgumentException
  {
    return $this->findException($exception, InvalidArgumentException::class);
  }

  /**
   * @template T of Throwable
   *
   * @param class-string<T> $class
   *
   * @return ?T
   */
  private function findException(Throwable $exception, string $class): ?Throwable
  {
    if ($exception instanceof $class) {
      return $exception;
    }

    if ($exception instanceof HandlerFailedException) {
      foreach ($exception->getWrappedExceptions() as $wrapped) {
        if ($wrapped instanceof $class) {
          return $wrapped;
        }
      }
    }

    $previous = $exception->getPrevious();
    if (null !== $previous) {
      return $this->findException($previous, $class);
    }

    return null;
  }
}
