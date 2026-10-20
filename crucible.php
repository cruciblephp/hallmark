<?php

use LucianoPereira\Crucible\Configuration\Crucible;

return Crucible::configure()
    ->bootstrap('vendor/autoload.php')
    // Orchestra Testbench brings phpunit/phpunit; the suite still runs on Crucible.
    ->phpunitCompatibility()
    ->testSuite('unit', 'tests')
    ->source(include: ['src', 'integration/laravel/src', 'integration/phpunit/src'])
    ->build();
