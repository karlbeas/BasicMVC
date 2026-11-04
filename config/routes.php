<?php

declare(strict_types=1);

use App\Controllers\Api\FormApiController;
use App\Controllers\Collect\CampaignController;
use App\Controllers\Collect\PublicFormController;
use App\Controllers\Control\AuthController;
use App\Controllers\Control\ProjectController;
use App\Controllers\HomeController;
use App\Controllers\Studio\FormController;
use App\Controllers\System\InstallController;
use App\Controllers\System\SystemController;
use App\Core\Router;

return static function (Router $r): void {
    $r->get('/', [HomeController::class, 'index']);
    $r->get('/lang/{locale}', [HomeController::class, 'locale']);

    // Control — authentication
    $r->get('/login', [AuthController::class, 'showLogin'], ['guest']);
    $r->post('/login', [AuthController::class, 'login'], ['guest']);
    $r->get('/register', [AuthController::class, 'showRegister'], ['guest']);
    $r->post('/register', [AuthController::class, 'register'], ['guest']);
    $r->post('/logout', [AuthController::class, 'logout'], ['auth']);

    // Control — projects
    $r->get('/projects', [ProjectController::class, 'index'], ['auth']);
    $r->post('/projects', [ProjectController::class, 'store'], ['auth']);
    $r->get('/projects/{project}', [ProjectController::class, 'show'], ['auth']);

    // Studio — forms
    $r->get('/projects/{project}/forms/new', [FormController::class, 'create'], ['auth']);
    $r->post('/projects/{project}/forms', [FormController::class, 'store'], ['auth']);
    $r->post('/projects/{project}/forms/blank', [FormController::class, 'storeBlank'], ['auth']);
    $r->get('/forms/{form}/edit', [FormController::class, 'edit'], ['auth']);
    $r->get('/forms/{form}', [FormController::class, 'show'], ['auth']);
    $r->post('/forms/{form}/drafts', [FormController::class, 'newDraft'], ['auth']);
    $r->post('/forms/{form}/versions/{version}', [FormController::class, 'updateDraft'], ['auth']);
    $r->post('/forms/{form}/versions/{version}/publish', [FormController::class, 'publish'], ['auth']);
    $r->get('/forms/{form}/versions/{version}/json', [FormController::class, 'exportJson'], ['auth']);

    // Collect — campaigns (authenticated)
    $r->post('/forms/{form}/versions/{version}/campaigns', [CampaignController::class, 'store'], ['auth']);
    $r->get('/campaigns/{campaign}', [CampaignController::class, 'show'], ['auth']);
    $r->post('/campaigns/{campaign}/status', [CampaignController::class, 'status'], ['auth']);
    $r->get('/campaigns/{campaign}/qr', [CampaignController::class, 'qr'], ['auth']);
    $r->get('/campaigns/{campaign}/export/{format}', [CampaignController::class, 'export'], ['auth']);

    // Collect — public response pages (no login)
    $r->get('/f/{token}', [PublicFormController::class, 'show']);
    $r->post('/f/{token}', [PublicFormController::class, 'submit']);
    $r->post('/f/{token}/code', [PublicFormController::class, 'unlock']);
    $r->get('/f/{token}/merci', [PublicFormController::class, 'thanks']);

    // API (session auth + X-CSRF-Token header)
    $r->post('/api/forms/validate', [FormApiController::class, 'validate'], ['auth']);

    // System
    $r->get('/install', [InstallController::class, 'show']);
    $r->post('/install', [InstallController::class, 'install']);
    $r->get('/health', [SystemController::class, 'health']);
    $r->get('/cron/{token}', [SystemController::class, 'cron'], ['nocsrf']);
    $r->post('/cron/{token}', [SystemController::class, 'cron'], ['nocsrf']);
};
