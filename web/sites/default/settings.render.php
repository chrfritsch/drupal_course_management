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

// Cập nhật trusted_host_patterns với domain chính xác
$settings['trusted_host_patterns'] = [
  '^drupal\-course\-management\.onrender\.com$',  // Domain chính xác của bạn
  '^.*\.onrender\.com$',  // Các subdomain khác của onrender.com
  '^localhost$',
  '^127\.0\.0\.1$',
];

// Cấu hình reverse proxy
$settings['reverse_proxy'] = TRUE;
$settings['reverse_proxy_addresses'] = array($_SERVER['REMOTE_ADDR']);

// Thêm debug mode nếu cần
$config['system.logging']['error_level'] = 'verbose';