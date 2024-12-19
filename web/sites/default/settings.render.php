<?php

$databases['default']['default'] = [
  'database' => getenv('DB_NAME'),
  'username' => getenv('DB_USER'),
  'password' => getenv('DB_PASSWORD'),
  'host' => getenv('DB_HOST'),
  'port' => getenv('DB_PORT'),
  'driver' => 'mysql',
  'prefix' => '',
  'namespace' => 'Drupal\\mysql\\Driver\\Database\\mysql',
  'autoload' => 'core/modules/mysql/src/Driver/Database/mysql/',
];

$settings['hash_salt'] = getenv('DRUPAL_HASH_SALT');

// Thêm cấu hình trusted_host_patterns
$settings['trusted_host_patterns'] = [
  '^.*\.render\.com$',  // Cho phép tất cả subdomain của render.com
  '^localhost$',
  '^127\.0\.0\.1$',
];

// Thêm cấu hình reverse proxy nếu cần
$settings['reverse_proxy'] = TRUE;
$settings['reverse_proxy_addresses'] = array($_SERVER['REMOTE_ADDR']);
