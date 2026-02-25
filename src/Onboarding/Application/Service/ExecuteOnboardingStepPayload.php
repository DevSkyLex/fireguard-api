<?php

declare(strict_types=1);

namespace Onboarding\Application\Service;

/**
 * Value object ExecuteOnboardingStepPayload.
 *
 * Application-layer input for executing an onboarding step.
 * All steps are currently confirmed without payload: the action
 * (creating the org, inviting members) is performed externally first,
 * then this payload signals the onboarding engine to validate the step.
 *
 * @category ValueObject
 *
 * @version 2.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ExecuteOnboardingStepPayload
{
}
