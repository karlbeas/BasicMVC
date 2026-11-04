<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Lang;
use App\Core\Request;
use App\Core\Response;
use App\Models\ProjectRepository;
use App\Services\PlanPolicy;

final class HomeController extends Controller
{
    public function index(Request $request): Response
    {
        $ctx = $this->app->auth()->current();
        if ($ctx === null) {
            return $this->view('home/landing');
        }
        $projects = (new ProjectRepository($this->app->db()))->all($ctx->organizationId());

        return $this->view('home/dashboard', [
            'projects' => array_slice($projects, 0, 5),
            'projectCount' => count($projects),
            'plan' => PlanPolicy::forOrganization($this->app->db(), $ctx->organizationId()),
        ]);
    }

    public function locale(Request $request): Response
    {
        $locale = $request->params['locale'] ?? '';
        if (in_array($locale, Lang::SUPPORTED, true)) {
            $this->app->session()->set('locale', $locale);
        }

        return $this->back($request);
    }
}
