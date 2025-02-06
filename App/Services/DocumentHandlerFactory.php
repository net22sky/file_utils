<?php
namespace App\Services;

use App\Handlers\AbstractDocumentHandler;
use App\Services\Loggers as Logger;

class DocumentHandlerFactory {
    private $handlers = [];
    private $logger;

    /**
     * Конструктор класса DocumentHandlerFactory.
     *
     * @param Logger $logger Логгер для записи событий и ошибок.
     */
    public function __construct(Logger $logger) {
        $this->logger = $logger;

        // Инициализируем обработчики
        $this->initHandlers();
    }

    /**
     * Возвращает обработчик для заданного расширения файла.
     *
     * @param string $extension Расширение файла.
     * @return AbstractDocumentHandler|null Обработчик или null, если не найден.
     */
    public function getHandlerForExtension(string $extension): ?AbstractDocumentHandler {
        foreach ($this->handlers as $handler) {
            if ($handler->supports($extension)) {
                return $handler;
            }
        }
        $this->logger->warning("Обработчик для расширения $extension не найден.");
        return null;
    }

    /**
     * Инициализирует список доступных обработчиков.
     */
    private function initHandlers(): void {
        $handlers = [
            new \App\Handlers\PdfHandler($this->logger),
            new \App\Handlers\Fb2Handler($this->logger),
            new \App\Handlers\DjvuHandler($this->logger),
        ];

        foreach ($handlers as $handler) {
            if (!$handler instanceof AbstractDocumentHandler) {
                //$this->logger->critical("Компонент {$handler::class} не является экземпляром AbstractDocumentHandler.");
                $this->logger->critical('Компонент ' . $handler::class . ' не является экземпляром AbstractDocumentHandler.');
                throw new \Exception("Неверный тип обработчика: '.$handler::class.'}");
            }

            $this->handlers[] = $handler;
        }
    }
}