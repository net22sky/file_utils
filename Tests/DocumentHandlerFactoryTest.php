<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use App\Services\DocumentHandlerFactory;
use App\Services\Loggers as Logger;
use App\Handlers\PdfHandler;
use App\Handlers\Fb2Handler;
use App\Handlers\DjvuHandler;

class DocumentHandlerFactoryTest extends TestCase {
    public function testGetHandlerForPdf(): void {
        $mockLogger = $this->createMock(Logger::class);
        $factory = new DocumentHandlerFactory($mockLogger);

        $handler = $factory->getHandlerForExtension('pdf');
        $this->assertInstanceOf(PdfHandler::class, $handler);
    }

    public function testGetHandlerForFb2(): void {
        $mockLogger = $this->createMock(Logger::class);
        $factory = new DocumentHandlerFactory($mockLogger);

        $handler = $factory->getHandlerForExtension('fb2');
        $this->assertInstanceOf(Fb2Handler::class, $handler);
    }

    public function testGetHandlerForDjvu(): void {
        $mockLogger = $this->createMock(Logger::class);
        $factory = new DocumentHandlerFactory($mockLogger);

        $handler = $factory->getHandlerForExtension('djvu');
        $this->assertInstanceOf(DjvuHandler::class, $handler);
    }

    public function testGetHandlerForUnsupportedExtension(): void {
        $mockLogger = $this->createMock(Logger::class);
        $mockLogger->expects($this->once())->method('warning')->with($this->stringContains('Обработчик для расширения unsupported не найден'));

        $factory = new DocumentHandlerFactory($mockLogger);

        $handler = $factory->getHandlerForExtension('unsupported');
        $this->assertNull($handler);
    }

    public function testInvalidHandlerType(): void {
        $mockLogger = $this->createMock(Logger::class);
        $mockLogger->expects($this->once())->method('critical')->with($this->stringContains('не является экземпляром AbstractDocumentHandler'));

        // Создаём фабрику с недопустимым обработчиком
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Неверный тип обработчика');

        $factory = new class($mockLogger) extends DocumentHandlerFactory {
            protected function initHandlers(): void {
                // Недопустимый обработчик
                $this->handlers[] = new \stdClass();
            }
        };
    }
}