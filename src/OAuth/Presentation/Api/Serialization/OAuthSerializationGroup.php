<?php

declare(strict_types=1);

namespace OAuth\Presentation\Api\Serialization;

/**
 * Class OAuthSerializationGroup.
 *
 * @category Serialization
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OAuthSerializationGroup
{
  // #region Constants
  /**
   * Constant TOKEN_READ.
   *
   * Group TOKEN_READ
   * Used for reading token data.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string TOKEN_READ = 'token:read';

  /**
   * Constant TOKEN_WRITE.
   *
   * Group TOKEN_WRITE
   * Used for writing/creating token data.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string TOKEN_WRITE = 'token:write';

  /**
   * Constant CONSENT_READ.
   *
   * Group CONSENT_READ
   * Used for reading consent data.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string CONSENT_READ = 'consent:read';

  /**
   * Constant CONSENT_WRITE.
   *
   * Group CONSENT_WRITE
   * Used for writing consent data.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string CONSENT_WRITE = 'consent:write';

  /**
   * Constant CLIENT_READ.
   *
   * Group CLIENT_READ
   * Used for reading client data.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string CLIENT_READ = 'client:read';

  /**
   * Constant CLIENT_WRITE.
   *
   * Group CLIENT_WRITE
   * Used for writing/creating client data.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string CLIENT_WRITE = 'client:write';

  /**
   * Constant CLIENT_UPDATE.
   *
   * Group CLIENT_UPDATE
   * Used for updating client data.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string CLIENT_UPDATE = 'client:update';

  /**
   * Constant CLIENT_SECRET.
   *
   * Group CLIENT_SECRET
   * Used for exposing client secret.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string CLIENT_SECRET = 'client:secret';
  // #endregion
}
