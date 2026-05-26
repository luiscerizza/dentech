<?php
require_once 'config/auth.php';

fazerLogout(); 

header('Location: login.php');
exit;