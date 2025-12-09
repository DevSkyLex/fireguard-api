<?php

declare(strict_types=1);

namespace Tests\Auth\Domain\Model;

use Auth\Domain\Model\Consent;
use Auth\Domain\ValueObject\ConsentId;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Shared\Domain\ValueObject\Scopes;
use Shared\Domain\ValueObject\Scope;

/**
 * Test ConsentTest
 * @final
 *
 * Test class for the Consent domain model.
 *
 * @category Model Tests
 * @package Tests\Auth\Domain\Model
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: Consent::class)]
final class ConsentTest extends TestCase
{
  //#region Methods
  /**
   * Method testGrantCreatesNewConsent
   *
   * Test that grant creates a new consent.
   *
   * @access public
   *
   * @return void
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
   * Method testRevokeMarksConsentAsRevoked
   *
   * Test that revoke marks consent as revoked.
   *
   * @access public
   *
   * @return void
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
   * Method testHasScopeChecksIndividualScope
   *
   * Test that hasScope checks individual scopes.
   *
   * @access public
   *
   * @return void
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
   * Method testContainsAllScopesChecksMultipleScopes
   *
   * Test that containsAllScopes checks multiple scopes.
   *
   * @access public
   *
   * @return void
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
   * Method testUpdateScopesModifiesGrantedScopes
   *
   * Test that updateScopes modifies granted scopes.
   *
   * @access public
   *
   * @return void
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

  /**
   * Method createTestConsent
   *
   * Helper to create a test consent.
   *
   * @access private
   *
   * @return Consent
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
  //#endregion
}
