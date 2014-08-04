<?php
spl_autoload_register(function($class) {
    $parts = explode('\\', $class);

    if ('Tomments' !== $parts[0]) {
        return;
    }

    array_shift($parts);
    $path = __DIR__ . '/../src/' . implode('/', $parts) . '.php';

    require $path;
});
