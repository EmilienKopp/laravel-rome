<?php

namespace Splitstack\Rome\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Flags save() / delete() called directly on the result of proxied() or underlying(false).
 *
 * Both methods return an in-memory hydrated instance that may carry computed view columns
 * absent from the backing table. Writing through such an instance can silently corrupt data
 * or fail at the DB level. Use update() on the ReadOnlyModel directly, or call
 * underlying(true) to get a DB-fetched instance first.
 *
 * Detected patterns (chained calls only):
 *   $view->proxied()->save()
 *   $view->proxied()->delete()
 *   $view->underlying(false)->save()
 *   $view->underlying(forceFetch: false)->delete()
 *
 * @implements Rule<MethodCall>
 */
class ProxiedWriteAfterProxyCallRule implements Rule
{
    private const WRITE_METHODS = ['save', 'delete'];

    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        assert($node instanceof MethodCall);

        if (! $node->name instanceof Node\Identifier) {
            return [];
        }

        $outerMethod = $node->name->name;

        if (! in_array($outerMethod, self::WRITE_METHODS, true)) {
            return [];
        }

        if (! $node->var instanceof MethodCall) {
            return [];
        }

        $inner = $node->var;

        if (! $inner->name instanceof Node\Identifier) {
            return [];
        }

        $innerMethod = $inner->name->name;

        if ($innerMethod === 'proxied') {
            return [$this->buildError($outerMethod, 'proxied()')];
        }

        if ($innerMethod === 'underlying' && $this->isForceFetchFalse($inner)) {
            return [$this->buildError($outerMethod, 'underlying(false)')];
        }

        return [];
    }

    /**
     * Returns true when the first argument of the call resolves to the literal false.
     * Handles both positional (underlying(false)) and named (underlying(forceFetch: false)) forms.
     */
    private function isForceFetchFalse(MethodCall $call): bool
    {
        foreach ($call->getArgs() as $arg) {
            $isForForceFetch = $arg->name === null
                || ($arg->name instanceof Node\Identifier && $arg->name->name === 'forceFetch');

            if (! $isForForceFetch) {
                continue;
            }

            return $arg->value instanceof Node\Expr\ConstFetch
                && strtolower($arg->value->name->toString()) === 'false';
        }

        return false;
    }

    private function buildError(string $writeMethod, string $sourceCall): RuleError
    {
        return RuleErrorBuilder::message(sprintf(
            'Do not call %s() on the result of %s: the in-memory instance may carry computed view columns '
            .'absent from the backing table. Use update() on the ReadOnlyModel directly, '
            .'or call underlying(true) to fetch a clean instance from the DB first.',
            $writeMethod,
            $sourceCall,
        ))->build();
    }
}
