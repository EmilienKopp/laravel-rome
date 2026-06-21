<?php

declare(strict_types=1);

namespace Splitstack\Rome\Tests\PHPStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\Test;
use Splitstack\Rome\PHPStan\Rules\ViewDefinitionsMightCollide;

/**
 * @extends RuleTestCase<ViewDefinitionsMightCollide>
 */
final class ViewDefinitionsMightCollideTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new ViewDefinitionsMightCollide(dbViewsPath: __DIR__.'/../Fixtures/ViewCollision/sql');
    }

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        require_once __DIR__.'/../Fixtures/ViewCollision/violating.php';
        require_once __DIR__.'/../Fixtures/ViewCollision/valid.php';
        require_once __DIR__.'/../Fixtures/ViewCollision/stale.php';
        require_once __DIR__.'/../Fixtures/ViewCollision/foreign.php';
    }

    #[Test]
    public function computed_column_colliding_with_proxied_fillable_is_flagged(): void
    {
        $this->analyse(
            [__DIR__.'/../Fixtures/ViewCollision/violating.php'],
            [
                [
                    'Column "name" is computed in the view SQL for "violating_view" and also exists as a fillable '
                    .'attribute on proxied model "Splitstack\Rome\Tests\PHPStan\Fixtures\ViewCollision\ViolatingProxied", '
                    .'but is not listed in $exclude. '
                    .'This may cause proxy()/underlying(false) to hydrate a computed value into a writable model. '
                    .'Add "name" to $exclude on Splitstack\Rome\Tests\PHPStan\Fixtures\ViewCollision\ViolatingCollisionView, '
                    .'or rename the SQL alias.',
                    19,
                ],
            ]
        );
    }

    #[Test]
    public function excluded_computed_column_and_model_without_proxy_produce_no_errors(): void
    {
        $this->analyse(
            [__DIR__.'/../Fixtures/ViewCollision/valid.php'],
            []
        );
    }

    #[Test]
    public function exclude_entry_absent_from_sql_is_flagged_as_stale(): void
    {
        $this->analyse(
            [__DIR__.'/../Fixtures/ViewCollision/stale.php'],
            [
                [
                    '"full_name" is listed in $exclude on Splitstack\Rome\Tests\PHPStan\Fixtures\ViewCollision\StaleExcludeView '
                    .'but does not appear anywhere in the view SQL "stale_view". '
                    .'This entry may be stale.',
                    18,
                ],
            ]
        );
    }

    #[Test]
    public function column_sourced_from_joined_table_colliding_with_proxied_fillable_is_flagged(): void
    {
        $this->analyse(
            [__DIR__.'/../Fixtures/ViewCollision/foreign.php'],
            [
                [
                    'Column "email" in the view SQL for "foreign_view" is sourced from a joined table, not from '
                    .'the primary table of proxied model "Splitstack\Rome\Tests\PHPStan\Fixtures\ViewCollision\ForeignProxied". '
                    .'This may cause proxy()/underlying(false) to write a stale or incorrect value. '
                    .'Add "email" to $exclude on Splitstack\Rome\Tests\PHPStan\Fixtures\ViewCollision\ForeignCollisionView, '
                    .'or use a distinct alias.',
                    19,
                ],
            ]
        );
    }
}
