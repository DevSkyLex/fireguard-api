<?php

declare(strict_types=1);

namespace Auth\Application\Port\Inbound;

use Auth\Application\Contract\Token\{AuthenticationSession, SignInGrantType};

/**
 * Issues the interactive token pair and mandatory session revocation anchor.
 *
 * @category Port
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface InteractiveSessionIssuerPort
{
  public function issue(string $userId, string $email, ?string $ipAddress, ?string $userAgent, ?string $trustedDeviceToken, bool $rememberMe, SignInGrantType $grantType): AuthenticationSession;
}
