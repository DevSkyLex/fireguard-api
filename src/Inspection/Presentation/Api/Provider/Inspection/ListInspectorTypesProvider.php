<?php

declare(strict_types=1);

namespace Inspection\Presentation\Api\Provider\Inspection;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Inspection\Domain\ValueObject\InspectorType;
use Inspection\Presentation\Api\Dto\Output\Inspection\InspectionOptionOutput;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/** @implements ProviderInterface<InspectionOptionOutput> */
final readonly class ListInspectorTypesProvider implements ProviderInterface
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
    foreach (InspectorType::cases() as $type) {
      $output = new InspectionOptionOutput();
      $output->value = $type->value;
      $output->label = $type->label();
      $outputs[] = $output;
    }

    return $outputs;
  }
}
