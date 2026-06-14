<?php

declare(strict_types=1);

namespace Mission\Application\Port\Outbound;

use Mission\Application\Contract\Publication\{MissionPublicationContext, PublicationView};

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
   * Method missionContext.
   *
   * Executes the mission context operation.
   *
   * @since 1.0.0
   *
   * @param string $missionId the mission id value
   *
   * @return ?MissionPublicationContext the mission context result
   */
  public function missionContext(string $missionId): ?MissionPublicationContext;

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
   * Method findByMissionRevision.
   *
   * Executes the find by mission revision operation.
   *
   * @since 1.0.0
   *
   * @param string $missionId the mission id value
   * @param int $missionRevision the mission revision value
   *
   * @return ?PublicationView the find by mission revision result
   */
  public function findByMissionRevision(string $missionId, int $missionRevision): ?PublicationView;

  /**
   * Method createOrGetPending.
   *
   * Executes the create or get pending operation.
   *
   * @since 1.0.0
   *
   * @param string $publicationId the publication id value
   * @param string $missionId the mission id value
   * @param int $missionRevision the mission revision value
   *
   * @return PublicationView the create or get pending result
   */
  public function createOrGetPending(string $publicationId, string $missionId, int $missionRevision): PublicationView;

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
