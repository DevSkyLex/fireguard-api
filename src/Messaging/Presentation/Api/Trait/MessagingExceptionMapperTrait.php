<?php

declare(strict_types=1);

namespace Messaging\Presentation\Api\Trait;

use Throwable;

/**
 * Trait MessagingExceptionMapperTrait.
 *
 * Maps Messaging domain exceptions (and the cross-module
 * `OrganizationAccessDeniedException` raised by
 * `OrganizationAuthorizationPort::assertGrantedPermissions()`) to their HTTP
 * counterparts, walking the exception chain (mirrors
 * `MaintenanceExceptionMapperTrait`) so a command-bus-wrapped exception is
 * unwrapped transparently.
 *
 * @category Trait
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
trait MessagingExceptionMapperTrait
{
  /**
   * Method mapMessagingException.
   *
   * @since 1.0.0
   *
   * @param Throwable $exception the exception value
   *
   * @return Throwable the mapped exception
   */
  private function mapMessagingException(Throwable $exception): Throwable
  {
    return new \Messaging\Presentation\Api\Service\MessagingExceptionMapper()->map($exception);
  }
}
