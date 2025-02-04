<?php

namespace Tests\Core;

use PHPUnit\Framework\TestCase;
use App\Core\DocumentProcessor;
use App\Config;

class DocumentProcessorTest extends TestCase {
    public function testProcessDirectory() {
        $configPath = __DIR__ . '/fixtures/config.json'; // Путь к тестовому config.json
        if (!file_exists($configPath)) {
            $this->markTestSkipped('Тестовый конфигурационный файл не найден.');
        }

        $config = Config\readConfig($configPath);
        $processor = new DocumentProcessor($config);

        $results = $processor->processDirectory();
        $this->assertIsArray($results);

        foreach ($results as $result) {
            $this->assertArrayHasKey('path', $result);
            $this->assertArrayHasKey('title', $result);
            $this->assertArrayHasKey('creation_date', $result);
            $this->assertArrayHasKey('thumbnail', $result);
        }
    }
}