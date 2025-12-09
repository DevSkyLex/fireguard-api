<?php

declare(strict_types=1);

namespace TrustedDevice\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use TrustedDevice\Application\UseCase\Command\TrustDevice\TrustDeviceCommand;
use TrustedDevice\Application\UseCase\Command\TrustDevice\TrustDeviceHandler;
use TrustedDevice\Presentation\Api\Dto\TrustDeviceOutput;

/**
 * Processor TrustDeviceProcessor
 * @final
 *
 * @implements ProcessorInterface<mixed, TrustDeviceOutput>
 */
final readonly class TrustDeviceProcessor implements ProcessorInterface
{
  public function __construct(
    private TrustDeviceHandler $handler,
    private Security $security,
    private RequestStack $requestStack,
  ) {
  }

  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): TrustDeviceOutput
  {
    $user = $this->security->getUser();
    if ($user === null) {
      throw new BadRequestHttpException('User must be authenticated.');
    }

    $request = $this->requestStack->getCurrentRequest();
    if ($request === null) {
      throw new BadRequestHttpException('Request not available.');
    }

    $command = new TrustDeviceCommand(
      userId: $user->getUserIdentifier(),
      userAgent: $request->headers->get('User-Agent', 'Unknown'),
      ipAddress: $request->getClientIp(),
      acceptLanguage: $request->headers->get('Accept-Language'),
    );

    $result = $this->handler->__invoke($command);

    $output = new TrustDeviceOutput();
    $output->deviceId = $result->deviceId;
    $output->token = $result->token;
    $output->deviceName = $result->deviceName;
    $output->expiresAt = $result->expiresAt;

    return $output;
  }
}
