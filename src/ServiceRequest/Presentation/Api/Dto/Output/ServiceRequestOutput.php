<?php

declare(strict_types=1);

namespace ServiceRequest\Presentation\Api\Dto\Output;

use ServiceRequest\Application\Contract\ServiceRequestView;
use Symfony\Component\Serializer\Attribute\Groups;

use const DATE_ATOM;

/** Class ServiceRequestOutput. Internal repair request and retained minimal target context. @category Output */
final class ServiceRequestOutput
{
  /**
   * Property id.
   */
  #[Groups(['service_request:read'])]
  public string $id = '';

  /**
   * Property organizationId.
   */
  #[Groups(['service_request:read'])]
  public string $organizationId = '';

  /**
   * Property equipmentId.
   */
  #[Groups(['service_request:read'])]
  public ?string $equipmentId = null;

  /**
   * Property siteId.
   */
  #[Groups(['service_request:read'])]
  public ?string $siteId = null;

  /**
   * Property title.
   */
  #[Groups(['service_request:read'])]
  public string $title = '';

  /**
   * Property description.
   */
  #[Groups(['service_request:read'])]
  public string $description = '';

  /**
   * Property priority.
   */
  #[Groups(['service_request:read'])]
  public string $priority = 'normal';

  /**
   * Property status.
   */
  #[Groups(['service_request:read'])]
  public string $status = 'requested';

  /**
   * Property originInspectionId.
   */
  #[Groups(['service_request:read'])]
  public ?string $originInspectionId = null;

  /**
   * Property originNonConformityId.
   */
  #[Groups(['service_request:read'])]
  public ?string $originNonConformityId = null;

  /**
   * Property qualificationNote.
   */
  #[Groups(['service_request:read'])]
  public ?string $qualificationNote = null;

  /**
   * Property decisionReason.
   */
  #[Groups(['service_request:read'])]
  public ?string $decisionReason = null;

  /**
   * Property interventionId.
   */
  #[Groups(['service_request:read'])]
  public ?string $interventionId = null;

  /**
   * Property taskId.
   */
  #[Groups(['service_request:read'])]
  public ?string $taskId = null;

  /**
   * Property requestedAt.
   */
  #[Groups(['service_request:read'])]
  public string $requestedAt = '';

  /**
   * Property updatedAt.
   */
  #[Groups(['service_request:read'])]
  public string $updatedAt = '';

  /**
   * Property qualifiedAt.
   */
  #[Groups(['service_request:read'])]
  public ?string $qualifiedAt = null;

  /**
   * Property rejectedAt.
   */
  #[Groups(['service_request:read'])]
  public ?string $rejectedAt = null;

  /**
   * Property cancelledAt.
   */
  #[Groups(['service_request:read'])]
  public ?string $cancelledAt = null;

  /**
   * Property convertedAt.
   */
  #[Groups(['service_request:read'])]
  public ?string $convertedAt = null;

  /**
   * Property revision.
   */
  #[Groups(['service_request:read'])]
  public int $revision = 1;

  /**
   * @var array{equipment:?array{id:string,name:?string,assetCode:?string,status:?string},site:?array{id:string,name:string},customer:?array{id:string,name:string}}
   */
  #[Groups(['service_request:read'])]
  public array $targetSnapshot = ['equipment' => null, 'site' => null, 'customer' => null];

  public static function fromView(ServiceRequestView $view): self
  {
    $output = new self();
    $output->id = $view->id;
    $output->organizationId = $view->organizationId;
    $output->equipmentId = $view->equipmentId;
    $output->siteId = $view->siteId;
    $output->title = $view->title;
    $output->description = $view->description;
    $output->priority = $view->priority;
    $output->status = $view->status;
    $output->originInspectionId = $view->originInspectionId;
    $output->originNonConformityId = $view->originNonConformityId;
    $output->qualificationNote = $view->qualificationNote;
    $output->decisionReason = $view->decisionReason;
    $output->interventionId = $view->interventionId;
    $output->taskId = $view->taskId;
    $output->revision = $view->revision;
    $output->targetSnapshot = $view->targetSnapshot;
    $output->requestedAt = $view->requestedAt->format(DATE_ATOM);
    $output->updatedAt = $view->updatedAt->format(DATE_ATOM);
    $output->qualifiedAt = $view->qualifiedAt?->format(DATE_ATOM);
    $output->rejectedAt = $view->rejectedAt?->format(DATE_ATOM);
    $output->cancelledAt = $view->cancelledAt?->format(DATE_ATOM);
    $output->convertedAt = $view->convertedAt?->format(DATE_ATOM);

    return $output;
  }
}
