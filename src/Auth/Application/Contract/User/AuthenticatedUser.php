<?php

declare(strict_types=1);

namespace Auth\Application\Contract\User;

/**
 * Contract AuthenticatedUser.
 *
 * Exposes the stable authenticated identity to other modules without coupling
 * their presentation code to Auth's security infrastructure implementation.
 *
 * @category Contract
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface AuthenticatedUser
{
  // #region Methods
  /**
   * Method getId.
   *
   * @since 1.0.0
   *
   * @return string the stable authenticated user identifier
   */
  public function getId(): string;
  // #endregion
}
