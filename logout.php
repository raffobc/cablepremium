<?php
session_start();
session_unset();
session_destroy();
header('Location: login.php');
exit();
// ... no debe haber nada más después de aquí ... 