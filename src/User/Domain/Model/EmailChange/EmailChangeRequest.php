<?php

declare(strict_types=1);

namespace User\Domain\Model\EmailChange;

use DateTimeImmutable;
use Shared\Domain\ValueObject\Email;
use User\Domain\Exception\{EmailChangeNotAllowedException, EmailChangeRequestNotFoundException};
use User\Domain\ValueObject\UserId;

use function sprintf;

/**
 * Aggregate EmailChangeRequest.
 *
 * A pending request to change a user's sign-in email address. Created
 * when the authenticated user proves their password, confirmed when the
 * CSPRNG token emailed to the new address is presented back within the
 * TTL. Only the SHA-256 hash of the token is ever stored; a user has at
 * most one pending request — a new request replaces the previous one.
 *
 * @category Model
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class EmailChangeRequest
{
  // #region Constants
  /**
   * Constant TTL_SECONDS.
   *
   * How long a confirmation token stays valid after the request.
   *
   * @since 1.0.0
   *
   * @var int
   */
  public const int TTL_SECONDS = 3600;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * Initialize the EmailChangeRequest model.
   *
   * @since 1.0.0
   *
   * @param string $id the request identifier (UUID)
   * @param UserId $userId the user whose email is being changed
   * @param Email $currentEmail the current sign-in email address
   * @param Email $newEmail the requested new email address
   * @param string $tokenHash the SHA-256 hash of the confirmation token
   * @param DateTimeImmutable $requestedAt when the request was created
   * @param DateTimeImmutable $expiresAt when the confirmation token expires
   * @param DateTimeImmutable|null $confirmedAt when the request was confirmed, if it was
   */
  private function __construct(
    private string $id,
    private UserId $userId,
    private Email $currentEmail,
    private Email $newEmail,
    private string $tokenHash,
    private DateTimeImmutable $requestedAt,
    private DateTimeImmutable $expiresAt,
    private ?DateTimeImmutable $confirmedAt,
  ) {
  }
  // #endregion

  // #region Static Constructors
  /**
   * Method request.
   *
   * @static
   *
   * Creates a new pending email change request.
   *
   * @since 1.0.0
   *
   * @param string $id the request identifier (UUID)
   * @param UserId $userId the user whose email is being changed
   * @param Email $currentEmail the current sign-in email address
   * @param Email $newEmail the requested new email address
   * @param string $tokenHash the SHA-256 hash of the confirmation token
   * @param DateTimeImmutable $requestedAt when the request is created
   *
   * @throws EmailChangeNotAllowedException when the new address equals the current one
   *
   * @return self the pending request
   */
  public static function request(
    string $id,
    UserId $userId,
    Email $currentEmail,
    Email $newEmail,
    string $tokenHash,
    DateTimeImmutable $requestedAt,
  ): self {
    if ($newEmail->value === $currentEmail->value) {
      throw EmailChangeNotAllowedException::emailNotAvailable();
    }

    return new self(
      id: $id,
      userId: $userId,
      currentEmail: $currentEmail,
      newEmail: $newEmail,
      tokenHash: $tokenHash,
      requestedAt: $requestedAt,
      expiresAt: $requestedAt->modify(sprintf('+%d seconds', self::TTL_SECONDS)),
      confirmedAt: null,
    );
  }

  /**
   * Method restore.
   *
   * @static
   *
   * Rehydrates a request from persistence without re-running the
   * creation invariants.
   *
   * @since 1.0.0
   *
   * @param string $id the request identifier (UUID)
   * @param UserId $userId the user whose email is being changed
   * @param Email $currentEmail the current sign-in email address
   * @param Email $newEmail the requested new email address
   * @param string $tokenHash the SHA-256 hash of the confirmation token
   * @param DateTimeImmutable $requestedAt when the request was created
   * @param DateTimeImmutable $expiresAt when the confirmation token expires
   * @param DateTimeImmutable|null $confirmedAt when the request was confirmed, if it was
   *
   * @return self the rehydrated request
   */
  public static function restore(
    string $id,
    UserId $userId,
    Email $currentEmail,
    Email $newEmail,
    string $tokenHash,
    DateTimeImmutable $requestedAt,
    DateTimeImmutable $expiresAt,
    ?DateTimeImmutable $confirmedAt,
  ): self {
    return new self(
      id: $id,
      userId: $userId,
      currentEmail: $currentEmail,
      newEmail: $newEmail,
      tokenHash: $tokenHash,
      requestedAt: $requestedAt,
      expiresAt: $expiresAt,
      confirmedAt: $confirmedAt,
    );
  }
  // #endregion

  // #region Methods
  /**
   * Method confirm.
   *
   * Marks the request as confirmed. Refuses an expired or already-used
   * request with the same neutral exception the lookup path uses, so a
   * caller cannot distinguish the failure modes.
   *
   * @since 1.0.0
   *
   * @param DateTimeImmutable $now the current time
   *
   * @throws EmailChangeRequestNotFoundException when the request is expired or already confirmed
   *
   * @return void No return value
   */
  public function confirm(DateTimeImmutable $now): void
  {
    if ($this->isConfirmed() || $this->isExpired($now)) {
      throw EmailChangeRequestNotFoundException::invalidToken();
    }

    $this->confirmedAt = $now;
  }

  /**
   * Method isExpired.
   *
   * Whether the confirmation token has expired.
   *
   * @since 1.0.0
   *
   * @param DateTimeImmutable $now the current time
   *
   * @return bool true when expired
   */
  public function isExpired(DateTimeImmutable $now): bool
  {
    return $now >= $this->expiresAt;
  }

  /**
   * Method isConfirmed.
   *
   * Whether the request has already been confirmed (single use).
   *
   * @since 1.0.0
   *
   * @return bool true when confirmed
   */
  public function isConfirmed(): bool
  {
    return null !== $this->confirmedAt;
  }

  /**
   * Method id.
   *
   * Get the request identifier.
   *
   * @since 1.0.0
   *
   * @return string the request identifier
   */
  public function id(): string
  {
    return $this->id;
  }

  /**
   * Method userId.
   *
   * Get the user identifier.
   *
   * @since 1.0.0
   *
   * @return UserId the user identifier
   */
  public function userId(): UserId
  {
    return $this->userId;
  }

  /**
   * Method currentEmail.
   *
   * Get the current sign-in email address.
   *
   * @since 1.0.0
   *
   * @return Email the current email
   */
  public function currentEmail(): Email
  {
    return $this->currentEmail;
  }

  /**
   * Method newEmail.
   *
   * Get the requested new email address.
   *
   * @since 1.0.0
   *
   * @return Email the new email
   */
  public function newEmail(): Email
  {
    return $this->newEmail;
  }

  /**
   * Method tokenHash.
   *
   * Get the SHA-256 hash of the confirmation token.
   *
   * @since 1.0.0
   *
   * @return string the token hash
   */
  public function tokenHash(): string
  {
    return $this->tokenHash;
  }

  /**
   * Method requestedAt.
   *
   * Get the request creation time.
   *
   * @since 1.0.0
   *
   * @return DateTimeImmutable the creation time
   */
  public function requestedAt(): DateTimeImmutable
  {
    return $this->requestedAt;
  }

  /**
   * Method expiresAt.
   *
   * Get the confirmation token expiry time.
   *
   * @since 1.0.0
   *
   * @return DateTimeImmutable the expiry time
   */
  public function expiresAt(): DateTimeImmutable
  {
    return $this->expiresAt;
  }

  /**
   * Method confirmedAt.
   *
   * Get the confirmation time, if the request was confirmed.
   *
   * @since 1.0.0
   *
   * @return DateTimeImmutable|null the confirmation time
   */
  public function confirmedAt(): ?DateTimeImmutable
  {
    return $this->confirmedAt;
  }
  // #endregion
}
