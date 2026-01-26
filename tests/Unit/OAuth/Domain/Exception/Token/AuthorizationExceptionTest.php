<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Domain\Exception\Token;

use OAuth\Domain\Exception\Token\AuthorizationException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Test AuthorizationExceptionTest.
 *
 * @category Exception Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(AuthorizationException::class)]
final class AuthorizationExceptionTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testInvalidRequestSetsErrorType(): void
  {
    $exception = AuthorizationException::invalidRequest('Bad request');

    $this->assertSame('invalid_request', $exception->errorType());
    $this->assertSame('Bad request', $exception->getMessage());
  }

  #[Test]
  public function testServerErrorSetsErrorType(): void
  {
    $exception = AuthorizationException::serverError('Server error');

    $this->assertSame('server_error', $exception->errorType());
  }

  #[Test]
  public function testTemporarilyUnavailableSetsErrorType(): void
  {
    $exception = AuthorizationException::temporarilyUnavailable('Unavailable');

    $this->assertSame('temporarily_unavailable', $exception->errorType());
    $this->assertSame(503, $exception->getCode());
  }

  #[Test]
  public function testInvalidClientSetsErrorType(): void
  {
    $exception = AuthorizationException::invalidClient('Bad client');

    $this->assertSame('invalid_client', $exception->errorType());
    $this->assertSame(401, $exception->getCode());
  }

  #[Test]
  public function testInvalidGrantSetsErrorType(): void
  {
    $exception = AuthorizationException::invalidGrant('Bad grant');

    $this->assertSame('invalid_grant', $exception->errorType());
    $this->assertSame(400, $exception->getCode());
  }

  #[Test]
  public function testUnauthorizedClientSetsErrorType(): void
  {
    $exception = AuthorizationException::unauthorizedClient('Unauthorized');

    $this->assertSame('unauthorized_client', $exception->errorType());
    $this->assertSame(400, $exception->getCode());
  }

  #[Test]
  public function testUnsupportedGrantTypeSetsErrorType(): void
  {
    $exception = AuthorizationException::unsupportedGrantType('Unsupported');

    $this->assertSame('unsupported_grant_type', $exception->errorType());
    $this->assertSame(400, $exception->getCode());
  }

  #[Test]
  public function testInvalidScopeSetsErrorType(): void
  {
    $exception = AuthorizationException::invalidScope('Invalid scope');

    $this->assertSame('invalid_scope', $exception->errorType());
    $this->assertSame(400, $exception->getCode());
  }

  #[Test]
  public function testAccessDeniedSetsErrorTypeAndPrevious(): void
  {
    $previous = new RuntimeException('previous');
    $exception = AuthorizationException::accessDenied('Denied', $previous);

    $this->assertSame('access_denied', $exception->errorType());
    $this->assertSame(403, $exception->getCode());
    $this->assertSame($previous, $exception->getPrevious());
  }
  // #endregion
}
