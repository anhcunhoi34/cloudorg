<?php
require_once __DIR__ . '/lib/CrontabController.php';

// Khởi tạo đối tượng của class
$crontab = new CrontabController();

if ( $argv[1] === 'getToken' ) {
    $crontab->getToken();
}
elseif ($argv[1] === 'createVpsWaiting') {
    $crontab->createVpsWaiting();
}
elseif ($argv[1] === 'updateStatusVps') {
    $crontab->updateStatusVps();
}

exit();