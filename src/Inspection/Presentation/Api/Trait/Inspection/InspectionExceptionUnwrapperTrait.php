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

/**
 * Trait InspectionExceptionUnwrapperTrait
 *
 * Extracts known inspection exceptions from direct and Messenger-wrapped failures.
 *
 * @category Trait
 */
trait InspectionExceptionUnwrapperTrait
{
  // #region Methods
  /**
   * Method findInspectionNotFoundException
   *
   * Finds an inspection-not-found exception in the failure chain.
   *
   * @access private
   *
   * @param Throwable $exception failure to inspect
   *
   * @return ?InspectionNotFoundException matching exception, if found
   */
  private function findInspectionNotFoundException(Throwable $exception): ?InspectionNotFoundException
  {
    return $this->findException($exception, InspectionNotFoundException::class);
  }

  /** Method findInspectionAlreadyClosedException
   *
   * Finds an inspection-already-closed exception in the failure chain.
   *
   * @access private
   *
   * @param Throwable $exception failure to inspect
   *
   * @return ?InspectionAlreadyClosedException matching exception, if found
   */
  private function findInspectionAlreadyClosedException(Throwable $exception): ?InspectionAlreadyClosedException
  {
    return $this->findException($exception, InspectionAlreadyClosedException::class);
  }

  /** Method findInspectionAlreadyCancelledException
   *
   * Finds an inspection-already-cancelled exception in the failure chain.
   *
   * @access private
   *
   * @param Throwable $exception failure to inspect
   *
   * @return ?InspectionAlreadyCancelledException matching exception, if found
   */
  private function findInspectionAlreadyCancelledException(Throwable $exception): ?InspectionAlreadyCancelledException
  {
    return $this->findException($exception, InspectionAlreadyCancelledException::class);
  }

  /** Method findInspectionAlreadySubmittedException
   *
   * Finds an inspection-already-submitted exception in the failure chain.
   *
   * @access private
   *
   * @param Throwable $exception failure to inspect
   *
   * @return ?InspectionAlreadySubmittedException matching exception, if found
   */
  private function findInspectionAlreadySubmittedException(Throwable $exception): ?InspectionAlreadySubmittedException
  {
    return $this->findException($exception, InspectionAlreadySubmittedException::class);
  }

  /** Method findInspectionNotSubmittedException
   *
   * Finds an inspection-not-submitted exception in the failure chain.
   *
   * @access private
   *
   * @param Throwable $exception failure to inspect
   *
   * @return ?InspectionNotSubmittedException matching exception, if found
   */
  private function findInspectionNotSubmittedException(Throwable $exception): ?InspectionNotSubmittedException
  {
    return $this->findException($exception, InspectionNotSubmittedException::class);
  }

  /** Method findNonConformityNotFoundException
   *
   * Finds a non-conformity-not-found exception in the failure chain.
   *
   * @access private
   *
   * @param Throwable $exception failure to inspect
   *
   * @return ?NonConformityNotFoundException matching exception, if found
   */
  private function findNonConformityNotFoundException(Throwable $exception): ?NonConformityNotFoundException
  {
    return $this->findException($exception, NonConformityNotFoundException::class);
  }

  /** Method findNonConformityAlreadyResolvedException
   *
   * Finds a non-conformity-already-resolved exception in the failure chain.
   *
   * @access private
   *
   * @param Throwable $exception failure to inspect
   *
   * @return ?NonConformityAlreadyResolvedException matching exception, if found
   */
  private function findNonConformityAlreadyResolvedException(Throwable $exception): ?NonConformityAlreadyResolvedException
  {
    return $this->findException($exception, NonConformityAlreadyResolvedException::class);
  }

  /** Method findChecklistNotFoundException
   *
   * Finds a checklist-not-found exception in the failure chain.
   *
   * @access private
   *
   * @param Throwable $exception failure to inspect
   *
   * @return ?ChecklistNotFoundException matching exception, if found
   */
  private function findChecklistNotFoundException(Throwable $exception): ?ChecklistNotFoundException
  {
    return $this->findException($exception, ChecklistNotFoundException::class);
  }

  /** Method findChecklistArchivedException
   *
   * Finds a checklist-archived exception in the failure chain.
   *
   * @access private
   *
   * @param Throwable $exception failure to inspect
   *
   * @return ?ChecklistArchivedException matching exception, if found
   */
  private function findChecklistArchivedException(Throwable $exception): ?ChecklistArchivedException
  {
    return $this->findException($exception, ChecklistArchivedException::class);
  }

  /** Method findChecklistInUseException
   *
   * Finds a checklist-in-use exception in the failure chain.
   *
   * @access private
   *
   * @param Throwable $exception failure to inspect
   *
   * @return ?ChecklistInUseException matching exception, if found
   */
  private function findChecklistInUseException(Throwable $exception): ?ChecklistInUseException
  {
    return $this->findException($exception, ChecklistInUseException::class);
  }

  /** Method findChecklistReferenceCodeAlreadyExistsException
   *
   * Finds a duplicate checklist reference-code exception in the failure chain.
   *
   * @access private
   *
   * @param Throwable $exception failure to inspect
   *
   * @return ?ChecklistReferenceCodeAlreadyExistsException matching exception, if found
   */
  private function findChecklistReferenceCodeAlreadyExistsException(Throwable $exception): ?ChecklistReferenceCodeAlreadyExistsException
  {
    return $this->findException($exception, ChecklistReferenceCodeAlreadyExistsException::class);
  }

  /** Method findInvalidArgumentException
   *
   * Finds an invalid-argument exception in the failure chain.
   *
   * @access private
   *
   * @param Throwable $exception failure to inspect
   *
   * @return ?InvalidArgumentException matching exception, if found
   */
  private function findInvalidArgumentException(Throwable $exception): ?InvalidArgumentException
  {
    return $this->findException($exception, InvalidArgumentException::class);
  }

  /**
   * Method findException
   *
   * Searches direct, Messenger-wrapped and previous exceptions for a requested class.
   *
   * @access private
   *
   * @template T of Throwable
   *
   * @param Throwable $exception failure chain root
   * @param class-string<T> $class exception class to find
   *
   * @return ?T matching exception, if found
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

    return null !== $previous ? $this->findException($previous, $class) : null;
  }
  // #endregion
}
