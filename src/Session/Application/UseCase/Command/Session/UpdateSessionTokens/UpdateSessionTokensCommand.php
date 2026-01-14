<?php

declare(strict_types=1);

namespace Session\Application\UseCase\Command\Session\UpdateSessionTokens;

use Shared\Application\Message\CommandMessage;

/**
 * Command UpdateSessionTokensCommand.
 *
 * @category Command
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UpdateSessionTokensCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $currentRefreshTokenId the current refresh token ID
   * @param string|null $currentAccessTokenId the current access token ID
   * @param string $newAccessTokenId the new access token ID
   * @param string $newRefreshTokenId the new refresh token ID
   */
  public function __construct(
    public string $currentRefreshTokenId,
    public ?string $currentAccessTokenId,
    public string $newAccessTokenId,
    public string $newRefreshTokenId,
  ) {
  }
  // #endregion
}
