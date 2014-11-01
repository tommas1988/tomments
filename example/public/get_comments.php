<?php
// Show comment example
require __DIR__ . '/../init.php';

$searchKey    = (int) $_GET['search_key'];
$length       = (int) $_GET['length'];
$searchParams = $_GET['search_params'];

// start profile
TUtils\Profiler::getInstance()->start('get_comments');

$comments = $commentManager->getComments($searchKey, $length, $searchParams);
$nextKey  = $commentManager->getNextSearchKey();

// stop profile
TUtils\Profiler::getInstance()->stop();

$result['comments'] = array();
foreach ($comments as $comment) {
    $result['comments'][] = $comment->toArray();
}

if (!$nextKey) {
    $result['nextSearchKey'] = false;
} else {
    $result['nextSearchKey'] = array(
        'searchKey' => $nextKey['search_key'],
        'originKey' => isset($nextKey['origin_key']) ? $nextKey['origin_key'] : -1,
    );
}

echo json_encode($result);
exit;
