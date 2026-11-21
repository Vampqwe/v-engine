<?php

declare(strict_types=1);

namespace Modules\Events;

final class ModuleEventRegistrar
{
    public function register(string $modulesPath, EventsInterface $events): void
    {
        $moduleFiles = glob(rtrim($modulesPath, DIRECTORY_SEPARATOR) . '/*/module.php') ?: [];
        sort($moduleFiles, SORT_STRING);

        foreach ($moduleFiles as $moduleFile) {
            $registrar = require $moduleFile;
            if (!is_callable($registrar)) {
                throw new EventException("Файл модуля должен вернуть callable для регистрации listeners: {$moduleFile}");
            }

            $registrar($events);
        }
    }
}
