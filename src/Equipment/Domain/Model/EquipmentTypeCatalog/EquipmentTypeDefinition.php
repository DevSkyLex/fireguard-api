<?php

declare(strict_types=1);

namespace Equipment\Domain\Model\EquipmentTypeCatalog;

use Equipment\Domain\Exception\EquipmentTypeCatalogException;

use function in_array;
use function mb_strlen;
use function preg_match;
use function trim;

/**
 * Class EquipmentTypeDefinition.
 *
 * An organization-owned descriptor. Codes remain stable after creation and archived
 * descriptors remain readable for equipment history.
 *
 * @category Model
 */
final readonly class EquipmentTypeDefinition
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * @access public
   *
   * @param string $value stable equipment type code
   * @param string $label display label
   * @param string $family fire, safety or other
   * @param bool $archived whether unavailable for new assignments
   * @param int $revision optimistic catalog revision
   *
   * @return void
   */
  public function __construct(
    public string $value,
    public string $label,
    public string $family,
    public bool $archived = false,
    public int $revision = 1,
  ) {
    if ('' === trim($value) || mb_strlen($value) > 32 || '' === trim($label) || mb_strlen($label) > 100 || !in_array($family, ['fire', 'safety', 'other'], true) || $revision < 1) {
      throw new EquipmentTypeCatalogException('equipment_type_invalid', 'Equipment type requires a code (max 32), label (max 100), valid family and positive revision.');
    }
  }
  // #endregion

  // #region Methods
  /**
   * Method create.
   *
   * New codes use stable lowercase tokens; restoration also supports historical codes.
   *
   * @access public
   *
   * @param string $value new code
   * @param string $label display label
   * @param string $family catalog family
   *
   * @return self new active descriptor
   */
  public static function create(string $value, string $label, string $family): self
  {
    if (1 !== preg_match('/^[a-z][a-z0-9_]{0,31}$/D', $value)) {
      throw new EquipmentTypeCatalogException('equipment_type_invalid', 'Equipment type code must be a lowercase token of at most 32 characters.');
    }

    return new self($value, trim($label), $family);
  }

  /**
   * Method revise.
   *
   * A real descriptor change increments its optimistic revision exactly once.
   *
   * @access public
   *
   * @param string|null $label replacement label, null preserves current
   * @param string|null $family replacement family, null preserves current
   * @param bool|null $archived replacement archived state, null preserves current
   *
   * @return self updated descriptor
   */
  public function revise(?string $label, ?string $family, ?bool $archived): self
  {
    $nextLabel = null === $label ? $this->label : trim($label);
    $nextFamily = $family ?? $this->family;
    $nextArchived = $archived ?? $this->archived;
    $changed = $nextLabel !== $this->label || $nextFamily !== $this->family || $nextArchived !== $this->archived;

    return new self($this->value, $nextLabel, $nextFamily, $nextArchived, $this->revision + ($changed ? 1 : 0));
  }

  /**
   * Method assertAvailable.
   *
   * Existing assignments may preserve an archived code; new assignments cannot.
   *
   * @access public
   *
   * @param string|null $existingType current equipment type, when updating
   *
   * @return void
   */
  public function assertAvailable(?string $existingType): void
  {
    if ($this->archived && $existingType !== $this->value) {
      throw new EquipmentTypeCatalogException('equipment_type_archived', 'This equipment type is archived.');
    }
  }
  // #endregion
}
