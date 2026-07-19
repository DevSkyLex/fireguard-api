<?php

declare(strict_types=1);

namespace Intervention\Application\Contract\Template;

use DateTimeImmutable;

/**
 * Domain InterventionTemplateView.
 *
 * @category Domain
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class InterventionTemplateView
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $id the id value
   * @param string $organizationId the organization id value
   * @param string $name the name value
   * @param ?string $description the description value
   * @param string $type the intervention type value (site_setup|inventory|inspection_campaign)
   * @param string $priority the intervention priority value (low|normal|high|urgent)
   * @param ?string $defaultSiteId the default site id value
   * @param ?string $defaultResponsibleId the default responsible member id value
   * @param ?string $duration the ISO-8601 duration string (e.g. `P14D`), used to derive `dueAt` at instantiation
   * @param list<string> $labelIds the organization label ids to assign at instantiation
   * @param list<InterventionTemplateItemView> $items the template items, ordered by position
   * @param DateTimeImmutable $createdAt the created at value
   * @param DateTimeImmutable $updatedAt the updated at value
   */
  public function __construct(
    public string $id,
    public string $organizationId,
    public string $name,
    public ?string $description,
    public string $type,
    public string $priority,
    public ?string $defaultSiteId,
    public ?string $defaultResponsibleId,
    public ?string $duration,
    public array $labelIds,
    public array $items,
    public DateTimeImmutable $createdAt,
    public DateTimeImmutable $updatedAt,
  ) {
  }
}
