<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Presentation\Api\Processor\Facility;

use ApiPlatform\Metadata\Patch;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Facility\Application\UseCase\Command\Facility\RestoreFacility\{RestoreFacilityCommand, RestoreFacilityResult};
use Facility\Domain\Exception\{FacilityArchivedException, FacilityNotFoundException};
use Facility\Presentation\Api\Processor\Facility\RestoreFacilityProcessor;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{BadRequestHttpException, NotFoundHttpException};
use Throwable;

#[CoversClass(RestoreFacilityProcessor::class)]
final class RestoreFacilityProcessorTest extends TestCase
{
  #[Test]
  public function testProcessMapsResultToOutput(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655441260';
    $facilityId = '550e8400-e29b-41d4-a716-446655441261';
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655441262');

    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($user);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::isInstanceOf(RestoreFacilityCommand::class))
      ->willReturn(new RestoreFacilityResult(
        facilityId: $facilityId,
        organizationId: $organizationId,
        parentFacilityId: null,
        type: 'site',
        name: 'HQ',
        code: 'SITE-001',
        status: 'active',
        address: null,
        metadata: [],
        createdAt: new DateTimeImmutable('2026-02-12T10:00:00+00:00'),
        updatedAt: new DateTimeImmutable('2026-02-12T11:00:00+00:00'),
      ));

    $processor = new RestoreFacilityProcessor(
      commandBus: $commandBus,
      authorization: $authorization,
      security: $security,
    );

    $output = $processor->process(
      data: null,
      operation: new Patch(),
      uriVariables: ['organizationId' => $organizationId, 'facilityId' => $facilityId],
    );

    self::assertSame('active', $output->status);
  }

  #[Test]
  public function testProcessMapsNotFoundToHttp404(): void
  {
    $processor = $this->makeProcessor(FacilityNotFoundException::withId('550e8400-e29b-41d4-a716-446655441271'));

    $this->expectException(NotFoundHttpException::class);

    $processor->process(
      data: null,
      operation: new Patch(),
      uriVariables: ['organizationId' => '550e8400-e29b-41d4-a716-446655441270', 'facilityId' => '550e8400-e29b-41d4-a716-446655441271'],
    );
  }

  #[Test]
  public function testProcessMapsArchivedParentToBadRequest(): void
  {
    $processor = $this->makeProcessor(MessengerRuntimeException::wrap(FacilityArchivedException::withId('550e8400-e29b-41d4-a716-446655441281')));

    $this->expectException(BadRequestHttpException::class);

    $processor->process(
      data: null,
      operation: new Patch(),
      uriVariables: ['organizationId' => '550e8400-e29b-41d4-a716-446655441280', 'facilityId' => '550e8400-e29b-41d4-a716-446655441281'],
    );
  }

  private function makeProcessor(Throwable $exception): RestoreFacilityProcessor
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441282'));

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException($exception);

    return new RestoreFacilityProcessor(
      commandBus: $commandBus,
      authorization: $authorization,
      security: $security,
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
