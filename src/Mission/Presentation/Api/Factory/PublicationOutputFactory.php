<?php

declare(strict_types=1);

namespace Mission\Presentation\Api\Factory;

use Mission\Application\Contract\Publication\PublicationView;
use Mission\Presentation\Api\Dto\Output\PublicationOutput;

/**
 * Factory PublicationOutputFactory.
 *
 * @category Factory
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class PublicationOutputFactory
{
  /**
   * Method fromView.
   *
   * Executes the from view operation.
   *
   * @since 1.0.0
   *
   * @param PublicationView $publication the publication value
   *
   * @return PublicationOutput the from view result
   */
  public function fromView(PublicationView $publication): PublicationOutput
  {
    $output = new PublicationOutput();
    $output->id = $publication->id;
    $output->mission = '/api/missions/' . $publication->missionId;
    $output->missionRevision = $publication->missionRevision;
    $output->status = $publication->status;
    $output->error = $publication->error;
    $output->createdAt = $publication->createdAt->format('c');
    $output->completedAt = $publication->completedAt?->format('c');

    return $output;
  }
}
