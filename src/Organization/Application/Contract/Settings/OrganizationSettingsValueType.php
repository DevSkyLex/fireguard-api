<?php

declare(strict_types=1);

namespace Organization\Application\Contract\Settings;

/**
 * Enum OrganizationSettingsValueType
 *
 * Closed structural JSON value kinds used by open owner-published settings fields.
 *
 * @category Contract
 */
enum OrganizationSettingsValueType: string
{
  case BOOLEAN = 'boolean';
  case INTEGER = 'integer';
  case NUMBER = 'number';
  case TEXT = 'text';
  case INTEGER_MAP = 'integer_map';
  case TEXT_MAP = 'text_map';
  case OBJECT_MAP = 'object_map';
}
