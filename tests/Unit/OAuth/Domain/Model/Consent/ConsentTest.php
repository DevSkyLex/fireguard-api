<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Domain\Model\Consent;

use DateTimeImmutable;
use OAuth\Domain\Model\Consent\Consent;
use OAuth\Domain\ValueObject\Consent\ConsentId;
use OAuth\Domain\ValueObject\Scope\{Scope, Scopes};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test ConsentTest.
 *
 * @category Model Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: Consent::class)]
final class ConsentTest extends TestCase
{
  // #region Methods
  /**
   * Method testGrantCreatesNewConsent.
   *
   * Test that grant creates a new consent.
   */
  #[Test]
  public function testGrantCreatesNewConsent(): void
  {
    $consentId = new ConsentId(value: '123e4567-e89b-12d3-a456-426614174000');
    $userId = 'user-123';
    $clientId = 'client-456';
    $scopes = new Scopes(Scope::OPENID, Scope::PROFILE);

    $consent = Consent::grant(
      id: $consentId,
      userId: $userId,
      clientId: $clientId,
      scopes: $scopes,
    );

    self::assertEquals($consentId, $consent->id());
    self::assertEquals($userId, $consent->userId());
    self::assertEquals($clientId, $consent->clientId());
    self::assertFalse($consent->isRevoked());
  }

  /**
   * Method testRevokeMarksConsentAsRevoked.
   *
   * Test that revoke marks consent as revoked.
   */
  #[Test]
  public function testRevokeMarksConsentAsRevoked(): void
  {
    $consent = $this->createTestConsent();

    self::assertFalse($consent->isRevoked());

    $consent->revoke();

    self::assertTrue($consent->isRevoked());
    self::assertNotNull($consent->revokedAt());
  }

  /**
   * Method testHasScopeChecksIndividualScope.
   *
   * Test that hasScope checks individual scopes.
   */
  #[Test]
  public function testHasScopeChecksIndividualScope(): void
  {
    $consent = $this->createTestConsent();

    self::assertTrue($consent->hasScope(scope: 'OPENID'));
    self::assertTrue($consent->hasScope(scope: 'PROFILE'));
    self::assertFalse($consent->hasScope(scope: 'ADMIN'));
  }

  /**
   * Method testContainsAllScopesChecksMultipleScopes.
   *
   * Test that containsAllScopes checks multiple scopes.
   */
  #[Test]
  public function testContainsAllScopesChecksMultipleScopes(): void
  {
    $consent = $this->createTestConsent();

    $requestedScopes1 = new Scopes(Scope::OPENID);
    $requestedScopes2 = new Scopes(Scope::OPENID, Scope::PROFILE);
    $requestedScopes3 = new Scopes(Scope::OPENID, Scope::ADMIN);

    self::assertTrue($consent->containsAllScopes(requestedScopes: $requestedScopes1));
    self::assertTrue($consent->containsAllScopes(requestedScopes: $requestedScopes2));
    self::assertFalse($consent->containsAllScopes(requestedScopes: $requestedScopes3));
  }

  /**
   * Method testUpdateScopesModifiesGrantedScopes.
   *
   * Test that updateScopes modifies granted scopes.
   */
  #[Test]
  public function testUpdateScopesModifiesGrantedScopes(): void
  {
    $consent = $this->createTestConsent();

    self::assertFalse($consent->hasScope(scope: 'EMAIL'));

    $newScopes = new Scopes(Scope::OPENID, Scope::EMAIL);
    $consent->updateScopes(scopes: $newScopes);

    self::assertTrue($consent->hasScope(scope: 'EMAIL'));
    self::assertFalse($consent->hasScope(scope: 'PROFILE'));
  }

  #[Test]
  public function testScopesAndGrantedAtExposeWhatWasGranted(): void
  {
    $consent = $this->createTestConsent();

    self::assertSame(['OPENID', 'PROFILE'], $consent->scopes()->toArray());
    self::assertEqualsWithDelta(
      new DateTimeImmutable()->getTimestamp(),
      $consent->grantedAt()->getTimestamp(),
      5,
      'grantedAt is stamped when consent is granted.',
    );
  }

  /**
   * Method createTestConsent.
   *
   * Helper to create a test consent.
   */
  private function createTestConsent(): Consent
  {
    return Consent::grant(
      id: new ConsentId(value: '123e4567-e89b-12d3-a456-426614174000'),
      userId: 'user-123',
      clientId: 'client-456',
      scopes: new Scopes(Scope::OPENID, Scope::PROFILE),
    );
  }
  // #endregion
}
