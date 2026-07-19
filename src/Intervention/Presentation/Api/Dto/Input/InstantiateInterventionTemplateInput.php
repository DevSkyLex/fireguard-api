<?php

declare(strict_types=1);

namespace Intervention\Presentation\Api\Dto\Input;

use DateTimeInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO InstantiateInterventionTemplateInput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InstantiateInterventionTemplateInput
{
  /**
   * Property name.
   *
   * Overrides the template name, when provided.
   *
   * @since 1.0.0
   */
  #[Assert\Length(min: 2, max: 160)]
  public ?string $name = null;

  /**
   * Property site.
   *
   * Overrides the template default site, when provided.
   *
   * @since 1.0.0
   */
  public ?string $site = null;

  /**
   * Property responsible.
   *
   * Overrides the template default responsible, when provided.
   *
   * @since 1.0.0
   */
  public ?string $responsible = null;

  /**
   * Property plannedStartAt.
   *
   * @since 1.0.0
   */
  #[Assert\DateTime(format: DateTimeInterface::ATOM)]
  public ?string $plannedStartAt = null;
}
