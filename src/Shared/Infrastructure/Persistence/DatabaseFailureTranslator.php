<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Persistence;

use Doctrine\DBAL\Exception\{ForeignKeyConstraintViolationException as DbalForeignKeyConstraintViolationException, UniqueConstraintViolationException as DbalUniqueConstraintViolationException};
use Shared\Application\Contract\Persistence\{ForeignKeyConstraintViolationException, UniqueConstraintViolationException};
use Throwable;

/**
 * Class DatabaseFailureTranslator
 *
 * Translates driver constraint failures at the Shared transaction boundary without altering unrelated failures.
 *
 * @category Adapter
 */
final readonly class DatabaseFailureTranslator
{
  // #region Methods
  /**
   * Method translate
   *
   * Preserves the complete diagnostic chain behind a pure Application exception.
   *
   * @access public
   *
   * @param Throwable $failure the adapter failure
   *
   * @return Throwable the pure constraint failure or the unchanged original
   */
  public static function translate(Throwable $failure): Throwable
  {
    return match (true) {
      $failure instanceof DbalUniqueConstraintViolationException => new UniqueConstraintViolationException($failure),
      $failure instanceof DbalForeignKeyConstraintViolationException => new ForeignKeyConstraintViolationException($failure),
      default => $failure,
    };
  }
  // #endregion
}
