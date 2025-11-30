<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Infrastructure\Symfony\Security;

use Auth\Application\Port\Outbound\AccessTokenRepositoryPort;
use Auth\Infrastructure\Symfony\Security\OAuth2Authenticator;
use Auth\Infrastructure\Symfony\Security\SecurityUser;
use Auth\Infrastructure\Symfony\Security\SecurityUserProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

/**
 * Class OAuth2AuthenticatorTest
 *
 * Unit tests for the OAuth2Authenticator.
 *
 * @category Unit Test
 * @package Tests\Unit\Auth\Infrastructure\Symfony\Security
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: OAuth2Authenticator::class)]
final class OAuth2AuthenticatorTest extends TestCase
{
  //#region Properties
  /**
   * Property authenticator
   *
   * OAuth2Authenticator instance.
   *
   * @access private
   *
   * @var OAuth2Authenticator
   */
  private OAuth2Authenticator $authenticator;
  //#endregion

  //#region Methods
  /**
   * Method setUp
   *
   * Sets up the test environment.
   *
   * @access protected
   *
   * @return void
   */
  protected function setUp(): void
  {
    $accessTokenRepository = $this->createMock(AccessTokenRepositoryPort::class);
    $queryBus = $this->createMock(QueryBusPort::class);
    $userProvider = new SecurityUserProvider($queryBus);

    $this->authenticator = new OAuth2Authenticator(
      $accessTokenRepository,
      $userProvider
    );
  }

  /**
   * Method testSupportsReturnsTrueForBearerToken
   *
   * Tests that supports returns true for Bearer token.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testSupportsReturnsTrueForBearerToken(): void
  {
    $request = new Request();
    $request->headers->set('Authorization', 'Bearer some-token');

    $this->assertTrue($this->authenticator->supports($request));
  }

  /**
   * Method testSupportsReturnsFalseWithoutAuthorizationHeader
   *
   * Tests that supports returns false without Authorization header.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testSupportsReturnsFalseWithoutAuthorizationHeader(): void
  {
    $request = new Request();

    $this->assertFalse($this->authenticator->supports($request));
  }

  /**
   * Method testSupportsReturnsFalseForNonBearerToken
   *
   * Tests that supports returns false for non-Bearer token.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testSupportsReturnsFalseForNonBearerToken(): void
  {
    $request = new Request();
    $request->headers->set('Authorization', 'Basic dXNlcjpwYXNz');

    $this->assertFalse($this->authenticator->supports($request));
  }

  /**
   * Method testOnAuthenticationSuccessReturnsNull
   *
   * Tests that onAuthenticationSuccess returns null.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testOnAuthenticationSuccessReturnsNull(): void
  {
    $request = new Request();
    $token = $this->createMock(TokenInterface::class);

    $result = $this->authenticator->onAuthenticationSuccess($request, $token, 'api');

    $this->assertNull($result);
  }

  /**
   * Method testOnAuthenticationFailureReturnsUnauthorizedResponse
   *
   * Tests that onAuthenticationFailure returns 401 response.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testOnAuthenticationFailureReturnsUnauthorizedResponse(): void
  {
    $request = new Request();
    $exception = new AuthenticationException('Invalid token');

    $response = $this->authenticator->onAuthenticationFailure($request, $exception);

    $this->assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    $this->assertStringContainsString('invalid_token', $response->getContent() ?: '');
    $this->assertTrue($response->headers->has('WWW-Authenticate'));
  }

  /**
   * Method testOnAuthenticationFailureIncludesWwwAuthenticateHeader
   *
   * Tests that onAuthenticationFailure includes WWW-Authenticate header.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testOnAuthenticationFailureIncludesWwwAuthenticateHeader(): void
  {
    $request = new Request();
    $exception = new AuthenticationException('Token expired');

    $response = $this->authenticator->onAuthenticationFailure($request, $exception);

    $wwwAuthenticate = $response->headers->get('WWW-Authenticate');
    $this->assertNotNull($wwwAuthenticate);
    $this->assertStringContainsString('Bearer', $wwwAuthenticate);
    $this->assertStringContainsString('error="invalid_token"', $wwwAuthenticate);
  }
  //#endregion
}
