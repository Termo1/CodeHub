<?php
require_once '../db/classes/Session.php';

// Start session
Session::start();

// Destroy session
Session::destroy();

// Redirect to login page
header('Location: http://localhost/codehub/index.php');
exit;