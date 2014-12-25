<?php
require __DIR__ . '/../vendors/Zend/Loader/AutoloaderFactory.php';
Zend\Loader\AutoloaderFactory::factory(array(
    'Zend\Loader\StandardAutoloader' => array(
        'namespaces' => array(
            'Tomments'      => __DIR__ . '/../src/',
            'Tomments\Test' => __DIR__ . '/../test',
            'TUtils'        => __DIR__ . '/../vendors/TUtils',
            'Zend'          => __DIR__ . '/../vendors/Zend',
))));
