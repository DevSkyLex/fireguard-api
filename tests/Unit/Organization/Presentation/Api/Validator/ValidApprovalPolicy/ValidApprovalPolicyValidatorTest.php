<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Presentation\Api\Validator\ValidApprovalPolicy;

use Organization\Application\Port\Outbound\ApprovalActionTypeCatalogPort;
use Organization\Presentation\Api\Dto\Input\Organization\UpdateOrganizationApprovalInput;
use Organization\Presentation\Api\Validator\ValidApprovalPolicy\{ValidApprovalPolicy, ValidApprovalPolicyValidator};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * Test ValidApprovalPolicyValidator.
 *
 * @category Validator Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @extends ConstraintValidatorTestCase<ValidApprovalPolicyValidator>
 */
#[CoversClass(ValidApprovalPolicyValidator::class)]
final class ValidApprovalPolicyValidatorTest extends ConstraintValidatorTestCase
{
  #[Test]
  public function testAnUnexpectedConstraintIsRejected(): void
  {
    $this->expectException(UnexpectedTypeException::class);

    $this->validator->validate(new UpdateOrganizationApprovalInput(), new NotBlank());
  }

  #[Test]
  public function testANonInputValueIsIgnored(): void
  {
    $this->validator->validate('not-an-input', new ValidApprovalPolicy());

    $this->assertNoViolation();
  }

  #[Test]
  public function testNullActionRulesAreIgnored(): void
  {
    $this->validator->validate(new UpdateOrganizationApprovalInput(), new ValidApprovalPolicy());

    $this->assertNoViolation();
  }

  #[Test]
  public function testKnownActionTypesAreValid(): void
  {
    $input = new UpdateOrganizationApprovalInput();
    $input->actionRules = ['nc_waiver' => ['enabled' => true], 'equipment_decommission' => null];

    $this->validator->validate($input, new ValidApprovalPolicy());

    $this->assertNoViolation();
  }

  #[Test]
  public function testAnUnknownActionTypeRaisesAViolation(): void
  {
    $constraint = new ValidApprovalPolicy();
    $input = new UpdateOrganizationApprovalInput();
    $input->actionRules = ['made_up' => ['enabled' => true]];

    $this->validator->validate($input, $constraint);

    $this->buildViolation($constraint->unknownActionTypeMessage)
      ->setParameter('{{ value }}', 'made_up')
      ->setParameter('{{ types }}', 'nc_waiver, equipment_decommission')
      ->assertRaised();
  }

  protected function createValidator(): ValidApprovalPolicyValidator
  {
    $catalog = $this->createStub(ApprovalActionTypeCatalogPort::class);
    $catalog->method('values')->willReturn(['nc_waiver', 'equipment_decommission']);

    return new ValidApprovalPolicyValidator($catalog);
  }
}
