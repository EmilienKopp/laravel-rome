<?php

declare(strict_types=1);

namespace Splitstack\Rome\Tests\PHPStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\Test;
use Splitstack\Rome\PHPStan\Rules\NoDirectWriteOnReadOnlyModelRule;

/**
 * @extends RuleTestCase<NoDirectWriteOnReadOnlyModelRule>
 */
final class NoDirectWriteOnReadOnlyModelRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new NoDirectWriteOnReadOnlyModelRule;
    }

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        require_once __DIR__.'/../Fixtures/NoDirectWrite/violating.php';
        require_once __DIR__.'/../Fixtures/NoDirectWrite/valid.php';
    }

    #[Test]
    public function save_and_delete_on_read_only_model_are_rejected(): void
    {
        $this->analyse(
            [__DIR__.'/../Fixtures/NoDirectWrite/violating.php'],
            [
                [
                    'Cannot call save() on Splitstack\Rome\Tests\PHPStan\Fixtures\NoDirectWrite\ViolatingView: this is a ReadOnlyModel. Define $proxyTo and call update() on the ReadOnlyModel instead.',
                    13,
                ],
                [
                    'Cannot call delete() on Splitstack\Rome\Tests\PHPStan\Fixtures\NoDirectWrite\ViolatingView: this is a ReadOnlyModel. ReadOnlyModel prevents direct deletes.',
                    14,
                ],
            ]
        );
    }

    #[Test]
    public function update_on_read_only_model_and_writes_on_plain_model_are_allowed(): void
    {
        $this->analyse(
            [__DIR__.'/../Fixtures/NoDirectWrite/valid.php'],
            []
        );
    }
}
