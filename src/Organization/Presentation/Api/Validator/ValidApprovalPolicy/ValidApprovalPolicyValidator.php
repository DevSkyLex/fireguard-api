<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Validator\ValidApprovalPolicy;

use Organization\Application\Port\Outbound\ApprovalActionTypeCatalogPort;
use Organization\Presentation\Api\Dto\Input\Organization\UpdateOrganizationApprovalInput;
use Symfony\Component\Validator\{Constraint, ConstraintValidator};
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

use function array_keys;
use function implode;
use function in_array;

/**
 * Validator ValidApprovalPolicyValidator.
 *
 * Delegates the action-type key check to
 * {@see ApprovalActionTypeCatalogPort} so the API contract enforces the
 * exact same catalog as {@see \Approval\Application\Contract\Action\ApprovalActionTypes}
 * — this is UX-only input-time validation; the domain value object's own
 * bounds (minimum approver role, minimum severity, TTL) remain the
 * authoritative business-rule gate.
 *
 * @category Validator
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ValidApprovalPolicyValidator extends ConstraintValidator
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param ApprovalActionTypeCatalogPort $actionTypeCatalog the approval action-type catalog port
   */
  public function __construct(
    private readonly ApprovalActionTypeCatalogPort $actionTypeCatalog,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method validate.
   *
   * {@inheritDoc}
   *
   * @since 1.0.0
   *
   * @param mixed $value the value to validate
   * @param Constraint $constraint the constraint to validate against
   */
  public function validate(mixed $value, Constraint $constraint): void
  {
    if (!$constraint instanceof ValidApprovalPolicy) {
      throw new UnexpectedTypeException($constraint, ValidApprovalPolicy::class);
    }

    if (!$value instanceof UpdateOrganizationApprovalInput || null === $value->actionRules) {
      return;
    }

    $knownTypes = $this->actionTypeCatalog->values();

    foreach (array_keys($value->actionRules) as $actionType) {
      if (!in_array((string) $actionType, $knownTypes, true)) {
        $this->context->buildViolation($constraint->unknownActionTypeMessage)
          ->setParameter('{{ value }}', (string) $actionType)
          ->setParameter('{{ types }}', implode(', ', $knownTypes))
          ->addViolation();
      }
    }
  }
  // #endregion
}
