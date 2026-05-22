<?php

declare(strict_types=1);

namespace Inspection\Presentation\Api\Provider\NonConformity;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Inspection\Domain\ValueObject\NonConformityStatus;
use Inspection\Presentation\Api\Dto\Output\Inspection\InspectionOptionOutput;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/** @implements ProviderInterface<InspectionOptionOutput> */
final readonly class ListNonConformityStatusesProvider implements ProviderInterface
{
  public function __construct(
    private Security $security,
  ) {
  }

  /**
   * @return list<InspectionOptionOutput>
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $outputs = [];
    foreach (NonConformityStatus::cases() as $status) {
      $output = new InspectionOptionOutput();
      $output->value = $status->value;
      $output->label = $status->label();
      $outputs[] = $output;
    }

    return $outputs;
  }
}
