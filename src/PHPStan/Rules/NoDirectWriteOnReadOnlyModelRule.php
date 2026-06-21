<?php

namespace Splitstack\Rome\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;
use PHPStan\Type\VerbosityLevel;
use Splitstack\Rome\Models\ReadOnlyModel;

/**
 * Flags save() and delete() calls on ReadOnlyModel instances.
 * Both methods always throw at runtime; this catches them at build time.
 *
 * @implements Rule<MethodCall>
 */
class NoDirectWriteOnReadOnlyModelRule implements Rule
{
    private const FORBIDDEN = ['save', 'delete'];

    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (! $node->name instanceof Node\Identifier) {
            return [];
        }

        $method = $node->name->name;

        if (! in_array($method, self::FORBIDDEN, true)) {
            return [];
        }

        $callerType = $scope->getType($node->var);

        if (! (new ObjectType(ReadOnlyModel::class))->isSuperTypeOf($callerType)->yes()) {
            return [];
        }

        $hint = $method === 'save'
            ? 'Define $proxyTo and call update() on the ReadOnlyModel instead.'
            : 'ReadOnlyModel prevents direct deletes.';

        return [
            RuleErrorBuilder::message(sprintf(
                'Cannot call %s() on %s: this is a ReadOnlyModel. %s',
                $method,
                $callerType->describe(VerbosityLevel::typeOnly()),
                $hint,
            ))
                ->identifier('splitstack.rome.readOnlyDirectWrite')
                ->build(),
        ];
    }
}
