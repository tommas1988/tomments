<?php
// Delete comment example
require __DIR__ . '/../init.php';

if (!isset($_POST['key'])) {
    echo '{"error" : "no comment key"}';
    exit;
}
$key = $_POST['key'];

$params  = null;
$isChild = false;
if (isset($_POST['is_child'])) {
    if (!isset($_POST['origin_key'])) {
        echo '{"error" : "no origin key"}';
        exit;
    }
    $params['originKey'] = $_POST['origin_key'];

    if (!isset($_POST['level'])) {
        echo '{"error" : "no level"}';
        exit;
    }
    $params['level'] = $_POST['level'];
    $isChild = true;
}

// start profile
TUtils\Profiler::getInstance()->start('delete_comment');

if ($commentManager->deleteComment($key, $params, $isChild)) {
    echo 1;
} else {
    echo '{"error" : "can not delete the comment"}';
}

// stop profile
TUtils\Profiler::getInstance()->stop();

exit;
