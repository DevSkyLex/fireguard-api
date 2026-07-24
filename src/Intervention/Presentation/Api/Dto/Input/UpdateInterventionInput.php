<?php

declare(strict_types=1);

namespace Intervention\Presentation\Api\Dto\Input;

use DateTimeInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO UpdateInterventionInput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class UpdateInterventionInput
{
  /**
   * Property name.
   *
   * @since 1.0.0
   */
  #[Assert\Length(min: 1, max: 160)]
  public ?string $name = null;

  /**
   * Property description.
   *
   * @since 1.0.0
   */
  #[Assert\Length(max: 5000)]
  public ?string $description = null;

  /**
   * Property status.
   *
   * @since 1.0.0
   */
  #[Assert\Choice(choices: ['draft', 'planned', 'in_progress', 'submitted', 'changes_requested', 'published', 'abandoned'])]
  public ?string $status = null;

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
   * Property participants.
   *
   * @since 1.0.0
   *
   * @var list<string>|null
   */
  #[Assert\All([new Assert\Type('string')])]
  public ?array $participants = null;

  /**
   * Property priority.
   *
   * @since 1.0.0
   */
  #[Assert\Choice(choices: ['low', 'normal', 'high', 'urgent'])]
  public ?string $priority = null;

  /**
   * Property plannedStartAt.
   *
   * @since 1.0.0
   */
  #[Assert\DateTime(format: DateTimeInterface::ATOM)]
  public ?string $plannedStartAt = null;

  /**
   * Property dueAt.
   *
   * @since 1.0.0
   */
  #[Assert\DateTime(format: DateTimeInterface::ATOM)]
  public ?string $dueAt = null;

  /**
   * Property reviewNote.
   *
   * @since 1.0.0
   */
  #[Assert\Length(max: 4000)]
  public ?string $reviewNote = null;

  /**
   * Property labelIds.
   *
   * Intervention label ids (uuids) to assign. PATCH replaces the full set:
   * when this field is present in the merge-patch body, the intervention's
   * labels become exactly this list.
   *
   * @since 1.0.0
   *
   * @var list<string>|null
   */
  #[Assert\All([new Assert\Type('string')])]
  public ?array $labelIds = null;
}
