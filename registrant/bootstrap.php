<?php
require_once __DIR__ . '/src/OlaCV.php';

$config = require __DIR__ . '/config.php';

if ($config['api_key'] === 'YOUR_OLACV_API_KEY_HERE') {
    die("Please configure your API Key in registrant/config.php");
}

$ola = new Registrant\OlaCV($config['api_key'], $config['api_base_url'], $config['debug']);

function get_template($name) {
    return __DIR__ . '/templates/' . $name . '.php';
}
