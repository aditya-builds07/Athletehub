<?php
/**
 * AthleteHub Admin — Logout
 */
session_start();
session_unset();
session_destroy();
header('Location: /dashboard/AthleteHub-Admin/index.php');
exit;
