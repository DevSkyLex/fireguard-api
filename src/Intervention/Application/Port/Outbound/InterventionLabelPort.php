<?php

declare(strict_types=1);

namespace Intervention\Application\Port\Outbound;

use Intervention\Application\Contract\Label\{InterventionLabelPage, InterventionLabelView};

/**
 * Interface InterventionLabelPort.
 *
 * Persists and reads organization-scoped intervention labels: small,
 * reusable `{name, color}` tags interventions can be assigned. Labels are
 * record-level metadata, not part of the `Intervention` domain aggregate.
 *
 * @category Interface
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface InterventionLabelPort
{
  /**
   * Method create.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization id value
   * @param string $name the name value
   * @param string $color the color value
   *
   * @return InterventionLabelView the created label view
   */
  public function create(string $organizationId, string $name, string $color): InterventionLabelView;

  /**
   * Method update.
   *
   * @since 1.0.0
   *
   * @param string $id the label id value
   * @param ?string $name the name value, only applied when `$hasName` is true
   * @param ?string $color the color value, only applied when `$hasColor` is true
   * @param bool $hasName whether the name field was present in the request
   * @param bool $hasColor whether the color field was present in the request
   *
   * @return InterventionLabelView the updated label view
   */
  public function update(string $id, ?string $name, ?string $color, bool $hasName, bool $hasColor): InterventionLabelView;

  /**
   * Method delete.
   *
   * @since 1.0.0
   *
   * @param string $id the label id value
   */
  public function delete(string $id): void;

  /**
   * Method find.
   *
   * @since 1.0.0
   *
   * @param string $id the label id value
   *
   * @return ?InterventionLabelView the label view, or null when not found
   */
  public function find(string $id): ?InterventionLabelView;

  /**
   * Method list.
   *
   * Lists an organization's labels, ordered by name.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization id value
   * @param int $page the page value
   * @param int $itemsPerPage the items per page value
   *
   * @return InterventionLabelPage the label page result
   */
  public function list(string $organizationId, int $page, int $itemsPerPage): InterventionLabelPage;
}
