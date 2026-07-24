<?php

declare(strict_types=1);

namespace Import\Presentation\Api\Dto\Output;

/**
 * DTO ImportRowErrorOutput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ImportRowErrorOutput
{
  /**
   * Property rowNumber.
   *
   * @since 1.0.0
   */
  public int $rowNumber = 0;

  /**
   * Property column.
   *
   * @since 1.0.0
   */
  public ?string $column = null;

  /**
   * Property code.
   *
   * One of `quota_exceeded`, `invalid`, `missing_required`.
   *
   * @since 1.0.0
   */
  public string $code = '';

  /**
   * Property message.
   *
   * @since 1.0.0
   */
  public string $message = '';
}
