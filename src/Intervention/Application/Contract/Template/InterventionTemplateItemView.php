<?php

declare(strict_types=1);

namespace Intervention\Application\Contract\Template;

/**
 * Domain InterventionTemplateItemView.
 *
 * @category Domain
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class InterventionTemplateItemView
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $id the id value
   * @param int $position the position value
   * @param string $action the action value
   * @param ?string $target the target value
   * @param ?string $resultResource the result resource value
   * @param bool $required the required value
   * @param ?string $defaultAssigneeId the default assignee id value
   */
  public function __construct(
    public string $id,
    public int $position,
    public string $action,
    public ?string $target,
    public ?string $resultResource,
    public bool $required,
    public ?string $defaultAssigneeId,
  ) {
  }
}
