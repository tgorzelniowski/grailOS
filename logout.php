<?php
require_once 'config/db.php';
session_destroy();
header('Location: /grailos/');
exit;
?>