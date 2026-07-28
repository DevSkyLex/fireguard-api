<?php

declare(strict_types=1);

namespace Tests\Support\Doctrine;

use Doctrine\DBAL\Driver\Exception as DriverException;
use RuntimeException;

/**
 * Test double FakeDriverException.
 *
 * The innermost driver error that DBAL's typed exceptions wrap. Building one
 * by hand lets a test construct a real
 * {@see \Doctrine\DBAL\Exception\UniqueConstraintViolationException} without
 * making PostgreSQL actually reject a statement — which, inside the
 * transaction the integration suite runs in, would abort every later query.
 *
 * @category Test Doubles
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class FakeDriverException extends RuntimeException implements DriverException
{
  // #region Methods
  /**
   * Method getSQLState.
   *
   * @since 1.0.0
   *
   * @return string the PostgreSQL unique-violation SQLSTATE
   */
  public function getSQLState(): string
  {
    return '23505';
  }
  // #endregion
}
