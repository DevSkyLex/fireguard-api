<?php

declare(strict_types=1);

namespace Auth\Application\UseCase\Command\Registration\RegisterUser;

use Auth\Application\Port\Outbound\AuthenticationPolicyPort;
use Otp\Application\Contract\Challenge\{OtpChannel, OtpPurpose};
use Otp\Application\Port\Inbound\Challenge\{ChallengeAccessPort, OtpChallengePort};
use Shared\Application\Message\CommandHandler;
use Shared\Domain\ValueObject\Email;
use User\Application\Port\Inbound\UserRegistrationPort;

use function preg_replace;
use function random_int;
use function str_pad;
use function strlen;
use function strstr;
use function strtolower;
use function substr;

use const STR_PAD_RIGHT;

/**
 * Handler RegisterUserHandler.
 *
 * Creates accounts through the User owner capability when project signup is enabled.
 * The installed default requires email verification; an explicit policy waiver skips its challenge.
 *
 * @category Handler
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RegisterUserHandler implements CommandHandler
{
  // #region Constants
  /**
   * Maximum attempts to find a free derived username before giving up.
   */
  private const int MAX_USERNAME_ATTEMPTS = 10;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * RegisterUserHandler class.
   *
   * @since 1.0.0
   *
   * @param UserRegistrationPort $userRepository the user repository port
   * @param OtpChallengePort $otpChallenge the OTP challenge port
   * @param AuthenticationPolicyPort $authenticationPolicy project registration choices
   */
  public function __construct(
    private readonly UserRegistrationPort $userRepository,
    private readonly OtpChallengePort $otpChallenge,
    private AuthenticationPolicyPort $authenticationPolicy,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * Handles the RegisterUserCommand.
   *
   * @since 1.0.0
   *
   * @param RegisterUserCommand $command the command
   *
   * @return RegisterUserResult the result
   */
  public function __invoke(RegisterUserCommand $command): RegisterUserResult
  {
    if (!$this->authenticationPolicy->signupEnabled()) {
      return RegisterUserResult::failed('Self-service registration is disabled.', RegisterUserResult::ERROR_REGISTRATION_DISABLED);
    }

    $email = new Email(strtolower($command->email));

    // Unlike password reset, a sign-up must report a taken email (409).
    if ($this->userRepository->emailExists($email->value)) {
      return RegisterUserResult::failed(
        message: 'An account already exists with this email address.',
        errorCode: RegisterUserResult::ERROR_EMAIL_TAKEN,
      );
    }

    $username = $this->deriveUniqueUsername($email->value);

    $userId = $this->userRepository->register(
      username: $username,
      email: $email->value,
      password: $command->password,
      firstName: $command->firstName,
      lastName: $command->lastName,
      requireEmailVerification: $this->authenticationPolicy->emailVerificationRequired(),
    );

    if (!$this->authenticationPolicy->emailVerificationRequired()) {
      return new RegisterUserResult(success: true, message: 'Your account has been created. You can now sign in.');
    }

    $challenge = $this->otpChallenge->generate(
      userId: $userId,
      purpose: OtpPurpose::EMAIL_VERIFICATION,
      channel: OtpChannel::EMAIL,
      recipient: $email->value,
    );

    return RegisterUserResult::success(
      challengeToken: $challenge->challengeToken,
      maskedRecipient: $challenge->maskedRecipient,
      expiresAt: $challenge->expiresAt,
      maxAttempts: $challenge->maxAttempts,
      canResendIn: ChallengeAccessPort::RESEND_COOLDOWN_SECONDS,
    );
  }

  /**
   * Method deriveUniqueUsername.
   *
   * Derives a valid, unique username from the email local part, appending a
   * numeric suffix until a free one is found.
   *
   * @since 1.0.0
   *
   * @param string $email the (lowercased) email address
   *
   * @return string a username matching the owned username pattern and not yet taken
   */
  private function deriveUniqueUsername(string $email): string
  {
    $base = $this->normalizeUsernameBase($email);

    if (!$this->userRepository->usernameExists($base)) {
      return $base;
    }

    for ($i = 0; $i < self::MAX_USERNAME_ATTEMPTS; ++$i) {
      $suffix = (string) random_int(1000, 999999);
      $candidate = substr($base, 0, 50 - 1 - strlen($suffix)) . '-' . $suffix;

      if (!$this->userRepository->usernameExists($candidate)) {
        return $candidate;
      }
    }

    // Extremely unlikely fallback: a fully random username.
    return 'user-' . random_int(100000000, 999999999);
  }

  /**
   * Method normalizeUsernameBase.
   *
   * Reduces the email local part to the allowed username alphabet and guarantees
   * the minimum length, leaving room for a uniqueness suffix.
   *
   * @since 1.0.0
   *
   * @param string $email the (lowercased) email address
   *
   * @return string a valid username base (3-44 characters)
   */
  private function normalizeUsernameBase(string $email): string
  {
    $localPart = strstr($email, '@', true);
    if (false === $localPart) {
      $localPart = $email;
    }

    $cleaned = preg_replace('/[^a-z0-9_-]/', '', $localPart) ?? '';

    if (strlen($cleaned) < 3) {
      $cleaned = str_pad($cleaned, 3, '0', STR_PAD_RIGHT);
    }

    // Cap the base so a "-<suffix>" can always be appended within 50 chars.
    return substr($cleaned, 0, 44);
  }
  // #endregion
}
