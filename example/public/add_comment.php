<?php
// Add comment example
require __DIR__ . '/../init.php';

if (!isset($_POST['content']) || empty($_POST['content'])) {
    echo '{"error" : "no comment content"}';
    exit;
}
$params['content'] = $_POST['content'];

if (!isset($_POST['target_id'])) {
    echo '{"error" : "no target id"}';
    exit;
}
$params['targetId'] = $_POST['target_id'];

if (isset($_POST['parent_key'])) {
    $params['parentKey'] = $_POST['parent_key'];
}

if (isset($_POST['origin_key'])) {
    $params['originKey'] = $_POST['origin_key'];
}

if (isset($_POST['level'])) {
    $params['level'] = $_POST['level'];
}

$parentKey = isset($_POST['parent_key']) ? (int) $_POST['parent_key'] : null;

$params['time'] = date('Y-m-d H:s:i');

// start profiler
TUtils\Profiler::getInstance()->start('add_comment');

if ($commentManager->addComment($params, $parentKey)) {
    echo 1;
} else {
    echo '{"error" : "can not add the comment"}';
}

// stop profle
TUtils\Profiler::getInstance()->stop();

exit;
