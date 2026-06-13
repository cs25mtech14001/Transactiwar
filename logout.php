<?php
# Atish Kadam - CS25MTECH14003
# Akarsh Dubey - CS25MTECH14001
# Atharva Kale - CS25MTECH11024
# Prashant Kumar Dubey - CS25MTECH14011
# Debdip Choudhuri - CS25MTECH11025
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

logout_user();
set_flash('success', 'You have been logged out.');
redirect(BASE_URL . '/public/login.php');