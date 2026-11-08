<?php

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

chdir(dirname(__FILE__) . '/../..');
require_once 'vendor/autoload.php';
require_once 'config.php';
require_once 'include/utils/utils.php';
require_once 'include/database/PearDatabase.php';
require_once 'modules/WhatsApp/WhatsApp.php';

while (true) {
	try {
		if (!WhatsApp_Mock_Service::runProviderWorkerBatch()) {
			sleep(1);
		}
	} catch (Throwable $exception) {
		error_log('WhatsApp worker iteration failed (' . get_class($exception) . ').');
		sleep(5);
	}
}
