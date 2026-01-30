<?php
echo "PDO drivers: " . implode(", ", pdo_drivers()) . "\n";
echo "mysqli: " . (extension_loaded('mysqli') ? 'Yes' : 'No') . "\n";
echo "upload_max_filesize: " . ini_get('upload_max_filesize') . "\n";
echo "post_max_size: " . ini_get('post_max_size') . "\n";
echo "Loaded php.ini: " . php_ini_loaded_file() . "\n";
