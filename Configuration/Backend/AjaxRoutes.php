<?php

declare(strict_types=1);

use Conrat\Crgalerie\Controller\Backend\UploadAjaxController;

return [
    'crgalerie_upload' => [
        'path' => '/crgalerie/upload',
        'methods' => ['POST'],
        'target' => UploadAjaxController::class . '::uploadAction',
        'inheritAccessFromModule' => 'web_crgalerie',
    ],
];
