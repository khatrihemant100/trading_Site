<?php
session_start();

// सेसन नष्ट गर्ने
session_unset();
session_destroy();

// लगइन पेजमा पठाउने
header("Location: login.php");
exit();
?>