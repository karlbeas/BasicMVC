<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\FormSchema;
use App\Services\FormValidator;
use App\Services\PlanPolicy;

final class FormApiController extends Controller
{
    /** POST /api/forms/validate — body: the form JSON. Nothing is stored. */
    public function validate(Request $request): Response
    {
        $ctx = $this->authorize('form.create');
        $body = $request->body();
        if (strlen($body) > FormValidator::MAX_JSON_BYTES) {
            return $this->json(['error' => ['status' => 413, 'message' => t('upload.too_large')]], 413);
        }
        $result = FormValidator::default()->validateJson($body);
        $data = $result->toArray();
        if ($result->form !== null && $result->isValid()) {
            $count = FormSchema::questionCount($result->form);
            $limit = PlanPolicy::forOrganization($this->app->db(), $ctx->organizationId())->limit('questions_per_form');
            $data['question_count'] = $count;
            $data['question_limit'] = $limit;
            $data['within_plan'] = $limit === null || $count <= $limit;
        }

        return $this->json($data);
    }
}
