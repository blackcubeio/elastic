<?php

declare(strict_types=1);

namespace Blackcube\Elastic\Tests\Support;

/**
 * Inherited Methods
 * @method void wantTo($text)
 * @method void wantToTest($text)
 * @method void execute($callable)
 * @method void expectTo($prediction)
 * @method void expect($prediction)
 * @method void amGoingTo($argumentation)
 * @method void am($role)
 * @method void lookForwardTo($achieveValue)
 * @method void comment($description)
 * @method void pause($vars = [])
 *
 * @SuppressWarnings(PHPMD)
*/
class MigrationTester extends \Codeception\Actor
{
    use _generated\MigrationTesterActions;

    /**
     * Define custom actions here
     */
}
