<?php

declare(strict_types=1);

namespace Inspection\Domain\Model\Checklist;

use InvalidArgumentException;

use function mb_strlen;
use function sprintf;
use function trim;

/**
 * Model ChecklistItem.
 *
 * A single point to verify within a checklist.
 *
 * @category Model
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ChecklistItem
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $id the item identifier
   * @param string $label the item label
   * @param int $position the display order
   * @param bool $required whether the item is required
   * @param ?string $description optional detailed description
   */
  private function __construct(
    private string $id,
    private string $label,
    private int $position,
    private bool $required,
    private ?string $description = null,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method create.
   *
   * Creates a new checklist item.
   *
   * @since 1.0.0
   *
   * @param string $id the item identifier
   * @param string $label the item label
   * @param int $position the display order
   * @param bool $required whether the item is required
   * @param ?string $description optional detailed description
   *
   * @return self the created checklist item
   */
  public static function create(
    string $id,
    string $label,
    int $position,
    bool $required = true,
    ?string $description = null,
  ): self {
    $normalizedLabel = trim($label);
    if ('' === $normalizedLabel) {
      throw new InvalidArgumentException('Checklist item label must not be empty.');
    }

    if (mb_strlen($normalizedLabel) > 255) {
      throw new InvalidArgumentException(
        sprintf('Checklist item label must be at most %d characters.', 255),
      );
    }

    $normalizedDesc = null;
    if (null !== $description) {
      $normalizedDesc = trim($description);
      if ('' === $normalizedDesc) {
        $normalizedDesc = null;
      } elseif (mb_strlen($normalizedDesc) > 1000) {
        throw new InvalidArgumentException(
          sprintf('Checklist item description must be at most %d characters.', 1000),
        );
      }
    }

    if ($position < 0) {
      throw new InvalidArgumentException('Checklist item position must be non-negative.');
    }

    return new self(
      id: $id,
      label: $normalizedLabel,
      position: $position,
      required: $required,
      description: $normalizedDesc,
    );
  }

  /**
   * Method reconstitute.
   *
   * Reconstitutes a checklist item from persisted state.
   *
   * @since 1.0.0
   *
   * @param string $id the item identifier
   * @param string $label the item label
   * @param int $position the display order
   * @param bool $required whether the item is required
   * @param ?string $description optional detailed description
   *
   * @return self the reconstituted checklist item
   */
  public static function reconstitute(
    string $id,
    string $label,
    int $position,
    bool $required,
    ?string $description = null,
  ): self {
    return new self(
      id: $id,
      label: $label,
      position: $position,
      required: $required,
      description: $description,
    );
  }

  /**
   * Method id.
   *
   * @since 1.0.0
   */
  public function id(): string
  {
    return $this->id;
  }

  /**
   * Method label.
   *
   * @since 1.0.0
   */
  public function label(): string
  {
    return $this->label;
  }

  /**
   * Method position.
   *
   * @since 1.0.0
   */
  public function position(): int
  {
    return $this->position;
  }

  /**
   * Method required.
   *
   * @since 1.0.0
   */
  public function required(): bool
  {
    return $this->required;
  }

  /**
   * Method description.
   *
   * @since 1.0.0
   */
  public function description(): ?string
  {
    return $this->description;
  }
  // #endregion
}
