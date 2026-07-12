<?php

declare(strict_types=1);

namespace Intervention\Application\Port\Outbound;

/**
 * Interface InterventionDraftPublisherPort.
 *
 * @category Interface
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface InterventionDraftPublisherPort
{
  /**
   * Method publishDrafts.
   *
   * Executes the publish drafts operation.
   *
   * @since 1.0.0
   *
   * @param string $interventionId the intervention id value
   */
  public function publishDrafts(string $interventionId): void;

  /**
   * Method discardDrafts.
   *
   * Deletes the still-draft resource records created for an intervention that is
   * being abandoned or deleted, so no orphaned draft rows (and their unique
   * client ids) are left behind. Already-published resources are untouched.
   *
   * @since 1.0.0
   *
   * @param string $interventionId the intervention id value
   */
  public function discardDrafts(string $interventionId): void;
}
