<?php

declare(strict_types=1);

namespace Intervention\Presentation\Api\Dto\Input;

use ApiPlatform\Metadata\ApiProperty;
use DateTimeInterface;
use Intervention\Domain\ValueObject\RecurrenceFrequency;
use Intervention\Presentation\Api\Validator\ValidTimezone\ValidTimezone;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO UpdateInterventionRecurrenceInput.
 *
 * A field is only applied when present in the merge-patch request body.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class UpdateInterventionRecurrenceInput
{
  /**
   * Property name.
   *
   * @since 1.0.0
   */
  #[Assert\Length(min: 1, max: 160)]
  public ?string $name = null;

  /**
   * Property site.
   *
   * @since 1.0.0
   */
  public ?string $site = null;

  /**
   * Property responsible.
   *
   * @since 1.0.0
   */
  public ?string $responsible = null;

  /**
   * Property frequency.
   *
   * @since 1.0.0
   */
  #[Assert\Choice(callback: [RecurrenceFrequency::class, 'values'])]
  public ?string $frequency = null;

  /**
   * Property interval.
   *
   * @since 1.0.0
   */
  #[Assert\Range(min: 1, max: 12)]
  public ?int $interval = null;

  /**
   * Property anchorDate.
   *
   * @since 1.0.0
   */
  #[Assert\DateTime(format: DateTimeInterface::ATOM)]
  public ?string $anchorDate = null;

  /**
   * Property timezone.
   *
   * @since 1.0.0
   */
  #[ValidTimezone]
  public ?string $timezone = null;

  /**
   * Property leadTimeDays.
   *
   * @since 1.0.0
   */
  #[Assert\Range(min: 0, max: 90)]
  public ?int $leadTimeDays = null;

  /**
   * Property endAt.
   *
   * @since 1.0.0
   */
  #[Assert\DateTime(format: DateTimeInterface::ATOM)]
  public ?string $endAt = null;

  /**
   * Property isActive.
   *
   * @since 1.0.0
   */
  #[ApiProperty(example: false)]
  public ?bool $isActive = null;
}
