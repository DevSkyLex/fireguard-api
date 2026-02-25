<?php

declare(strict_types=1);

namespace Notification\Presentation\Api\Provider\MercureSubscription;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Notification\Presentation\Api\Dto\Output\MercureSubscription\MercureSubscriptionOutput;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Mercure\Jwt\TokenFactoryInterface;

use function sprintf;

/**
 * Provider GetMercureSubscriptionProvider.
 *
 * Generates a Mercure subscriber JWT restricted to the authenticated
 * user's private notification topic, enabling the client to open a
 * Server-Sent Events connection and receive private updates.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<MercureSubscriptionOutput>
 */
final readonly class GetMercureSubscriptionProvider implements ProviderInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param Security $security the security service
   * @param TokenFactoryInterface $defaultFactory the Mercure JWT token factory
   * @param string $topicPrefix the Mercure topic prefix (default: /users)
   */
  public function __construct(
    private Security $security,
    private TokenFactoryInterface $defaultFactory,
    private string $topicPrefix = '/users',
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method provide.
   *
   * @since 1.0.0
   *
   * @param Operation $operation the operation metadata
   * @param array<string, mixed> $uriVariables URI variables
   * @param array<string, mixed> $context provider context
   *
   * @return MercureSubscriptionOutput the subscription output
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): MercureSubscriptionOutput
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $topic = sprintf('%s/%s/notifications', $this->topicPrefix, $user->getId());

    $token = $this->defaultFactory->create(
      subscribe: [$topic],
      publish: [],
    );

    $output = new MercureSubscriptionOutput();
    $output->token = $token;
    $output->topic = $topic;

    return $output;
  }
  // #endregion
}
