<?php

declare(strict_types=1);

namespace Auth\Domain\Model;

use Auth\Domain\ValueObject\ConsentId;
use DateTimeImmutable;
use Shared\Domain\ValueObject\Scopes;

/**
 * Model Consent
 * @final
 *
 * Represents user consent for OAuth2 authorization.
 * Tracks which scopes a user has granted to which client.
 *
 * @category Model
 * @package Auth\Domain\Model
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class Consent
{
  //#region Constructor
  /**
   * Constructor
   *
   * @access private
   * @since 1.0.0
   *
   * @param ConsentId $id The consent ID.
   * @param string $userId The user ID.
   * @param string $clientId The client ID.
   * @param Scopes $scopes The granted scopes.
   * @param DateTimeImmutable $grantedAt When consent was granted.
   * @param DateTimeImmutable|null $revokedAt When consent was revoked.
   */
  private function __construct(
    private ConsentId $id,
    private string $userId,
    private string $clientId,
    private Scopes $scopes,
    private DateTimeImmutable $grantedAt,
    private ?DateTimeImmutable $revokedAt = null,
  ) {
  }
  //#endregion

  //#region Methods
  /**
   * Method grant
   * @static
   *
   * Creates a new consent grant.
   *
   * @access public
   * @since 1.0.0
   *
   * @param ConsentId $id The consent ID.
   * @param string $userId The user ID.
   * @param string $clientId The client ID.
   * @param Scopes $scopes The scopes to grant.
   *
   * @return self The new Consent instance.
   */
  public static function grant(
    ConsentId $id,
    string $userId,
    string $clientId,
    Scopes $scopes,
  ): self {
    return new self(
      id: $id,
      userId: $userId,
      clientId: $clientId,
      scopes: $scopes,
      grantedAt: new DateTimeImmutable(),
    );
  }

  /**
   * Method id
   *
   * Returns the consent ID.
   *
   * @access public
   * @since 1.0.0
   *
   * @return ConsentId The consent ID.
   */
  public function id(): ConsentId
  {
    return $this->id;
  }

  /**
   * Method userId
   *
   * Returns the user ID.
   *
   * @access public
   * @since 1.0.0
   *
   * @return string The user ID.
   */
  public function userId(): string
  {
    return $this->userId;
  }

  /**
   * Method clientId
   *
   * Returns the client ID.
   *
   * @access public
   * @since 1.0.0
   *
   * @return string The client ID.
   */
  public function clientId(): string
  {
    return $this->clientId;
  }

  /**
   * Method scopes
   *
   * Returns the granted scopes.
   *
   * @access public
   * @since 1.0.0
   *
   * @return Scopes The scopes.
   */
  public function scopes(): Scopes
  {
    return $this->scopes;
  }

  /**
   * Method grantedAt
   *
   * Returns the grant timestamp.
   *
   * @access public
   * @since 1.0.0
   *
   * @return DateTimeImmutable The grant timestamp.
   */
  public function grantedAt(): DateTimeImmutable
  {
    return $this->grantedAt;
  }

  /**
   * Method isRevoked
   *
   * Returns whether the consent is revoked.
   *
   * @access public
   * @since 1.0.0
   *
   * @return bool True if revoked.
   */
  public function isRevoked(): bool
  {
    return $this->revokedAt !== null;
  }

  /**
   * Method revokedAt
   *
   * Returns the revocation timestamp.
   *
   * @access public
   * @since 1.0.0
   *
   * @return DateTimeImmutable|null The revocation timestamp.
   */
  public function revokedAt(): ?DateTimeImmutable
  {
    return $this->revokedAt;
  }

  /**
   * Method revoke
   *
   * Revokes the consent.
   *
   * @access public
   * @since 1.0.0
   *
   * @return void
   */
  public function revoke(): void
  {
    if (!$this->isRevoked()) {
      $this->revokedAt = new DateTimeImmutable();
    }
  }

  /**
   * Method updateScopes
   *
   * Updates the granted scopes.
   *
   * @access public
   * @since 1.0.0
   *
   * @param Scopes $scopes The new scopes.
   *
   * @return void
   */
  public function updateScopes(Scopes $scopes): void
  {
    $this->scopes = $scopes;
  }

  /**
   * Method hasScope
   *
   * Checks if consent includes a specific scope.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $scope The scope to check.
   *
   * @return bool True if scope is granted.
   */
  public function hasScope(string $scope): bool
  {
    return in_array($scope, $this->scopes->toArray(), strict: true);
  }

  /**
   * Method containsAllScopes
   *
   * Checks if consent includes all requested scopes.
   *
   * @access public
   * @since 1.0.0
   *
   * @param Scopes $requestedScopes The scopes to check.
   *
   * @return bool True if all scopes are granted.
   */
  public function containsAllScopes(Scopes $requestedScopes): bool
  {
    $grantedScopes = $this->scopes->toArray();
    foreach ($requestedScopes->toArray() as $scope) {
      if (!in_array($scope, $grantedScopes, strict: true)) {
        return false;
      }
    }
    return true;
  }
  //#endregion
}
