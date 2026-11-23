<?php

declare(strict_types=1);

namespace App\Exception;

use App\Rule\Violation;

/** An assignment breaks one or more rules. All violations are reported at once (422). */
final class RuleViolationException extends DomainException
{
    /** @param non-empty-list<Violation> $violations */
    public function __construct(array $violations)
    {
        parent::__construct(
            sprintf('This assignment breaks %d %s.', count($violations), 1 === count($violations) ? 'rule' : 'rules'),
            422,
            'RULE_VIOLATION',
            array_map(static fn (Violation $violation): array => $violation->toArray(), $violations),
        );
    }
}
