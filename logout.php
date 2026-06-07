<?php
require_once 'config/db.php';
_destroy_session();
header('Location: /');
exit;