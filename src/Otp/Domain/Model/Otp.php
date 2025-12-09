<?php

declare(strict_types=1);

namespace Otp\Domain\Model;

use DateTimeImmutable;
use Otp\Domain\Event\{
  OtpGeneratedEvent,
  OtpVerifiedEvent,
  OtpFailedEvent,
};
use Otp\Domain\Exception\{
  OtpExpiredException,
  OtpMaxAttemptsException,
};
use Otp\Domain\ValueObject\{
  OtpId,
  OtpCode,
  OtpChannel,
  OtpPurpose,
  ChallengeToken,
};
use Shared\Domain\Event\DomainEvent;

/**
 * Model Otp
 * @final
 *
 * Aggregate root representing a one-time password challenge.
 *
 * @category Model
 * @package Otp\Domain\Model
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class Otp
{
  //#region Properties
  /**
   * Property events
   *
   * Domain events raised by this aggregate.
   * 
   * @access private
   * @since 1.0.0
   *
   * @var list<DomainEvent>
   */
  private array $events = [];
  //#endregion

  //#region Constructor
  /**
   * Constructor
   *
   * @access private
   * @since 1.0.0
   *
   * @param OtpId $id The OTP ID.
   * @param ChallengeToken $challengeToken The public challenge token.
   * @param string $userId The user ID.
   * @param OtpPurpose $purpose The OTP purpose.
   * @param OtpChannel $channel The delivery channel.
   * @param OtpCode $code The OTP code.
   * @param string $recipient The recipient (email/phone).
   * @param DateTimeImmutable $expiresAt Expiration time.
   * @param int $maxAttempts Maximum verification attempts.
   * @param int $attempts Current attempt count.
   * @param DateTimeImmutable|null $verifiedAt When verified.
   * @param DateTimeImmutable $createdAt Creation time.
   */
  private function __construct(
    private OtpId $id,
    private ChallengeToken $challengeToken,
    private string $userId,
    private OtpPurpose $purpose,
    private OtpChannel $channel,
    private OtpCode $code,
    private string $recipient,
    private DateTimeImmutable $expiresAt,
    private int $maxAttempts,
    private int $attempts = 0,
    private ?DateTimeImmutable $verifiedAt = null,
    private DateTimeImmutable $createdAt = new DateTimeImmutable(),
  ) {}
  //#endregion

  //#region Methods
  /**
   * Method generate
   * @static
   *
   * Creates a new OTP challenge.
   *
   * @access public
   * @since 1.0.0
   *
   * @param OtpId $id The OTP ID.
   * @param string $userId The user ID.
   * @param OtpPurpose $purpose The purpose.
   * @param OtpChannel $channel The channel.
   * @param string $recipient The recipient.
   * @param int|null $ttlSeconds Custom TTL (null for default).
   * @param int|null $maxAttempts Custom max attempts (null for default).
   *
   * @return self The new OTP.
   */
  public static function generate(
    OtpId $id,
    string $userId,
    OtpPurpose $purpose,
    OtpChannel $channel,
    string $recipient,
    ?int $ttlSeconds = null,
    ?int $maxAttempts = null,
  ): self {
    $ttl = $ttlSeconds ?? $purpose->getDefaultTtlSeconds();
    $attempts = $maxAttempts ?? $purpose->getDefaultMaxAttempts();

    $otp = new self(
      id: $id,
      challengeToken: ChallengeToken::generate(),
      userId: $userId,
      purpose: $purpose,
      channel: $channel,
      code: OtpCode::generate(),
      recipient: $recipient,
      expiresAt: new DateTimeImmutable("+{$ttl} seconds"),
      maxAttempts: $attempts,
      createdAt: new DateTimeImmutable(),
    );

    $otp->events[] = new OtpGeneratedEvent(
      otpId: $id->value,
      userId: $userId,
      purpose: $purpose->value,
      channel: $channel->value,
    );

    return $otp;
  }

  /**
   * Method id
   *
   * Returns the OTP ID.
   *
   * @access public
   * @since 1.0.0
   *
   * @return OtpId The OTP ID.
   */
  public function id(): OtpId
  {
    return $this->id;
  }

  /**
   * Method challengeToken
   *
   * Returns the challenge token.
   *
   * @access public
   * @since 1.0.0
   *
   * @return ChallengeToken The challenge token.
   */
  public function challengeToken(): ChallengeToken
  {
    return $this->challengeToken;
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
   * Method purpose
   *
   * Returns the purpose.
   *
   * @access public
   * @since 1.0.0
   *
   * @return OtpPurpose The purpose.
   */
  public function purpose(): OtpPurpose
  {
    return $this->purpose;
  }

  /**
   * Method channel
   *
   * Returns the channel.
   *
   * @access public
   * @since 1.0.0
   *
   * @return OtpChannel The channel.
   */
  public function channel(): OtpChannel
  {
    return $this->channel;
  }

  /**
   * Method code
   *
   * Returns the code.
   *
   * @access public
   * @since 1.0.0
   *
   * @return OtpCode The code.
   */
  public function code(): OtpCode
  {
    return $this->code;
  }

  /**
   * Method recipient
   *
   * Returns the recipient.
   *
   * @access public
   * @since 1.0.0
   *
   * @return string The recipient.
   */
  public function recipient(): string
  {
    return $this->recipient;
  }

  /**
   * Method expiresAt
   *
   * Returns the expiration time.
   *
   * @access public
   * @since 1.0.0
   *
   * @return DateTimeImmutable The expiration time.
   */
  public function expiresAt(): DateTimeImmutable
  {
    return $this->expiresAt;
  }

  /**
   * Method attempts
   *
   * Returns the attempt count.
   *
   * @access public
   * @since 1.0.0
   *
   * @return int The attempt count.
   */
  public function attempts(): int
  {
    return $this->attempts;
  }

  /**
   * Method maxAttempts
   *
   * Returns the maximum attempt count.
   *
   * @access public
   * @since 1.0.0
   *
   * @return int The maximum attempt count.
   */
  public function maxAttempts(): int
  {
    return $this->maxAttempts;
  }

  /**
   * Method attemptsRemaining
   *
   * Returns the remaining attempt count.
   *
   * @access public
   * @since 1.0.0
   *
   * @return int The remaining attempt count.
   */
  public function attemptsRemaining(): int
  {
    return max(0, $this->maxAttempts - $this->attempts);
  }

  /**
   * Method verifiedAt
   *
   * Returns the verification time.
   *
   * @access public
   * @since 1.0.0
   *
   * @return ?DateTimeImmutable The verification time.
   */
  public function verifiedAt(): ?DateTimeImmutable
  {
    return $this->verifiedAt;
  }

  /**
   * Method createdAt
   *
   * Returns the creation time.
   *
   * @access public
   * @since 1.0.0
   *
   * @return DateTimeImmutable The creation time.
   */
  public function createdAt(): DateTimeImmutable
  {
    return $this->createdAt;
  }

  /**
   * Method isExpired
   *
   * Checks if the OTP has expired.
   *
   * @access public
   * @since 1.0.0
   *
   * @return bool True if expired, false otherwise.
   */
  public function isExpired(): bool
  {
    return $this->expiresAt < new DateTimeImmutable();
  }

  /**
   * Method isVerified
   *
   * Checks if the OTP has been verified.
   *
   * @access public
   * @since 1.0.0
   *
   * @return bool True if verified, false otherwise.
   */
  public function isVerified(): bool
  {
    return $this->verifiedAt !== null;
  }

  /**
   * Method hasAttemptsRemaining
   *
   * Checks if there are remaining attempts.
   *
   * @access public
   * @since 1.0.0
   *
   * @return bool True if attempts remaining, false otherwise.
   */
  public function hasAttemptsRemaining(): bool
  {
    return $this->attempts < $this->maxAttempts;
  }

  /**
   * Method canVerify
   *
   * Checks if the OTP can be verified.
   *
   * @access public
   * @since 1.0.0
   *
   * @return bool True if can verify, false otherwise.
   */
  public function canVerify(): bool
  {
    return !$this->isExpired() 
      && !$this->isVerified() 
      && $this->hasAttemptsRemaining();
  }

  /**
   * Method status
   *
   * Returns the OTP status.
   *
   * @access public
   * @since 1.0.0
   *
   * @return string The OTP status.
   */
  public function status(): string
  {
    return match (true) {
      $this->isVerified() => 'verified',
      $this->isExpired() => 'expired',
      !$this->hasAttemptsRemaining() => 'failed',
      default => 'pending',
    };
  }

  /**
   * Method verify
   *
   * Attempts to verify the OTP with given code.
   * 
   * @access public
   * @since 1.0.0
   *
   * @param string $inputCode The code to verify.
   *
   * @return bool True if verification succeeded.
   *
   * @throws OtpExpiredException If OTP is expired.
   * @throws OtpMaxAttemptsException If max attempts exceeded.
   */
  public function verify(string $inputCode): bool
  {
    if ($this->isExpired()) throw OtpExpiredException::create(
      id: $this->id
    );

    if (!$this->hasAttemptsRemaining()) throw OtpMaxAttemptsException::create(
      id: $this->id
    );

    $this->attempts++;

    if ($this->code->verify($inputCode)) {
      $this->verifiedAt = new DateTimeImmutable();

      $this->events[] = new OtpVerifiedEvent(
        otpId: $this->id->value,
        userId: $this->userId,
        purpose: $this->purpose->value,
      );

      return true;
    }

    $this->events[] = new OtpFailedEvent(
      otpId: $this->id->value,
      userId: $this->userId,
      purpose: $this->purpose->value,
      attemptsRemaining: $this->attemptsRemaining(),
    );

    return false;
  }

  /**
   * Method maskedRecipient
   *
   * Returns the masked recipient.
   *
   * @access public
   * @since 1.0.0
   *
   * @return string The masked recipient.
   */
  public function maskedRecipient(): string
  {
    return match ($this->channel) {
      OtpChannel::EMAIL => $this->maskEmail($this->recipient),
      OtpChannel::SMS => $this->maskPhone($this->recipient),
      OtpChannel::TOTP => 'Authenticator App',
    };
  }

  /**
   * Method releaseEvents
   *
   * Returns and clears domain events.
   *
   * @access public
   * @since 1.0.0
   *
   * @return list<DomainEvent>
   */
  public function releaseEvents(): array
  {
    $events = $this->events;
    $this->events = [];
    return $events;
  }

  /**
   * Method maskEmail
   *
   * Masks an email address.
   *
   * @access private
   * @since 1.0.0
   *
   * @param string $email The email address to mask.
   *
   * @return string The masked email address.
   */
  private function maskEmail(string $email): string
  {
    $parts = explode('@', $email);
    if (count($parts) !== 2) {
      return '***@***';
    }
    $local = $parts[0];
    $domain = $parts[1];
    $maskedLocal = strlen($local) <= 2
      ? str_repeat('*', strlen($local))
      : substr($local, 0, 2) . str_repeat('*', strlen($local) - 2);
    return $maskedLocal . '@' . $domain;
  }

  /**
   * Method maskPhone
   *
   * Masks a phone number.
   *
   * @access private
   * @since 1.0.0
   *
   * @param string $phone The phone number to mask.
   *
   * @return string The masked phone number.
   */
  private function maskPhone(string $phone): string
  {
    $digits = preg_replace('/[^0-9]/', '', $phone);
    if (strlen($digits) < 4) {
      return '****';
    }
    return str_repeat('*', strlen($digits) - 4) . substr($digits, -4);
  }

  /**
   * Method reconstitute
   *
   * Reconstitutes an OTP from its components.
   *
   * @access public
   * @since 1.0.0
   *
   * @param OtpId $id The OTP ID.
   * @param ChallengeToken $challengeToken The challenge token.
   * @param string $userId The user ID.
   * @param OtpPurpose $purpose The purpose.
   * @param OtpChannel $channel The channel.
   * @param string $codeHash The code hash.
   * @param string $recipient The recipient.
   * @param DateTimeImmutable $expiresAt The expiration time.
   * @param int $maxAttempts The maximum attempt count.
   * @param int $attempts The attempt count.
   * @param ?DateTimeImmutable $verifiedAt The verification time.
   * @param DateTimeImmutable $createdAt The creation time.
   *
   * @return self The reconstituted OTP.
   */
  public static function reconstitute(
    OtpId $id,
    ChallengeToken $challengeToken,
    string $userId,
    OtpPurpose $purpose,
    OtpChannel $channel,
    string $codeHash,
    string $recipient,
    DateTimeImmutable $expiresAt,
    int $maxAttempts,
    int $attempts,
    ?DateTimeImmutable $verifiedAt,
    DateTimeImmutable $createdAt,
  ): self {
    return new self(
      id: $id,
      challengeToken: $challengeToken,
      userId: $userId,
      purpose: $purpose,
      channel: $channel,
      code: OtpCode::fromHash($codeHash),
      recipient: $recipient,
      expiresAt: $expiresAt,
      maxAttempts: $maxAttempts,
      attempts: $attempts,
      verifiedAt: $verifiedAt,
      createdAt: $createdAt,
    );
  }
  //#endregion
}
