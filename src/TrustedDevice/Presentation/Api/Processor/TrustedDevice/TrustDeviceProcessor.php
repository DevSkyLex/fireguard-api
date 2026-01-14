<?php

declare(strict_types=1);

namespace TrustedDevice\Presentation\Api\Processor\TrustedDevice;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use TrustedDevice\Application\UseCase\Command\TrustedDevice\TrustDevice\{TrustDeviceCommand, TrustDeviceResult};
use TrustedDevice\Presentation\Api\Dto\Output\TrustedDevice\TrustDeviceOutput;
use TrustedDevice\Presentation\Api\EventSubscriber\TrustedDeviceCookieListener;
use TrustedDevice\Presentation\Api\Service\TrustedDeviceCookieService;

/**
 * Processor TrustDeviceProcessor.
 *
 * @implements ProcessorInterface<mixed, TrustDeviceOutput>
 */
final readonly class TrustDeviceProcessor implements ProcessorInterface
{
  public function __construct(
    private CommandBusPort $commandBus,
    private Security $security,
    private RequestStack $requestStack,
    private TrustedDeviceCookieService $cookieService,
  ) {
  }

  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): TrustDeviceOutput
  {
    $user = $this->security->getUser();
    if (null === $user) {
      throw new BadRequestHttpException('User must be authenticated.');
    }

    $request = $this->requestStack->getCurrentRequest();
    if (null === $request) {
      throw new BadRequestHttpException('Request not available.');
    }

    $command = new TrustDeviceCommand(
      userId: $user->getUserIdentifier(),
      userAgent: $request->headers->get('User-Agent', 'Unknown'),
      ipAddress: $request->getClientIp(),
      acceptLanguage: $request->headers->get('Accept-Language'),
    );

    /** @var TrustDeviceResult $result */
    $result = $this->commandBus->dispatch($command);

    // Create and store the cookie for the listener to add to the response
    $cookie = $this->cookieService->createCookie(
      token: $result->token,
      expiresAt: $result->expiresAt,
    );
    $request->attributes->set(
      key: TrustedDeviceCookieListener::REQUEST_ATTRIBUTE,
      value: $cookie,
    );

    $output = new TrustDeviceOutput();
    $output->deviceId = $result->deviceId;
    $output->token = $result->token;
    $output->deviceName = $result->deviceName;
    $output->expiresAt = $result->expiresAt;

    return $output;
  }
}
