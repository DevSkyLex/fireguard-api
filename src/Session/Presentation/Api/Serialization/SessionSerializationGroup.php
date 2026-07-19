<?php

declare(strict_types=1);

namespace Session\Presentation\Api\Serialization;

/**
 * Class SessionSerializationGroup.
 *
 * @category Serialization
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class SessionSerializationGroup
{
  /**
   * Group READ.
   *
   * Used for reading session data.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string READ = 'session:read';

  /**
   * Group REVOKE_OTHERS.
   *
   * Used for the revoke-others-sessions result view.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string REVOKE_OTHERS = 'session:revoke_others';
}
