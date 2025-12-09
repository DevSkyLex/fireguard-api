<?php

declare(strict_types=1);

namespace Otp\Presentation\Api\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO GenerateOtpInput
 * @final
 *
 * Input DTO for OTP generation.
 *
 * @category DTO
 * @package Otp\Presentation\Api\Dto
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class GenerateOtpInput
{
  //#region Properties
  /**
   * Property purpose
   *
   * The OTP purpose.
   *
   * @var string
   */
  #[Assert\NotBlank]
  #[Assert\Choice(choices: ['login', 'password_reset', 'email_verification', 'phone_verification', 'sensitive_operation', 'transaction_approval'])]
  public string $purpose;

  /**
   * Property channel
   *
   * The delivery channel.
   *
   * @var string
   */
  #[Assert\NotBlank]
  #[Assert\Choice(choices: ['email', 'sms', 'totp'])]
  public string $channel;

  /**
   * Property recipient
   *
   * The recipient (email or phone). Optional if using user's default.
   *
   * @var string|null
   */
  public ?string $recipient = null;

  /**
   * Property ttlSeconds
   *
   * Custom TTL in seconds.
   *
   * @var int|null
   */
  #[Assert\Range(min: 60, max: 3600)]
  public ?int $ttlSeconds = null;
  //#endregion
}
