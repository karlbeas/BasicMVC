<?php

declare(strict_types=1);

namespace App\Controllers\System;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\Installer;
use App\Services\ValidationException;

final class InstallController extends Controller
{
    public function show(Request $request): Response
    {
        $installer = $this->installer();

        return $this->view('install/form', [
            'requirements' => $installer->requirements(),
            'ready' => $installer->requirementsMet(),
            'old' => [
                'db_host' => 'localhost',
                'db_port' => '3306',
                'db_name' => '',
                'db_user' => '',
                'app_url' => $this->detectedUrl($request),
            ],
            'errors' => [],
            'error' => null,
        ]);
    }

    public function install(Request $request): Response
    {
        $installer = $this->installer();
        $old = [
            'db_host' => $request->string('db_host'),
            'db_port' => $request->string('db_port', '3306'),
            'db_name' => $request->string('db_name'),
            'db_user' => $request->string('db_user'),
            'app_url' => $request->string('app_url'),
        ];
        $render = fn(array $errors, ?string $error, int $status = 422): Response => $this->view('install/form', [
            'requirements' => $installer->requirements(),
            'ready' => $installer->requirementsMet(),
            'old' => $old,
            'errors' => $errors,
            'error' => $error,
        ], $status);

        if (!$installer->requirementsMet()) {
            return $render([], t('install.requirements_failed'));
        }
        if (!$installer->checkKey($request->string('install_key'))) {
            sleep(1); // slow down guessing

            return $render(['install_key' => 'install.error.key'], null, 403);
        }
        try {
            $result = $installer->install([
                'db_host' => $old['db_host'],
                'db_port' => (int) $old['db_port'],
                'db_name' => $old['db_name'],
                'db_user' => $old['db_user'],
                'db_password' => (string) $request->input('db_password', ''),
                'app_url' => $old['app_url'],
                'https' => str_starts_with($old['app_url'], 'https://'),
                'locale' => $this->app->lang()->locale(),
            ]);
        } catch (ValidationException $e) {
            return $render($e->errors, null);
        } catch (\PDOException $e) {
            $this->app->logger()->error('Installer database error', ['code' => $e->getCode()]);

            return $render([], t('install.error.db_connect') . ' (' . $e->getMessage() . ')');
        }

        return $this->view('install/done', [
            'appUrl' => rtrim($old['app_url'], '/'),
            'root' => $this->app->root,
            // PHP_BINARY is the FPM/LSAPI binary under the web SAPI; the CLI usually sits in PHP_BINDIR.
            'phpBinary' => PHP_BINDIR . '/php',
            'cronToken' => $result['cron_token'],
            'migrations' => $result['migrations'],
        ]);
    }

    private function installer(): Installer
    {
        $installer = new Installer($this->app->root, $this->app->storagePath());
        if ($installer->isInstalled()) {
            throw new HttpException(404);
        }

        return $installer;
    }

    private function detectedUrl(Request $request): string
    {
        $host = preg_replace('/[^A-Za-z0-9.:-]/', '', (string) $request->header('Host'));

        return ($request->isSecure() ? 'https' : 'http') . '://' . $host . $this->app->basePath();
    }
}
