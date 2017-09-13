<?php
/**
 * Created by PhpStorm.
 * User: xinghuo
 * Date: 2017/8/8
 * Time: 上午11:29
 */


$path = [];
array_unshift($path, "autoload.php");
array_unshift($path, "vendor");

do {
    array_unshift($path, "..");
    $file = implode("/", $path);
} while (!file_exists(__DIR__ . '/' . $file));

include __DIR__ . '/' . $file;

echo "load bootstrap.php\r\n";
