<?php

declare(strict_types=1);

namespace Mission\Presentation\Api\Dto\Output;

/**
 * DTO MissionIssueOutput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class MissionIssueOutput
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
