<?php

declare(strict_types=1);

namespace VEngine\System\Router;

final class StaticModuleRegistrar
{
    public function register(string $modulesPath, StaticSiteContext $context): void
    {
        $moduleFiles = glob(rtrim($modulesPath, DIRECTORY_SEPARATOR) . '/*/static.php') ?: [];
        sort($moduleFiles, SORT_STRING);

        foreach ($moduleFiles as $moduleFile) {
            $registrar = require $moduleFile;
            if (!is_callable($registrar)) {
                throw new RouterException("Static module должен вернуть callable: {$moduleFile}");
            }

            $registrar($context);
        }
    }
}
