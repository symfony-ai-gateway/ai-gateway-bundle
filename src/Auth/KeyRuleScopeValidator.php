<?php

declare(strict_types=1);

namespace AIGateway\Auth;

final class KeyRuleScopeValidator
{
    public function validate(array $teamRules, array $keyRules): array
    {
        $errors = [];

        $this->validateNumericScope($errors, $keyRules, $teamRules, 'budget_per_day', 'Budget / Day');
        $this->validateNumericScope($errors, $keyRules, $teamRules, 'budget_per_month', 'Budget / Month');
        $this->validateNumericScope($errors, $keyRules, $teamRules, 'rate_limit_per_minute', 'Rate Limit / Minute');
        $this->validateNumericScope($errors, $keyRules, $teamRules, 'rate_limit_per_day', 'Rate Limit / Day');

        $teamModels = $teamRules['allowed_models'] ?? null;
        $keyModels = $keyRules['allowed_models'] ?? null;
        if (is_array($teamModels) && is_array($keyModels)) {
            $outsideScope = array_values(array_diff($keyModels, $teamModels));
            if ([] !== $outsideScope) {
                $errors[] = 'Models outside team scope: ' . implode(', ', $outsideScope);
            }
        }

        return $errors;
    }

    private function validateNumericScope(array &$errors, array $keyRules, array $teamRules, string $key, string $label): void
    {
        if (!array_key_exists($key, $keyRules) || null === $keyRules[$key]) {
            return;
        }

        if ((float) $keyRules[$key] < 0) {
            $errors[] = sprintf('%s cannot be negative.', $label);

            return;
        }

        if (!array_key_exists($key, $teamRules) || null === $teamRules[$key]) {
            return;
        }

        if ((float) $keyRules[$key] > (float) $teamRules[$key]) {
            $errors[] = sprintf('%s cannot exceed team limit (%s).', $label, $teamRules[$key]);
        }
    }
}
