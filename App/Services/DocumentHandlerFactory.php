<?php

namespace App\Services;

use App\Handlers\PdfHandler;
use App\Handlers\Fb2Handler;
use App\Handlers\DjvuHandler;
use App\Handlers\AbstractDocumentHandler;

class DocumentHandlerFactory {
    private $handlers;

    public function __construct() {
        $this->handlers = [
            new PdfHandler(),
            new Fb2Handler(),
            new DjvuHandler()
        ];
    }

    public function getHandlerForExtension(string $extension): ?AbstractDocumentHandler {
        foreach ($this->handlers as $handler) {
            if ($handler->supports($extension)) {
                return $handler;
            }
        }
        return null;
    }
}