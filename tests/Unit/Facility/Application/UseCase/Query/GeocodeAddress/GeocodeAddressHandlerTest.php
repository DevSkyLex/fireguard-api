<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Application\UseCase\Query\GeocodeAddress;

use Facility\Application\Contract\Geocoding\GeocodingResult;
use Facility\Application\Port\Outbound\GeocodingPort;
use Facility\Application\UseCase\Query\GeocodeAddress\{GeocodeAddressHandler, GeocodeAddressQuery};
use Facility\Domain\Exception\{FacilityAccessDeniedException, FacilityAddressNotFoundException, FacilityNotFoundException};
use Organization\Application\Contract\Authorization\OrganizationAccessDecision;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;

use function str_repeat;

/**
 * Test GeocodeAddressHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(GeocodeAddressHandler::class)]
final class GeocodeAddressHandlerTest extends TestCase
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

    /** @var GeocodingPort&MockObject $geocoding */
    $geocoding = $this->createMock(GeocodingPort::class);
    $geocoding->expects(self::never())->method('geocode');

    $handler = new GeocodeAddressHandler(geocoding: $geocoding, authorization: $authorization);

    $this->expectException(FacilityAccessDeniedException::class);

    $handler->__invoke(new GeocodeAddressQuery(self::USER_ID, self::ORG_ID, '1 Rue de Paris'));
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

    /** @var GeocodingPort&MockObject $geocoding */
    $geocoding = $this->createMock(GeocodingPort::class);
    $geocoding->expects(self::never())->method('geocode');

    $handler = new GeocodeAddressHandler(geocoding: $geocoding, authorization: $authorization);

    $this->expectException(FacilityNotFoundException::class);

    $handler->__invoke(new GeocodeAddressQuery(self::USER_ID, self::ORG_ID, '1 Rue de Paris'));
  }

  #[Test]
  public function testInvokeRejectsAWhitespaceOnlyAddressBeforeCallingTheProvider(): void
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);

    /** @var GeocodingPort&MockObject $geocoding */
    $geocoding = $this->createMock(GeocodingPort::class);
    $geocoding->expects(self::never())->method('geocode');

    $handler = new GeocodeAddressHandler(geocoding: $geocoding, authorization: $authorization);

    $this->expectException(InvalidValueException::class);

    $handler->__invoke(new GeocodeAddressQuery(self::USER_ID, self::ORG_ID, '   '));
  }

  #[Test]
  public function testInvokeRejectsAnAddressLongerThanTheCapBeforeCallingTheProvider(): void
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);

    /** @var GeocodingPort&MockObject $geocoding */
    $geocoding = $this->createMock(GeocodingPort::class);
    $geocoding->expects(self::never())->method('geocode');

    $handler = new GeocodeAddressHandler(geocoding: $geocoding, authorization: $authorization);

    $this->expectException(InvalidValueException::class);

    $handler->__invoke(new GeocodeAddressQuery(
      self::USER_ID,
      self::ORG_ID,
      str_repeat('a', GeocodeAddressHandler::MAX_ADDRESS_LENGTH + 1),
    ));
  }

  #[Test]
  public function testInvokeThrowsAddressNotFoundWhenTheProviderKnowsNoMatch(): void
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);

    /** @var GeocodingPort&MockObject $geocoding */
    $geocoding = $this->createMock(GeocodingPort::class);
    $geocoding->expects(self::once())
      ->method('geocode')
      ->with('Nowhere Street 0')
      ->willReturn(null);

    $handler = new GeocodeAddressHandler(geocoding: $geocoding, authorization: $authorization);

    $this->expectException(FacilityAddressNotFoundException::class);

    $handler->__invoke(new GeocodeAddressQuery(self::USER_ID, self::ORG_ID, 'Nowhere Street 0'));
  }

  #[Test]
  public function testInvokeReturnsTheCoordinatesOnSuccessAndTrimsTheAddress(): void
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);

    /** @var GeocodingPort&MockObject $geocoding */
    $geocoding = $this->createMock(GeocodingPort::class);
    $geocoding->expects(self::once())
      ->method('geocode')
      ->with('1 Rue de Paris, 75001 Paris')
      ->willReturn(new GeocodingResult(
        latitude: 48.8566,
        longitude: 2.3522,
        displayName: '1, Rue de Paris, 75001 Paris, France',
        confidence: 0.87,
      ));

    $handler = new GeocodeAddressHandler(geocoding: $geocoding, authorization: $authorization);

    $result = $handler->__invoke(new GeocodeAddressQuery(self::USER_ID, self::ORG_ID, '  1 Rue de Paris, 75001 Paris  '));

    self::assertSame(48.8566, $result->latitude);
    self::assertSame(2.3522, $result->longitude);
    self::assertSame('1, Rue de Paris, 75001 Paris, France', $result->displayName);
  }
}
