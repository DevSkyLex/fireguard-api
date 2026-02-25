<?php

declare(strict_types=1);

namespace Onboarding\Presentation\Api\Dto\Input\Onboarding;

/**
 * DTO ExecuteOrganizationOnboardingStepInput.
 *
 * Generic payload used to execute onboarding steps.
 * All steps are now confirmed without payload: the user first performs
 * the associated action via its dedicated endpoint
 * (e.g. POST /api/organizations, POST /api/organizations/{id}/invitations),
 * then calls executeStep to validate the step in the onboarding flow.
 *
 * @category DTO
 *
 * @version 3.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ExecuteOrganizationOnboardingStepInput
{
}
