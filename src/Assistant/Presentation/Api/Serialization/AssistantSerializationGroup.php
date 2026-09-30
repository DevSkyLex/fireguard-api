<?php

declare(strict_types=1);

namespace Assistant\Presentation\Api\Serialization;

/**
 * Serialization AssistantSerializationGroup.
 *
 * @category Serialization
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class AssistantSerializationGroup
{
  /**
   * Constant READ
   */
  public const string READ = 'Assistant:read';

  /**
   * Constant WRITE
   */
  public const string WRITE = 'Assistant:write';
}
