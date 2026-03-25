<?php

declare(strict_types=1);

namespace Inspection\Presentation\Api\Provider\Inspection;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Inspection\Domain\ValueObject\InspectionResult;
use Inspection\Presentation\Api\Dto\Output\Inspection\InspectionOptionOutput;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/** @implements ProviderInterface<InspectionOptionOutput> */
final readonly class ListInspectionResultsProvider implements ProviderInterface
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
    foreach (InspectionResult::cases() as $result) {
      $output = new InspectionOptionOutput();
      $output->value = $result->value;
      $output->label = $result->label();
      $outputs[] = $output;
    }

    return $outputs;
  }
}
