<?php

declare(strict_types=1);

namespace Splitstack\Rome\Tests\PHPStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\Test;
use Splitstack\Rome\PHPStan\Rules\ProxiedWriteAfterProxyCallRule;

/**
 * @extends RuleTestCase<ProxiedWriteAfterProxyCallRule>
 */
final class ProxiedWriteAfterProxyCallRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new ProxiedWriteAfterProxyCallRule;
    }

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        require_once __DIR__.'/../Fixtures/ProxiedWrite/violating.php';
        require_once __DIR__.'/../Fixtures/ProxiedWrite/valid.php';
    }

    #[Test]
    public function chained_write_on_proxy_result_is_rejected(): void
    {
        $this->analyse(
            [__DIR__.'/../Fixtures/ProxiedWrite/violating.php'],
            [
                [
                    'Do not call save() on the result of proxy(): the in-memory instance may carry computed view columns absent from the backing table. Use update() on the ReadOnlyModel directly, or call underlying(true) to fetch a clean instance from the DB first.',
                    13,
                ],
                [
                    'Do not call delete() on the result of proxy(): the in-memory instance may carry computed view columns absent from the backing table. Use update() on the ReadOnlyModel directly, or call underlying(true) to fetch a clean instance from the DB first.',
                    14,
                ],
                [
                    'Do not call save() on the result of underlying(false): the in-memory instance may carry computed view columns absent from the backing table. Use update() on the ReadOnlyModel directly, or call underlying(true) to fetch a clean instance from the DB first.',
                    15,
                ],
                [
                    'Do not call delete() on the result of underlying(false): the in-memory instance may carry computed view columns absent from the backing table. Use update() on the ReadOnlyModel directly, or call underlying(true) to fetch a clean instance from the DB first.',
                    16,
                ],
                [
                    'Do not call save() on the result of underlying(false): the in-memory instance may carry computed view columns absent from the backing table. Use update() on the ReadOnlyModel directly, or call underlying(true) to fetch a clean instance from the DB first.',
                    17,
                ],
            ]
        );
    }

    #[Test]
    public function force_fetch_true_and_assignment_without_write_are_allowed(): void
    {
        $this->analyse(
            [__DIR__.'/../Fixtures/ProxiedWrite/valid.php'],
            []
        );
    }
}
