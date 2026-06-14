<?php

declare(strict_types=1);

namespace Intervention\Application\Port\Outbound;

use Intervention\Application\Contract\Publication\{InterventionPublicationContext, PublicationView};

/**
 * Interface PublicationRepositoryPort.
 *
 * @category Interface
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface PublicationRepositoryPort
{
  /**
   * Method interventionContext.
   *
   * Executes the intervention context operation.
   *
   * @since 1.0.0
   *
   * @param string $interventionId the intervention id value
   *
   * @return ?InterventionPublicationContext the intervention context result
   */
  public function interventionContext(string $interventionId): ?InterventionPublicationContext;

  /**
   * Method find.
   *
   * Executes the find operation.
   *
   * @since 1.0.0
   *
   * @param string $publicationId the publication id value
   *
   * @return ?PublicationView the find result
   */
  public function find(string $publicationId): ?PublicationView;

  /**
   * Method findByInterventionRevision.
   *
   * Executes the find by intervention revision operation.
   *
   * @since 1.0.0
   *
   * @param string $interventionId the intervention id value
   * @param int $interventionRevision the intervention revision value
   *
   * @return ?PublicationView the find by intervention revision result
   */
  public function findByInterventionRevision(string $interventionId, int $interventionRevision): ?PublicationView;

  /**
   * Method createOrGetPending.
   *
   * Executes the create or get pending operation.
   *
   * @since 1.0.0
   *
   * @param string $publicationId the publication id value
   * @param string $interventionId the intervention id value
   * @param int $interventionRevision the intervention revision value
   *
   * @return PublicationView the create or get pending result
   */
  public function createOrGetPending(string $publicationId, string $interventionId, int $interventionRevision): PublicationView;

  /**
   * Method retryFailed.
   *
   * Executes the retry failed operation.
   *
   * @since 1.0.0
   *
   * @param string $publicationId the publication id value
   *
   * @return PublicationView the retry failed result
   */
  public function retryFailed(string $publicationId): PublicationView;

  /**
   * Method markProcessing.
   *
   * Executes the mark processing operation.
   *
   * @since 1.0.0
   *
   * @param string $publicationId the publication id value
   */
  public function markProcessing(string $publicationId): void;

  /**
   * Method publish.
   *
   * Executes the publish operation.
   *
   * @since 1.0.0
   *
   * @param string $publicationId the publication id value
   */
  public function publish(string $publicationId): void;

  /**
   * Method markFailed.
   *
   * Executes the mark failed operation.
   *
   * @since 1.0.0
   *
   * @param string $publicationId the publication id value
   * @param string $error the error value
   */
  public function markFailed(string $publicationId, string $error): void;
}
