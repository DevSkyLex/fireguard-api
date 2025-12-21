<?php

declare(strict_types=1);

namespace OAuth\Domain\Model;

use DateTimeImmutable;
use OAuth\Domain\ValueObject\ConsentId;
use OAuth\Domain\ValueObject\Scopes;

use function in_array;

/**
 * Model Consent.
 *
 * @category Model
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class Consent
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * Consent class.
   *
   * @since 1.0.0
   *
   * @param ConsentId              $id        the consent ID
   * @param string                 $userId    the user ID
   * @param string                 $clientId  the client ID
   * @param Scopes                 $scopes    the granted scopes
   * @param DateTimeImmutable      $grantedAt when consent was granted
   * @param DateTimeImmutable|null $revokedAt when consent was revoked
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
  // #endregion

  // #region Methods
  /**
   * Method grant.
   *
   * @static
   *
   * Creates a new consent grant.
   *
   * @since 1.0.0
   *
   * @param ConsentId $id       the consent ID
   * @param string    $userId   the user ID
   * @param string    $clientId the client ID
   * @param Scopes    $scopes   the scopes to grant
   *
   * @return self the new Consent instance
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
   * Method id.
   *
   * Returns the consent ID.
   *
   * @since 1.0.0
   *
   * @return ConsentId the consent ID
   */
  public function id(): ConsentId
  {
    return $this->id;
  }

  /**
   * Method userId.
   *
   * Returns the user ID.
   *
   * @since 1.0.0
   *
   * @return string the user ID
   */
  public function userId(): string
  {
    return $this->userId;
  }

  /**
   * Method clientId.
   *
   * Returns the client ID.
   *
   * @since 1.0.0
   *
   * @return string the client ID
   */
  public function clientId(): string
  {
    return $this->clientId;
  }

  /**
   * Method scopes.
   *
   * Returns the granted scopes.
   *
   * @since 1.0.0
   *
   * @return Scopes the scopes
   */
  public function scopes(): Scopes
  {
    return $this->scopes;
  }

  /**
   * Method grantedAt.
   *
   * Returns the grant timestamp.
   *
   * @since 1.0.0
   *
   * @return DateTimeImmutable the grant timestamp
   */
  public function grantedAt(): DateTimeImmutable
  {
    return $this->grantedAt;
  }

  /**
   * Method isRevoked.
   *
   * Returns whether the consent is revoked.
   *
   * @since 1.0.0
   *
   * @return bool true if revoked
   */
  public function isRevoked(): bool
  {
    return null !== $this->revokedAt;
  }

  /**
   * Method revokedAt.
   *
   * Returns the revocation timestamp.
   *
   * @since 1.0.0
   *
   * @return DateTimeImmutable|null the revocation timestamp
   */
  public function revokedAt(): ?DateTimeImmutable
  {
    return $this->revokedAt;
  }

  /**
   * Method revoke.
   *
   * Revokes the consent.
   *
   * @since 1.0.0
   */
  public function revoke(): void
  {
    if (!$this->isRevoked()) {
      $this->revokedAt = new DateTimeImmutable();
    }
  }

  /**
   * Method updateScopes.
   *
   * Updates the granted scopes.
   *
   * @since 1.0.0
   *
   * @param Scopes $scopes the new scopes
   */
  public function updateScopes(Scopes $scopes): void
  {
    $this->scopes = $scopes;
  }

  /**
   * Method hasScope.
   *
   * Checks if consent includes a specific scope.
   *
   * @since 1.0.0
   *
   * @param string $scope the scope to check
   *
   * @return bool true if scope is granted
   */
  public function hasScope(string $scope): bool
  {
    return in_array($scope, $this->scopes->toArray(), strict: true);
  }

  /**
   * Method containsAllScopes.
   *
   * Checks if consent includes all requested scopes.
   *
   * @since 1.0.0
   *
   * @param Scopes $requestedScopes the scopes to check
   *
   * @return bool true if all scopes are granted
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
  // #endregion
}
