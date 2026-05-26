<?php
require_once 'config/auth.config_area_restrita.php';

logoutRestrito(); 

header('Location: index.php');
exit;
