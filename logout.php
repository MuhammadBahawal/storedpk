<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/store_service.php';

storeLogoutUser();

header('Location: login.php');
exit;
