<?php

declare(strict_types=1);

namespace Otp\Domain\Model;

use DateTimeImmutable;
use Otp\Domain\Event\{
  OtpFailedEvent,
  OtpGeneratedEvent,
  OtpVerifiedEvent,
};
use Otp\Domain\Exception\{
  OtpExpiredException,
  OtpMaxAttemptsException,
};
use Otp\Domain\ValueObject\{
  ChallengeToken,
  OtpChannel,
  OtpCode,
  OtpId,
  OtpPurpose,
};
use Shared\Domain\Event\DomainEvent;

use function count;
use function explode;
use function is_string;
use function max;
use function preg_replace;
use function str_repeat;
use function strlen;
use function substr;

/**
 * Model Otp.
 *
 * @category Model
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class Otp
{
  // #region Properties
  /**
   * Property events.
   *
   * Domain events raised by this aggregate.
   *
   * @since 1.0.0
   *
   * @var list<DomainEvent>
   */
  private array $events = [];
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param OtpId $id the OTP ID
   * @param ChallengeToken $challengeToken the public challenge token
   * @param string $userId the user ID
   * @param OtpPurpose $purpose the OTP purpose
   * @param OtpChannel $channel the delivery channel
   * @param OtpCode $code the OTP code
   * @param string $recipient the recipient (email/phone)
   * @param DateTimeImmutable $expiresAt expiration time
   * @param int $maxAttempts maximum verification attempts
   * @param int $attempts current attempt count
   * @param DateTimeImmutable|null $verifiedAt when verified
   * @param DateTimeImmutable $createdAt creation time
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
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method generate.
   *
   * @static
   *
   * Creates a new OTP challenge.
   *
   * @since 1.0.0
   *
   * @param OtpId $id the OTP ID
   * @param string $userId the user ID
   * @param OtpPurpose $purpose the purpose
   * @param OtpChannel $channel the channel
   * @param string $recipient the recipient
   * @param int|null $ttlSeconds custom TTL (null for default)
   * @param int|null $maxAttempts custom max attempts (null for default)
   * @param int|null $codeLength custom OTP code length (null for default)
   *
   * @return self the new OTP
   */
  public static function generate(
    OtpId $id,
    string $userId,
    OtpPurpose $purpose,
    OtpChannel $channel,
    string $recipient,
    ?int $ttlSeconds = null,
    ?int $maxAttempts = null,
    ?int $codeLength = null,
  ): self {
    $ttl = $ttlSeconds ?? $purpose->getDefaultTtlSeconds();
    $attempts = $maxAttempts ?? $purpose->getDefaultMaxAttempts();
    $effectiveCodeLength = null === $codeLength ? null : max(1, $codeLength);
    $otpCode = null === $effectiveCodeLength
      ? OtpCode::generate()
      : OtpCode::generate($effectiveCodeLength);

    $otp = new self(
      id: $id,
      challengeToken: ChallengeToken::generate(),
      userId: $userId,
      purpose: $purpose,
      channel: $channel,
      code: $otpCode,
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
   * Method id.
   *
   * Returns the OTP ID.
   *
   * @since 1.0.0
   *
   * @return OtpId the OTP ID
   */
  public function id(): OtpId
  {
    return $this->id;
  }

  /**
   * Method challengeToken.
   *
   * Returns the challenge token.
   *
   * @since 1.0.0
   *
   * @return ChallengeToken the challenge token
   */
  public function challengeToken(): ChallengeToken
  {
    return $this->challengeToken;
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
   * Method purpose.
   *
   * Returns the purpose.
   *
   * @since 1.0.0
   *
   * @return OtpPurpose the purpose
   */
  public function purpose(): OtpPurpose
  {
    return $this->purpose;
  }

  /**
   * Method channel.
   *
   * Returns the channel.
   *
   * @since 1.0.0
   *
   * @return OtpChannel the channel
   */
  public function channel(): OtpChannel
  {
    return $this->channel;
  }

  /**
   * Method code.
   *
   * Returns the code.
   *
   * @since 1.0.0
   *
   * @return OtpCode the code
   */
  public function code(): OtpCode
  {
    return $this->code;
  }

  /**
   * Method recipient.
   *
   * Returns the recipient.
   *
   * @since 1.0.0
   *
   * @return string the recipient
   */
  public function recipient(): string
  {
    return $this->recipient;
  }

  /**
   * Method expiresAt.
   *
   * Returns the expiration time.
   *
   * @since 1.0.0
   *
   * @return DateTimeImmutable the expiration time
   */
  public function expiresAt(): DateTimeImmutable
  {
    return $this->expiresAt;
  }

  /**
   * Method attempts.
   *
   * Returns the attempt count.
   *
   * @since 1.0.0
   *
   * @return int the attempt count
   */
  public function attempts(): int
  {
    return $this->attempts;
  }

  /**
   * Method maxAttempts.
   *
   * Returns the maximum attempt count.
   *
   * @since 1.0.0
   *
   * @return int the maximum attempt count
   */
  public function maxAttempts(): int
  {
    return $this->maxAttempts;
  }

  /**
   * Method attemptsRemaining.
   *
   * Returns the remaining attempt count.
   *
   * @since 1.0.0
   *
   * @return int the remaining attempt count
   */
  public function attemptsRemaining(): int
  {
    return max(0, $this->maxAttempts - $this->attempts);
  }

  /**
   * Method verifiedAt.
   *
   * Returns the verification time.
   *
   * @since 1.0.0
   *
   * @return ?DateTimeImmutable the verification time
   */
  public function verifiedAt(): ?DateTimeImmutable
  {
    return $this->verifiedAt;
  }

  /**
   * Method createdAt.
   *
   * Returns the creation time.
   *
   * @since 1.0.0
   *
   * @return DateTimeImmutable the creation time
   */
  public function createdAt(): DateTimeImmutable
  {
    return $this->createdAt;
  }

  /**
   * Method isExpired.
   *
   * Checks if the OTP has expired.
   *
   * @since 1.0.0
   *
   * @return bool true if expired, false otherwise
   */
  public function isExpired(): bool
  {
    return $this->expiresAt < new DateTimeImmutable();
  }

  /**
   * Method isVerified.
   *
   * Checks if the OTP has been verified.
   *
   * @since 1.0.0
   *
   * @return bool true if verified, false otherwise
   */
  public function isVerified(): bool
  {
    return null !== $this->verifiedAt;
  }

  /**
   * Method hasAttemptsRemaining.
   *
   * Checks if there are remaining attempts.
   *
   * @since 1.0.0
   *
   * @return bool true if attempts remaining, false otherwise
   */
  public function hasAttemptsRemaining(): bool
  {
    return $this->attempts < $this->maxAttempts;
  }

  /**
   * Method canVerify.
   *
   * Checks if the OTP can be verified.
   *
   * @since 1.0.0
   *
   * @return bool true if can verify, false otherwise
   */
  public function canVerify(): bool
  {
    return !$this->isExpired()
      && !$this->isVerified()
      && $this->hasAttemptsRemaining();
  }

  /**
   * Method status.
   *
   * Returns the OTP status.
   *
   * @since 1.0.0
   *
   * @return string the OTP status
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
   * Method verify.
   *
   * Attempts to verify the OTP with given code.
   *
   * @since 1.0.0
   *
   * @param string $inputCode the code to verify
   *
   * @throws OtpExpiredException if OTP is expired
   * @throws OtpMaxAttemptsException if max attempts exceeded
   *
   * @return bool true if verification succeeded
   */
  public function verify(string $inputCode): bool
  {
    if ($this->isExpired()) {
      throw OtpExpiredException::create(
        id: $this->id,
      );
    }

    if (!$this->hasAttemptsRemaining()) {
      throw OtpMaxAttemptsException::create(
        id: $this->id,
      );
    }

    ++$this->attempts;

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
   * Method maskedRecipient.
   *
   * Returns the masked recipient.
   *
   * @since 1.0.0
   *
   * @return string the masked recipient
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
   * Method releaseEvents.
   *
   * Returns and clears domain events.
   *
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
   * Method reconstitute.
   *
   * Reconstitutes an OTP from its components.
   *
   * @since 1.0.0
   *
   * @param OtpId $id the OTP ID
   * @param ChallengeToken $challengeToken the challenge token
   * @param string $userId the user ID
   * @param OtpPurpose $purpose the purpose
   * @param OtpChannel $channel the channel
   * @param string $codeHash the code hash
   * @param string $recipient the recipient
   * @param DateTimeImmutable $expiresAt the expiration time
   * @param int $maxAttempts the maximum attempt count
   * @param int $attempts the attempt count
   * @param ?DateTimeImmutable $verifiedAt the verification time
   * @param DateTimeImmutable $createdAt the creation time
   *
   * @return self the reconstituted OTP
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

  /**
   * Method maskEmail.
   *
   * Masks an email address.
   *
   * @since 1.0.0
   *
   * @param string $email the email address to mask
   *
   * @return string the masked email address
   */
  private function maskEmail(string $email): string
  {
    $parts = explode('@', $email);
    if (2 !== count($parts)) {
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
   * Method maskPhone.
   *
   * Masks a phone number.
   *
   * @since 1.0.0
   *
   * @param string $phone the phone number to mask
   *
   * @return string the masked phone number
   */
  private function maskPhone(string $phone): string
  {
    $digits = preg_replace('/[^0-9]/', '', $phone);
    $digits = is_string($digits) ? $digits : '';
    if (strlen($digits) < 4) {
      return '****';
    }

    return str_repeat('*', strlen($digits) - 4) . substr($digits, -4);
  }
  // #endregion
}
