<?php

declare(strict_types=1);

use Modules\Seo\StaticSeoGenerator;
use VEngine\System\Router\StaticSiteContext;

return static function (StaticSiteContext $context): void {
    (new StaticSeoGenerator())->generate($context);
};
