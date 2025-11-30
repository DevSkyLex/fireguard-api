<?php

declare(strict_types=1);

namespace Auth\Application\Port\Outbound;

use Auth\Domain\Model\AuthCode;

/**
 * Interface AuthCodeRepositoryPort
 *
 * Port for Auth Code repository.
 *
 * @category Port
 * @package Auth\Application\Port\Outbound
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface AuthCodeRepositoryPort
{
  /**
   * Method save
   * 
   * Saves an auth code.
   * 
   * @access public
   * @since 1.0.0
   * 
   * @param AuthCode $authCode The auth code to save.
   * 
   * @return void
   */
  public function save(AuthCode $authCode): void;

  /**
   * Method find
   * 
   * Finds an auth code by identifier.
   * 
   * @access public
   * @since 1.0.0
   * 
   * @param string $identifier The auth code identifier.
   * 
   * @return AuthCode|null The auth code or null if not found.
   */
  public function find(string $identifier): ?AuthCode;
}
