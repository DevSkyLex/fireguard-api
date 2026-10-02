<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Presentation\Api\Dto\Input\Organization;

use ApiPlatform\Metadata\Property\Factory\{AttributePropertyMetadataFactory, PropertyInfoPropertyMetadataFactory};
use ApiPlatform\Symfony\Validator\Metadata\Property\Restriction\{PropertySchemaFormat, PropertySchemaLengthRestriction};
use ApiPlatform\Symfony\Validator\Metadata\Property\ValidatorPropertyMetadataFactory;
use Organization\Presentation\Api\Dto\Input\Organization\{UpdateOrganizationRegisteredAddressInput, UpdateOrganizationSettingsInput};
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\Validator\Mapping\Factory\LazyLoadingMetadataFactory;
use Symfony\Component\Validator\Mapping\Loader\AttributeLoader;
use Symfony\Component\Validator\Validation;

use function str_repeat;

#[CoversClass(UpdateOrganizationSettingsInput::class)]
#[CoversClass(UpdateOrganizationRegisteredAddressInput::class)]
final class UpdateOrganizationLegalProfileInputTest extends TestCase
{
  #[Test]
  public function testOptionalEmptyAndPartialComponentsAreValid(): void
  {
    $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
    $input = new UpdateOrganizationSettingsInput();
    self::assertCount(0, $validator->validate($input));
    $input->registeredAddress = new UpdateOrganizationRegisteredAddressInput();
    $input->registeredAddress->city = 'Lyon';
    $input->registeredAddress->countryCode = ' fr ';
    $input->privacyContactEmail = ' privacy@example.com ';
    self::assertCount(0, $validator->validate($input));
    $input->privacyContactEmail = '';
    self::assertCount(0, $validator->validate($input));
  }

  #[Test]
  public function testPrivacyContactSchemaPreservesClearAndTrimInputsWithoutAnEmailFormat(): void
  {
    $metadata = new ValidatorPropertyMetadataFactory(
      new LazyLoadingMetadataFactory(new AttributeLoader()),
      new AttributePropertyMetadataFactory(),
      [new PropertySchemaFormat()],
    );
    $property = $metadata->create(UpdateOrganizationSettingsInput::class, 'privacyContactEmail');
    $schema = $property->getSchema();
    self::assertNotNull($schema);
    self::assertSame(['string', 'null'], $schema['type'] ?? null);
    self::assertArrayNotHasKey('format', $schema);
    $description = $schema['description'] ?? null;
    self::assertIsString($description);
    self::assertStringContainsString('254 characters after trimming', $description);
    self::assertStringContainsString('null or omission leaves it unchanged', $description);
    self::assertStringContainsString('an empty string clears it', $description);
    self::assertFalse($property->isRequired());

    $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
    foreach (['', ' privacy@example.com ', 'privacy@example.com', null] as $value) {
      $input = new UpdateOrganizationSettingsInput();
      $input->privacyContactEmail = $value;
      self::assertCount(0, $validator->validate($input));
    }
    $legacyInput = new UpdateOrganizationSettingsInput();
    $legacyInput->name = 'Existing organization';
    self::assertCount(0, $validator->validate($legacyInput));
    $invalidInput = new UpdateOrganizationSettingsInput();
    $invalidInput->privacyContactEmail = ' invalid ';
    self::assertCount(1, $validator->validate($invalidInput));
  }

  #[Test]
  #[DataProvider('addressLengthLimits')]
  public function testAddressSchemaDocumentsTrimmedLimitsWithoutRejectingRawPadding(string $field, int $limit): void
  {
    $metadata = new ValidatorPropertyMetadataFactory(
      new LazyLoadingMetadataFactory(new AttributeLoader()),
      new PropertyInfoPropertyMetadataFactory(
        new PropertyInfoExtractor(typeExtractors: [new ReflectionExtractor()]),
        new AttributePropertyMetadataFactory(),
      ),
      [new PropertySchemaLengthRestriction()],
    );
    $property = $metadata->create(UpdateOrganizationRegisteredAddressInput::class, $field);
    $schema = $property->getSchema();

    self::assertNotNull($schema);
    self::assertSame(['string', 'null'], $schema['type'] ?? null);
    self::assertArrayNotHasKey('maxLength', $schema);
    $description = $schema['description'] ?? null;
    self::assertIsString($description);
    self::assertStringContainsString($limit . ' characters after trimming', $description);
    self::assertFalse($property->isRequired());
  }

  #[Test]
  #[DataProvider('addressLengthLimits')]
  public function testAddressValidationMeasuresTheTrimmedLength(string $field, int $limit): void
  {
    $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
    $input = new UpdateOrganizationSettingsInput();
    $input->registeredAddress = new UpdateOrganizationRegisteredAddressInput();
    $input->registeredAddress->{$field} = ' ' . str_repeat('a', $limit) . ' ';
    self::assertCount(0, $validator->validate($input));

    $input->registeredAddress->{$field} = ' ' . str_repeat('a', $limit + 1) . ' ';
    $violations = $validator->validate($input);
    self::assertCount(1, $violations);
    $violation = $violations[0];
    self::assertNotNull($violation);
    self::assertSame('registeredAddress.' . $field, $violation->getPropertyPath());
  }

  /**
   * Method addressLengthLimits
   *
   * Covers each normalized address field's canonical length boundary.
   *
   * @access public
   *
   * @return iterable<string, array{string, int}> address fields and their trimmed length limits
   */
  public static function addressLengthLimits(): iterable
  {
    yield 'first address line' => ['line1', 255];
    yield 'second address line' => ['line2', 255];
    yield 'postal code' => ['postalCode', 32];
    yield 'city' => ['city', 128];
    yield 'region' => ['region', 128];
  }

  #[Test]
  public function testNestedValidationRejectsLongComponentsAndInvalidEmail(): void
  {
    $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
    $input = new UpdateOrganizationSettingsInput();
    $input->registeredAddress = new UpdateOrganizationRegisteredAddressInput();
    $input->registeredAddress->city = str_repeat('a', 129);
    $input->privacyContactEmail = 'invalid';
    $violations = $validator->validate($input);

    self::assertCount(2, $violations);
    $addressViolation = $violations[0];
    $emailViolation = $violations[1];
    self::assertNotNull($addressViolation);
    self::assertNotNull($emailViolation);
    self::assertSame('registeredAddress.city', $addressViolation->getPropertyPath());
    self::assertSame('privacyContactEmail', $emailViolation->getPropertyPath());
  }
}
