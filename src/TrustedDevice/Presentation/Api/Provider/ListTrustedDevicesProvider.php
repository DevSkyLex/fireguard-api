<?php

declare(strict_types=1);

namespace TrustedDevice\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use TrustedDevice\Application\UseCase\Query\ListTrustedDevices\ListTrustedDevicesHandler;
use TrustedDevice\Application\UseCase\Query\ListTrustedDevices\ListTrustedDevicesQuery;
use TrustedDevice\Presentation\Api\Dto\TrustedDeviceOutput;

/**
 * Provider ListTrustedDevicesProvider
 * @final
 *
 * @implements ProviderInterface<TrustedDeviceOutput>
 */
final readonly class ListTrustedDevicesProvider implements ProviderInterface
{
  public function __construct(
    private ListTrustedDevicesHandler $handler,
    private Security $security,
  ) {
  }

  /**
   * @return list<TrustedDeviceOutput>
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
  {
    $user = $this->security->getUser();
    if ($user === null) {
      throw new BadRequestHttpException('User must be authenticated.');
    }

    $query = new ListTrustedDevicesQuery(userId: $user->getUserIdentifier());
    $result = $this->handler->__invoke($query);

    $outputs = [];
    foreach ($result->devices as $device) {
      $output = new TrustedDeviceOutput();
      $output->id = $device->id;
      $output->name = $device->name;
      $output->lastUsedAt = $device->lastUsedAt;
      $output->expiresAt = $device->expiresAt;
      $output->createdAt = $device->createdAt;
      $outputs[] = $output;
    }

    return $outputs;
  }
}
