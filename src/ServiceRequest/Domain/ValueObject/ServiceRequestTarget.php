<?php

declare(strict_types=1);

namespace ServiceRequest\Domain\ValueObject;

/**
 * Class ServiceRequestTarget
 *
 * Retains target identity and the inspection evidence from which the request originated.
 * The aggregate validates new references and isolates snapshot JSON before retaining them.
 *
 * @category ValueObject
 */
final readonly class ServiceRequestTarget
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param string|null $equipmentId selected equipment, absent until site-only qualification
   * @param string|null $siteId original root site or explicit reserve equipment absence
   * @param array<string,mixed> $snapshot owner-published retained identity
   * @param string|null $originInspectionId original inspection evidence
   * @param string|null $originNonConformityId original non-conformity evidence
   *
   * @return void
   */
  public function __construct(public ?string $equipmentId, public ?string $siteId, public array $snapshot, public ?string $originInspectionId = null, public ?string $originNonConformityId = null)
  {
  }
  // #endregion
}
