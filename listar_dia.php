<?php
$data = $_GET['data'] ?? date('Y-m-d');
header("Location: agendamentos.php?data=" . urlencode($data));
exit;