<?php

declare(strict_types=1);

namespace User\Presentation\Api\Provider\User;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use User\Domain\ValueObject\UserStatus;
use User\Presentation\Api\Dto\Output\User\UserStatusOptionOutput;

/** @implements ProviderInterface<UserStatusOptionOutput> */
final readonly class ListUserStatusesProvider implements ProviderInterface
{
  public function __construct(
    private Security $security,
  ) {
  }

  /**
   * @return list<UserStatusOptionOutput>
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
  {
    $caller = $this->security->getUser();
    if (!$caller instanceof SecurityUser) {
      throw new UnauthorizedHttpException('Bearer', 'Authentication required.');
    }

    $outputs = [];
    foreach (UserStatus::cases() as $status) {
      $output = new UserStatusOptionOutput();
      $output->value = $status->value;
      $output->label = $status->label();
      $outputs[] = $output;
    }

    return $outputs;
  }
}
