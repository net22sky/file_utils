<?php

namespace Tests\Handlers;

use PHPUnit\Framework\TestCase;
use App\Handlers\PdfHandler;

class PdfHandlerTest extends TestCase {
    public function testSupportsPdf() {
        $handler = new PdfHandler();
        $this->assertTrue($handler->supports('pdf'));
        $this->assertFalse($handler->supports('docx'));
    }

    public function testGetInfo() {
        $filePath = __DIR__ . '/fixtures/test.pdf'; // Путь к тестовому PDF-файлу
        if (!file_exists($filePath)) {
            $this->markTestSkipped('Тестовый PDF-файл не найден.');
        }

        $handler = new PdfHandler();
        [$title, $creationDate] = $handler->getInfo($filePath);

        $this->assertIsString($title);
        $this->assertIsString($creationDate);
        $this->assertNotEmpty($title);
        $this->assertNotEmpty($creationDate);
    }
}