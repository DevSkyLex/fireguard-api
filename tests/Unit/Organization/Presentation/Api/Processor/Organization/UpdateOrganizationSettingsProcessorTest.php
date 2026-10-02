<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Presentation\Api\Processor\Organization;

use ApiPlatform\Metadata\Patch;
use Auth\Infrastructure\Security\User\SecurityUser;
use LogicException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\Port\Outbound\EquipmentTypeCatalogPort;
use Organization\Application\UseCase\Command\Organization\UpdateOrganizationSettings\{UpdateOrganizationSettingsCommand, UpdateOrganizationSettingsResult};
use Organization\Application\UseCase\Query\Organization\GetOrganization\GetOrganizationQuery;
use Organization\Domain\Exception\{OrganizationArchivedException, OrganizationNotFoundException};
use Organization\Presentation\Api\Dto\Input\Organization\{
  UpdateOrganizationComplianceInput,
  UpdateOrganizationRegisteredAddressInput,
  UpdateOrganizationSettingsInput
};
use Organization\Presentation\Api\Processor\Organization\UpdateOrganizationSettingsProcessor;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\{CommandBusPort, QueryBusPort};
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, ConflictHttpException, NotFoundHttpException};
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;

use function json_encode;

use const JSON_THROW_ON_ERROR;

#[CoversClass(UpdateOrganizationSettingsProcessor::class)]
final class UpdateOrganizationSettingsProcessorTest extends TestCase
{
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655440010';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655440001';

  #[Test]
  public function testProcessThrowsConflictWhenOrganizationIsArchived(): void
  {
    $input = new UpdateOrganizationSettingsInput();
    $input->isActive = false;

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willThrowException(OrganizationArchivedException::cannotSuspend());

    $processor = $this->createProcessor($commandBus);

    $this->expectException(ConflictHttpException::class);

    $processor->process($input, new Patch(), ['id' => self::ORGANIZATION_ID], $this->requestContext($input));
  }

  #[Test]
  public function testProcessThrowsConflictWhenArchivedIsWrappedInMessengerRuntimeException(): void
  {
    $input = new UpdateOrganizationSettingsInput();
    $input->isActive = false;

    $archivedConflict = OrganizationArchivedException::cannotSuspend();
    $handlerFailure = new HandlerFailedException(
      new Envelope(new UpdateOrganizationSettingsCommand(organizationId: self::ORGANIZATION_ID, isActive: false)),
      [$archivedConflict],
    );

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willThrowException(MessengerRuntimeException::wrap($handlerFailure));

    $processor = $this->createProcessor($commandBus);

    $this->expectException(ConflictHttpException::class);

    $processor->process($input, new Patch(), ['id' => self::ORGANIZATION_ID], $this->requestContext($input));
  }

  #[Test]
  public function testProcessRejectsUnknownEquipmentTypeInPeriodicities(): void
  {
    $compliance = new UpdateOrganizationComplianceInput();
    $compliance->inspectionPeriodicityDefaults = ['teleporter' => 'P1Y'];

    $input = new UpdateOrganizationSettingsInput();
    $input->compliance = $compliance;

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())->method('dispatch');

    $processor = $this->createProcessor($commandBus);

    $this->expectException(BadRequestHttpException::class);
    $this->expectExceptionMessageMatches('/Unknown equipment type "teleporter"/');

    $processor->process($input, new Patch(), ['id' => self::ORGANIZATION_ID], $this->requestContext($input));
  }

  #[Test]
  public function testProcessPassesLegalProfileFieldsToCommand(): void
  {
    $input = new UpdateOrganizationSettingsInput();
    $input->country = 'FR';
    $input->legalType = 'limited_liability_company';
    $input->legalName = 'Fireguard Paris SARL';
    $input->registrationNumber = 'RCS PARIS 812345678';
    $input->vatNumber = 'FR12345678901';
    $input->registeredAddress = new UpdateOrganizationRegisteredAddressInput();
    $input->registeredAddress->city = 'Paris';
    $input->registeredAddress->countryCode = 'FR';
    $input->privacyContactEmail = 'privacy@example.com';

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static function (UpdateOrganizationSettingsCommand $command): bool {
        return 'FR' === $command->country
          && 'limited_liability_company' === $command->legalType
          && 'Fireguard Paris SARL' === $command->legalName
          && 'RCS PARIS 812345678' === $command->registrationNumber
          && 'FR12345678901' === $command->vatNumber
          && 'Paris' === ($command->registeredAddress['city'] ?? null)
          && 'FR' === ($command->registeredAddress['countryCode'] ?? null)
          && 'privacy@example.com' === $command->privacyContactEmail;
      }))
      ->willThrowException(OrganizationArchivedException::cannotSuspend());

    $processor = $this->createProcessor($commandBus);

    $this->expectException(ConflictHttpException::class);

    $processor->process($input, new Patch(), ['id' => self::ORGANIZATION_ID], $this->requestContext($input));
  }

  #[Test]
  public function testProcessThrowsNotFoundWhenTheRefreshedReadFailsWrappedInMessengerRuntimeException(): void
  {
    $input = new UpdateOrganizationSettingsInput();
    $input->name = 'Renamed organization';

    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willReturn(new UpdateOrganizationSettingsResult(self::ORGANIZATION_ID));

    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException(MessengerRuntimeException::wrap(new HandlerFailedException(
      new Envelope(new GetOrganizationQuery(self::ORGANIZATION_ID)),
      [OrganizationNotFoundException::withId(self::ORGANIZATION_ID)],
    )));

    $processor = $this->createProcessor($commandBus, $queryBus);

    $this->expectException(NotFoundHttpException::class);

    $processor->process($input, new Patch(), ['id' => self::ORGANIZATION_ID], $this->requestContext($input));
  }

  #[Test]
  #[DataProvider('invalidRequestBodies')]
  public function testProcessRejectsInvalidRawShapesBeforeDispatch(string $body): void
  {
    $input = new UpdateOrganizationSettingsInput();
    $input->registeredAddress = new UpdateOrganizationRegisteredAddressInput();
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())->method('dispatch');
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::never())->method('ask');

    $this->expectException(BadRequestHttpException::class);

    $this->createProcessor($commandBus, $queryBus)->process(
      $input,
      new Patch(),
      ['id' => self::ORGANIZATION_ID],
      ['request' => new Request(content: $body)],
    );
  }

  /**
   * @return iterable<string, array{string}>
   */
  public static function invalidRequestBodies(): iterable
  {
    yield 'false address' => ['{"registeredAddress":false}'];
    yield 'true address' => ['{"registeredAddress":true}'];
    yield 'integer address' => ['{"registeredAddress":0}'];
    yield 'string address' => ['{"registeredAddress":"Paris"}'];
    yield 'empty array address' => ['{"registeredAddress":[]}'];
    yield 'list address' => ['{"registeredAddress":["foo"]}'];
    yield 'unknown property' => ['{"registeredAddress":{"bogus":"foo"}}'];
    yield 'mixed properties' => ['{"registeredAddress":{"city":"Paris","bogus":"foo"}}'];
    yield 'array root' => ['[]'];
    yield 'null root' => ['null'];
    yield 'scalar root' => ['false'];
    yield 'malformed JSON' => ['{"registeredAddress":'];
  }

  #[Test]
  #[DataProvider('validRequestBodies')]
  public function testProcessAcceptsAbsentNullAndEmptyObjectAddresses(string $body, bool $hasAddress): void
  {
    $input = new UpdateOrganizationSettingsInput();
    if ($hasAddress) {
      $input->registeredAddress = new UpdateOrganizationRegisteredAddressInput();
    }
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())->method('dispatch')
      ->with(self::callback(static function (UpdateOrganizationSettingsCommand $command) use ($hasAddress): bool {
        return $hasAddress
          ? ['line1' => null, 'line2' => null, 'postalCode' => null, 'city' => null, 'region' => null, 'countryCode' => null] === $command->registeredAddress
          : null === $command->registeredAddress;
      }))
      ->willThrowException(OrganizationArchivedException::cannotSuspend());

    $this->expectException(ConflictHttpException::class);

    $this->createProcessor($commandBus)->process(
      $input,
      new Patch(),
      ['id' => self::ORGANIZATION_ID],
      ['request' => new Request(content: $body)],
    );
  }

  /**
   * @return iterable<string, array{string, bool}>
   */
  public static function validRequestBodies(): iterable
  {
    yield 'omitted address' => ['{}', false];
    yield 'null address' => ['{"registeredAddress":null}', false];
    yield 'empty object address' => ['{"registeredAddress":{}}', true];
    yield 'null components' => ['{"registeredAddress":{"city":null}}', true];
  }

  #[Test]
  public function testProcessFailsInternallyWithoutHttpRequestBeforeDispatch(): void
  {
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())->method('dispatch');

    $this->expectException(LogicException::class);

    $this->createProcessor($commandBus)->process(new UpdateOrganizationSettingsInput(), new Patch(), ['id' => self::ORGANIZATION_ID]);
  }

  #[Test]
  public function testProcessChecksPermissionBeforeInspectingTheRequest(): void
  {
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())->method('dispatch');

    $this->expectException(AccessDeniedHttpException::class);

    $this->createProcessor($commandBus, canWrite: false)->process(new UpdateOrganizationSettingsInput(), new Patch(), ['id' => self::ORGANIZATION_ID]);
  }

  /**
   * @return array{request: Request}
   */
  private function requestContext(UpdateOrganizationSettingsInput $input): array
  {
    return ['request' => new Request(content: json_encode($input, JSON_THROW_ON_ERROR))];
  }

  private function createProcessor(CommandBusPort $commandBus, ?QueryBusPort $queryBus = null, bool $canWrite = true): UpdateOrganizationSettingsProcessor
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser());

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn($canWrite);

    $equipmentTypeCatalog = $this->createStub(EquipmentTypeCatalogPort::class);
    $equipmentTypeCatalog->method('values')->willReturn(['fire_extinguisher', 'camera']);

    return new UpdateOrganizationSettingsProcessor(
      commandBus: $commandBus,
      queryBus: $queryBus ?? $this->createStub(QueryBusPort::class),
      authorization: $authorization,
      equipmentTypeCatalog: $equipmentTypeCatalog,
      security: $security,
    );
  }

  private function createSecurityUser(): SecurityUser
  {
    return new SecurityUser(
      id: self::USER_ID,
      email: 'user@example.com',
      password: 'hashed-password',
      roles: ['ROLE_USER'],
      scopes: [],
      isActive: true,
    );
  }
}
