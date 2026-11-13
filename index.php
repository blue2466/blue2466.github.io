<?php
/**
 * index.php - pintu masuk: arahkan ke dashboard bila sudah login, jika belum ke login.
 */
require_once 'config.php';

redirect(is_logged_in() ? 'dashboard.php' : 'login.php');
