<?php
/**
 * Nik Smart Count - Admin Directory Protected Gateway
 */
require_once __DIR__ . '/../config/config.php';
// Do not expose secret admin panel URL publicly. Safely redirect to dashboard.
header('Location: ' . BASE_URL . 'index.php');
exit;

