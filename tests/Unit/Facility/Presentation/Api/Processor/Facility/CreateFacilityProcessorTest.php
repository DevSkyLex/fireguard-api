<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Presentation\Api\Processor\Facility;

use ApiPlatform\Metadata\Post;
use Auth\Infrastructure\Security\User\SecurityUser;
use Facility\Application\UseCase\Command\Facility\CreateFacility\CreateFacilityCommand;
use Facility\Domain\Exception\FacilityCodeAlreadyExistsException;
use Facility\Presentation\Api\Dto\Input\Facility\CreateFacilityInput;
use Facility\Presentation\Api\Processor\Facility\CreateFacilityProcessor;
use Organization\Application\Port\Inbound\{OrganizationAuthorizationPort, OrganizationQuotaPort};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;

#[CoversClass(CreateFacilityProcessor::class)]
final class CreateFacilityProcessorTest extends TestCase
{
  #[Test]
  public function testProcessMapsWrappedCodeConflictToHttp409(): void
  {
    $input = new CreateFacilityInput();
    $input->type = 'site';
    $input->name = 'HQ';
    $input->code = 'SITE-001';

    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655441000');

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($user);

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('hasPermission')
      ->with($user->getId(), '550e8400-e29b-41d4-a716-446655441001', 'organization.facilities.write')
      ->willReturn(true);

    $domainException = FacilityCodeAlreadyExistsException::withCode('SITE-001');
    $handlerFailure = new HandlerFailedException(
      new Envelope(new CreateFacilityCommand(
        organizationId: '550e8400-e29b-41d4-a716-446655441001',
        type: 'site',
        name: 'HQ',
        code: 'SITE-001',
      )),
      [$domainException],
    );

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willThrowException(MessengerRuntimeException::wrap($handlerFailure));

    $processor = new CreateFacilityProcessor(
      commandBus: $commandBus,
      authorization: $authorization,
      quota: $this->createStub(OrganizationQuotaPort::class),
      security: $security,
    );

    $this->expectException(ConflictHttpException::class);
    $this->expectExceptionMessage('Facility code "SITE-001" already exists for this organization.');

    $processor->process(
      data: $input,
      operation: new Post(),
      uriVariables: ['organizationId' => '550e8400-e29b-41d4-a716-446655441001'],
    );
  }

  private function createSecurityUser(string $id): SecurityUser
  {
    return new SecurityUser(
      id: $id,
      email: 'user@example.com',
      password: 'hashed-password',
      roles: ['ROLE_USER'],
      scopes: [],
      isActive: true,
    );
  }
}
