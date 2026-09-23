<?php
require_once __DIR__ . '/../includes/auth.php';
logout_admin();
redirect('/admin/index.php');
