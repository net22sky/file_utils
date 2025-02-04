<?php

namespace Tests\Handlers;

use PHPUnit\Framework\TestCase;
use App\Handlers\Fb2Handler;

class Fb2HandlerTest extends TestCase {
    public function testSupportsFb2() {
        $handler = new Fb2Handler();
        $this->assertTrue($handler->supports('fb2'));
        $this->assertFalse($handler->supports('epub'));
    }

    public function testGetInfo() {
        $filePath = __DIR__ . '/fixtures/test.fb2'; // Путь к тестовому FB2-файлу
        if (!file_exists($filePath)) {
            $this->markTestSkipped('Тестовый FB2-файл не найден.');
        }

        $handler = new Fb2Handler();
        [$title, $date] = $handler->getInfo($filePath);

        $this->assertIsString($title);
        $this->assertIsString($date);
        $this->assertNotEmpty($title);
    }
}