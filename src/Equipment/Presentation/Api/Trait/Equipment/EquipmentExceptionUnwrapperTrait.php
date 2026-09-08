<?php

declare(strict_types=1);

namespace Equipment\Presentation\Api\Trait\Equipment;

use Equipment\Domain\Exception\{
  AttachmentNotFoundException,
  EquipmentAccessDeniedException,
  EquipmentAlreadyDecommissionedException,
  EquipmentExportTooLargeException,
  EquipmentLabelExportTooLargeException,
  EquipmentNotFoundException,
  EquipmentSerialNumberAlreadyExistsException,
  TagNotFoundException
};
use InvalidArgumentException;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Throwable;

/**
 * Trait EquipmentExceptionUnwrapperTrait.
 *
 * Provides helper methods to unwrap domain exceptions from a Symfony
 * Messenger HandlerFailedException or from arbitrary Throwable chains.
 *
 * @category Trait
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
trait EquipmentExceptionUnwrapperTrait
{
  // #region Methods
  /**
   * Method findEquipmentNotFoundException.
   *
   * @since 1.0.0
   */
  private function findEquipmentNotFoundException(Throwable $exception): ?EquipmentNotFoundException
  {
    $current = $exception;

    while (null !== $current) {
      if ($current instanceof EquipmentNotFoundException) {
        return $current;
      }

      if ($current instanceof HandlerFailedException) {
        foreach ($current->getWrappedExceptions() as $wrappedException) {
          if ($wrappedException instanceof EquipmentNotFoundException) {
            return $wrappedException;
          }
        }
      }

      $current = $current->getPrevious();
    }

    return null;
  }

  /**
   * Method findEquipmentAlreadyDecommissionedException.
   *
   * @since 1.0.0
   */
  private function findEquipmentAlreadyDecommissionedException(Throwable $exception): ?EquipmentAlreadyDecommissionedException
  {
    $current = $exception;

    while (null !== $current) {
      if ($current instanceof EquipmentAlreadyDecommissionedException) {
        return $current;
      }

      if ($current instanceof HandlerFailedException) {
        foreach ($current->getWrappedExceptions() as $wrappedException) {
          if ($wrappedException instanceof EquipmentAlreadyDecommissionedException) {
            return $wrappedException;
          }
        }
      }

      $current = $current->getPrevious();
    }

    return null;
  }

  /**
   * Method findEquipmentSerialNumberAlreadyExistsException.
   *
   * @since 1.0.0
   */
  private function findEquipmentSerialNumberAlreadyExistsException(Throwable $exception): ?EquipmentSerialNumberAlreadyExistsException
  {
    $current = $exception;

    while (null !== $current) {
      if ($current instanceof EquipmentSerialNumberAlreadyExistsException) {
        return $current;
      }

      if ($current instanceof HandlerFailedException) {
        foreach ($current->getWrappedExceptions() as $wrappedException) {
          if ($wrappedException instanceof EquipmentSerialNumberAlreadyExistsException) {
            return $wrappedException;
          }
        }
      }

      $current = $current->getPrevious();
    }

    return null;
  }

  /**
   * Method findAttachmentNotFoundException.
   *
   * @since 1.0.0
   */
  private function findAttachmentNotFoundException(Throwable $exception): ?AttachmentNotFoundException
  {
    $current = $exception;

    while (null !== $current) {
      if ($current instanceof AttachmentNotFoundException) {
        return $current;
      }

      if ($current instanceof HandlerFailedException) {
        foreach ($current->getWrappedExceptions() as $wrappedException) {
          if ($wrappedException instanceof AttachmentNotFoundException) {
            return $wrappedException;
          }
        }
      }

      $current = $current->getPrevious();
    }

    return null;
  }

  /**
   * Method findTagNotFoundException.
   *
   * @since 1.0.0
   */
  private function findTagNotFoundException(Throwable $exception): ?TagNotFoundException
  {
    $current = $exception;

    while (null !== $current) {
      if ($current instanceof TagNotFoundException) {
        return $current;
      }

      if ($current instanceof HandlerFailedException) {
        foreach ($current->getWrappedExceptions() as $wrappedException) {
          if ($wrappedException instanceof TagNotFoundException) {
            return $wrappedException;
          }
        }
      }

      $current = $current->getPrevious();
    }

    return null;
  }

  /**
   * Method findEquipmentAccessDeniedException.
   *
   * @since 1.0.0
   */
  private function findEquipmentAccessDeniedException(Throwable $exception): ?EquipmentAccessDeniedException
  {
    $current = $exception;

    while (null !== $current) {
      if ($current instanceof EquipmentAccessDeniedException) {
        return $current;
      }

      if ($current instanceof HandlerFailedException) {
        foreach ($current->getWrappedExceptions() as $wrappedException) {
          if ($wrappedException instanceof EquipmentAccessDeniedException) {
            return $wrappedException;
          }
        }
      }

      $current = $current->getPrevious();
    }

    return null;
  }

  /**
   * Method findEquipmentExportTooLargeException.
   *
   * @since 1.0.0
   */
  private function findEquipmentExportTooLargeException(Throwable $exception): ?EquipmentExportTooLargeException
  {
    $current = $exception;

    while (null !== $current) {
      if ($current instanceof EquipmentExportTooLargeException) {
        return $current;
      }

      if ($current instanceof HandlerFailedException) {
        foreach ($current->getWrappedExceptions() as $wrappedException) {
          if ($wrappedException instanceof EquipmentExportTooLargeException) {
            return $wrappedException;
          }
        }
      }

      $current = $current->getPrevious();
    }

    return null;
  }

  /**
   * Method findEquipmentLabelExportTooLargeException.
   *
   * @since 1.0.0
   */
  private function findEquipmentLabelExportTooLargeException(Throwable $exception): ?EquipmentLabelExportTooLargeException
  {
    $current = $exception;

    while (null !== $current) {
      if ($current instanceof EquipmentLabelExportTooLargeException) {
        return $current;
      }

      if ($current instanceof HandlerFailedException) {
        foreach ($current->getWrappedExceptions() as $wrappedException) {
          if ($wrappedException instanceof EquipmentLabelExportTooLargeException) {
            return $wrappedException;
          }
        }
      }

      $current = $current->getPrevious();
    }

    return null;
  }

  /**
   * Method findInvalidArgumentException.
   *
   * @since 1.0.0
   */
  private function findInvalidArgumentException(Throwable $exception): ?InvalidArgumentException
  {
    $current = $exception;

    while (null !== $current) {
      if ($current instanceof InvalidArgumentException) {
        return $current;
      }

      if ($current instanceof HandlerFailedException) {
        foreach ($current->getWrappedExceptions() as $wrappedException) {
          if ($wrappedException instanceof InvalidArgumentException) {
            return $wrappedException;
          }
        }
      }

      $current = $current->getPrevious();
    }

    return null;
  }
  // #endregion
}
