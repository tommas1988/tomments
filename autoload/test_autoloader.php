<?php
spl_autoload_register(function($class) {
    $parts = explode('\\', $class);

    if ('Tomments' !== $parts[0] || 'Test' !== $parts[1]) {
        return;
    }

    array_shift($parts);
    array_shift($parts);
    $path = __DIR__ . '/../test/' . implode('/', $parts) . '.php';

    require $path;
});

require 'autoloader.php';
