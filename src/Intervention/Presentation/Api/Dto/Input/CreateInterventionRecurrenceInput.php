<?php

declare(strict_types=1);

namespace Intervention\Presentation\Api\Dto\Input;

use ApiPlatform\Metadata\ApiProperty;
use DateTimeInterface;
use Intervention\Domain\ValueObject\RecurrenceFrequency;
use Intervention\Presentation\Api\Validator\ValidTimezone\ValidTimezone;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO CreateInterventionRecurrenceInput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class CreateInterventionRecurrenceInput
{
  /**
   * Property organization.
   *
   * @since 1.0.0
   */
  #[Assert\NotBlank]
  #[ApiProperty(example: '/api/organizations/550e8400-e29b-41d4-a716-446655440000')]
  public string $organization = '';

  /**
   * Property template.
   *
   * @since 1.0.0
   */
  #[Assert\NotBlank]
  #[ApiProperty(example: '/api/intervention-templates/550e8400-e29b-41d4-a716-446655440000')]
  public string $template = '';

  /**
   * Property name.
   *
   * @since 1.0.0
   */
  #[Assert\NotBlank]
  #[Assert\Length(max: 160)]
  public string $name = '';

  /**
   * Property site.
   *
   * Site (facility) override; when absent, the template default is used.
   *
   * @since 1.0.0
   */
  public ?string $site = null;

  /**
   * Property responsible.
   *
   * Responsible member override; when absent, the template default is used.
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
  public string $frequency = 'monthly';

  /**
   * Property interval.
   *
   * @since 1.0.0
   */
  #[Assert\Range(min: 1, max: 12)]
  public int $interval = 1;

  /**
   * Property anchorDate.
   *
   * @since 1.0.0
   */
  #[Assert\NotBlank]
  #[Assert\DateTime(format: DateTimeInterface::ATOM)]
  #[ApiProperty(example: '2026-01-31T00:00:00+00:00')]
  public string $anchorDate = '';

  /**
   * Property timezone.
   *
   * @since 1.0.0
   */
  #[Assert\NotBlank]
  #[ValidTimezone]
  #[ApiProperty(example: 'Europe/Paris')]
  public string $timezone = 'UTC';

  /**
   * Property leadTimeDays.
   *
   * @since 1.0.0
   */
  #[Assert\Range(min: 0, max: 90)]
  public int $leadTimeDays = 7;

  /**
   * Property endAt.
   *
   * @since 1.0.0
   */
  #[Assert\DateTime(format: DateTimeInterface::ATOM)]
  public ?string $endAt = null;
}
