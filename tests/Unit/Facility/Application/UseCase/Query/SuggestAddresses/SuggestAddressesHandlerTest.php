<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Application\UseCase\Query\SuggestAddresses;

use Facility\Application\Contract\Geocoding\AddressSuggestion;
use Facility\Application\Port\Outbound\AddressSuggestionsPort;
use Facility\Application\UseCase\Query\SuggestAddresses\{SuggestAddressesHandler, SuggestAddressesQuery};
use Facility\Domain\Exception\{FacilityAccessDeniedException, FacilityAddressSuggestionsUnavailableException, FacilityNotFoundException};
use Organization\Application\Contract\Authorization\OrganizationAccessDecision;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;

use function str_repeat;

/**
 * Test SuggestAddressesHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(SuggestAddressesHandler::class)]
final class SuggestAddressesHandlerTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655449401';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655449402';

  #[Test]
  public function testInvokeThrowsAccessDeniedWithoutTheFacilitiesWritePermission(): void
  {
    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('resolveAccess')
      ->with(self::USER_ID, self::ORG_ID, 'organization.facilities.write')
      ->willReturn(OrganizationAccessDecision::MISSING_PERMISSION);

    /** @var AddressSuggestionsPort&MockObject $geocoding */
    $geocoding = $this->createMock(AddressSuggestionsPort::class);
    $geocoding->expects(self::never())->method('suggest');

    $handler = new SuggestAddressesHandler(suggestions: $geocoding, authorization: $authorization);

    $this->expectException(FacilityAccessDeniedException::class);

    $handler->__invoke(new SuggestAddressesQuery(self::USER_ID, self::ORG_ID, '1 Rue de Paris'));
  }

  #[Test]
  public function testInvokeThrowsNotFoundWhenTheCallerIsOutsideTheOwningOrganization(): void
  {
    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('resolveAccess')
      ->with(self::USER_ID, self::ORG_ID, 'organization.facilities.write')
      ->willReturn(OrganizationAccessDecision::OUTSIDE_SCOPE);

    /** @var AddressSuggestionsPort&MockObject $geocoding */
    $geocoding = $this->createMock(AddressSuggestionsPort::class);
    $geocoding->expects(self::never())->method('suggest');

    $handler = new SuggestAddressesHandler(suggestions: $geocoding, authorization: $authorization);

    $this->expectException(FacilityNotFoundException::class);

    $handler->__invoke(new SuggestAddressesQuery(self::USER_ID, self::ORG_ID, '1 Rue de Paris'));
  }

  #[Test]
  public function testInvokeRejectsAWhitespaceOnlyAddressBeforeCallingTheProvider(): void
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);

    /** @var AddressSuggestionsPort&MockObject $geocoding */
    $geocoding = $this->createMock(AddressSuggestionsPort::class);
    $geocoding->expects(self::never())->method('suggest');

    $handler = new SuggestAddressesHandler(suggestions: $geocoding, authorization: $authorization);

    $this->expectException(InvalidValueException::class);

    $handler->__invoke(new SuggestAddressesQuery(self::USER_ID, self::ORG_ID, '   '));
  }

  #[Test]
  public function testInvokeRejectsAnAddressLongerThanTheCapBeforeCallingTheProvider(): void
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);

    /** @var AddressSuggestionsPort&MockObject $geocoding */
    $geocoding = $this->createMock(AddressSuggestionsPort::class);
    $geocoding->expects(self::never())->method('suggest');

    $handler = new SuggestAddressesHandler(suggestions: $geocoding, authorization: $authorization);

    $this->expectException(InvalidValueException::class);

    $handler->__invoke(new SuggestAddressesQuery(
      self::USER_ID,
      self::ORG_ID,
      str_repeat('a', SuggestAddressesHandler::MAX_ADDRESS_LENGTH + 1),
    ));
  }

  #[Test]
  public function testInvokePropagatesProviderUnavailability(): void
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);

    /** @var AddressSuggestionsPort&MockObject $geocoding */
    $geocoding = $this->createMock(AddressSuggestionsPort::class);
    $geocoding->expects(self::once())
      ->method('suggest')
      ->with('Nowhere Street 0')
      ->willThrowException(new FacilityAddressSuggestionsUnavailableException());

    $handler = new SuggestAddressesHandler(suggestions: $geocoding, authorization: $authorization);

    $this->expectException(FacilityAddressSuggestionsUnavailableException::class);

    $handler->__invoke(new SuggestAddressesQuery(self::USER_ID, self::ORG_ID, 'Nowhere Street 0'));
  }

  #[Test]
  public function testInvokeReturnsTheCoordinatesOnSuccessAndTrimsTheAddress(): void
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);

    /** @var AddressSuggestionsPort&MockObject $geocoding */
    $geocoding = $this->createMock(AddressSuggestionsPort::class);
    $geocoding->expects(self::once())
      ->method('suggest')
      ->with('1 Rue de Paris, 75001 Paris')
      ->willReturn([new AddressSuggestion(
        latitude: 48.8566,
        longitude: 2.3522,
        displayName: '1, Rue de Paris, 75001 Paris, France',
      )]);

    $handler = new SuggestAddressesHandler(suggestions: $geocoding, authorization: $authorization);

    $result = $handler->__invoke(new SuggestAddressesQuery(self::USER_ID, self::ORG_ID, '  1 Rue de Paris, 75001 Paris  '));

    self::assertSame(48.8566, $result->suggestions[0]->latitude);
    self::assertSame(2.3522, $result->suggestions[0]->longitude);
    self::assertSame('1, Rue de Paris, 75001 Paris, France', $result->suggestions[0]->displayName);
  }
}
