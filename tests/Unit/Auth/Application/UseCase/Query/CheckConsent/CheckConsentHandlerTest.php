<?php

declare(strict_types=1);

namespace Tests\Auth\Application\UseCase\Query\CheckConsent;

use OAuth\Application\Port\Outbound\ConsentRepositoryPort;
use OAuth\Application\UseCase\Query\CheckConsent\CheckConsentHandler;
use OAuth\Application\UseCase\Query\CheckConsent\CheckConsentQuery;
use OAuth\Application\UseCase\Query\CheckConsent\CheckConsentResult;
use OAuth\Domain\Model\Consent;
use OAuth\Domain\ValueObject\ConsentId;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use OAuth\Domain\ValueObject\Scope;
use OAuth\Domain\ValueObject\Scopes;

/**
 * Test CheckConsentHandlerTest
 * @final
 *
 * Test class for CheckConsentHandler.
 *
 * @category Handler Tests
 * @package Tests\Auth\Application\UseCase\Query\CheckConsent
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: CheckConsentHandler::class)]
final class CheckConsentHandlerTest extends TestCase
{
  //#region Methods
  /**
   * Method testInvokeReturnsFalseWhenNoConsent
   *
   * Test that __invoke returns false when no consent exists.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testInvokeReturnsFalseWhenNoConsent(): void
  {
    $repository = $this->createMock(ConsentRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findByUserAndClient')
      ->willReturn(null);

    $query = new CheckConsentQuery(
      userId: 'user-123',
      clientId: 'client-456',
      requestedScopes: ['OPENID', 'PROFILE'],
    );

    $handler = new CheckConsentHandler(consentRepository: $repository);
    $result = $handler->__invoke(query: $query);

    self::assertInstanceOf(CheckConsentResult::class, $result);
    self::assertFalse($result->hasConsent);
    self::assertTrue($result->requiresConsentScreen);
    self::assertEmpty($result->grantedScopes);
    self::assertEquals(['OPENID', 'PROFILE'], $result->missingScopes);
  }

  /**
   * Method testInvokeReturnsTrueWhenAllScopesGranted
   *
   * Test that __invoke returns true when all scopes are granted.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testInvokeReturnsTrueWhenAllScopesGranted(): void
  {
    $consent = Consent::grant(
      id: new ConsentId('123e4567-e89b-12d3-a456-426614174000'),
      userId: 'user-123',
      clientId: 'client-456',
      scopes: new Scopes(Scope::OPENID, Scope::PROFILE, Scope::EMAIL),
    );

    $repository = $this->createMock(ConsentRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findByUserAndClient')
      ->willReturn($consent);

    $query = new CheckConsentQuery(
      userId: 'user-123',
      clientId: 'client-456',
      requestedScopes: ['OPENID', 'PROFILE'],
    );

    $handler = new CheckConsentHandler(consentRepository: $repository);
    $result = $handler->__invoke(query: $query);

    self::assertTrue($result->hasConsent);
    self::assertFalse($result->requiresConsentScreen);
    self::assertEmpty($result->missingScopes);
  }

  /**
   * Method testInvokeIdentifiesMissingScopes
   *
   * Test that __invoke identifies missing scopes.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testInvokeIdentifiesMissingScopes(): void
  {
    $consent = Consent::grant(
      id: new ConsentId('123e4567-e89b-12d3-a456-426614174000'),
      userId: 'user-123',
      clientId: 'client-456',
      scopes: new Scopes(Scope::OPENID),
    );

    $repository = $this->createMock(ConsentRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findByUserAndClient')
      ->willReturn($consent);

    $query = new CheckConsentQuery(
      userId: 'user-123',
      clientId: 'client-456',
      requestedScopes: ['OPENID', 'PROFILE', 'EMAIL'],
    );

    $handler = new CheckConsentHandler(consentRepository: $repository);
    $result = $handler->__invoke(query: $query);

    self::assertTrue($result->hasConsent);
    self::assertTrue($result->requiresConsentScreen);
    self::assertContains('PROFILE', $result->missingScopes);
    self::assertContains('EMAIL', $result->missingScopes);
  }
  //#endregion
}
