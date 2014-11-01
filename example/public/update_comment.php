<?php
// Edit comment example
require __DIR__ . '/../init.php';

if (!isset($_POST['key'])) {
    echo '{"error" : "no comment key"}';
    exit;
}
$key = $_POST['key'];

if (!isset($_POST['content']) || empty($_POST['content'])) {
    echo '{"error" : "no comment content"}';
    exit;
}
$params['content'] = $_POST['content'];

$isChild = isset($_POST['is_child']) ? true : false;
$params['time'] = date('Y-m-d H:s:i');

// start profile
TUtils\Profiler::getInstance()->start('update_comment');

if ($commentManager->updateComment($key, $params, $isChild)) {
    echo 1;
} else {
    echo '{"error" : "can not update the comment"}';
}

// stop profile
TUtils\Profiler::getInstance()->stop();

exit;
