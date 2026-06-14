<?php

declare(strict_types=1);

namespace Intervention\Presentation\Api\Dto\Output;

/**
 * DTO InterventionIssueOutput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InterventionIssueOutput
{
  /**
   * Property severity.
   *
   * @since 1.0.0
   */
  public string $severity = '';

  /**
   * Property resource.
   *
   * @since 1.0.0
   */
  public string $resource = '';

  /**
   * Property field.
   *
   * @since 1.0.0
   */
  public ?string $field = null;

  /**
   * Property message.
   *
   * @since 1.0.0
   */
  public string $message = '';
}
