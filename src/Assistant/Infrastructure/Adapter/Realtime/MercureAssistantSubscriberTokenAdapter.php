<?php

declare(strict_types=1);

namespace Assistant\Infrastructure\Adapter\Realtime;

use Assistant\Application\Port\Outbound\Internal\AssistantSubscriberTokenPort;
use DateTimeImmutable;
use Symfony\Component\Mercure\Jwt\TokenFactoryInterface;

use function sprintf;

/**
 * Class MercureAssistantSubscriberTokenAdapter
 *
 * Hides JWT creation and expiry representation behind a scalar owner-local token capability.
 *
 * @category Adapter
 */
final readonly class MercureAssistantSubscriberTokenAdapter implements AssistantSubscriberTokenPort
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param TokenFactoryInterface $defaultFactory configured Mercure token signer
   * @param int $tokenTtl subscriber lifetime in seconds
   *
   * @return void
   */
  public function __construct(private TokenFactoryInterface $defaultFactory, private int $tokenTtl = 900)
  {
  }
  // #endregion

  // #region Methods
  /**
   * {@inheritdoc}
   */
  public function create(string $topic): string
  {
    return $this->defaultFactory->create(
      subscribe: [$topic],
      publish: [],
      additionalClaims: ['exp' => new DateTimeImmutable(sprintf('+%d seconds', $this->tokenTtl))],
    );
  }
  // #endregion
}
