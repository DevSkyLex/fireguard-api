<?php

declare(strict_types=1);

namespace Organization\Application\Contract\Settings;

use InvalidArgumentException;

use function preg_match;

/**
 * Class OrganizationSettingsPolicyDefinition
 *
 * Publishes one typed settings field, its default and structural constraints using an owner-controlled section.field key.
 *
 * @category Contract
 */
final readonly class OrganizationSettingsPolicyDefinition
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param string $key stable section.field identifier
   * @param OrganizationSettingsValueType $type structural field type
   * @param bool|int|float|string|array<string, mixed>|null $default effective default
   * @param int|float|null $minimum inclusive numeric lower bound
   * @param int|float|null $maximum inclusive numeric upper bound
   * @param list<string>|null $allowedKeys allowed map keys or null for open owner identifiers
   * @param bool $nullResets whether explicit null resets the scalar field
   */
  public function __construct(
    public string $key,
    public OrganizationSettingsValueType $type,
    public bool|int|float|string|array|null $default,
    public int|float|null $minimum = null,
    public int|float|null $maximum = null,
    public ?array $allowedKeys = null,
    public bool $nullResets = false,
  ) {
    if (1 !== preg_match('/^[a-z][a-z0-9_]*\.[a-z][a-z0-9_]*$/D', $key)) {
      throw new InvalidArgumentException('Invalid organization settings policy key.');
    }
  }
  // #endregion
}
