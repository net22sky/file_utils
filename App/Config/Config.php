<?php

namespace App\Config;

function readConfig(string $configPath): array {
    if (!file_exists($configPath)) {
        echo "Файл конфигурации не найден.\n";
        exit(1);
    }

    $config = json_decode(file_get_contents($configPath), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "Ошибка при чтении файла конфигурации: " . json_last_error_msg() . "\n";
        exit(1);
    }

    return $config;
}