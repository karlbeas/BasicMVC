<?php

declare(strict_types=1);

namespace App\Controllers\Collect;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Uuid;
use App\Models\CampaignRepository;
use App\Models\SubmissionRepository;
use App\Services\Collect\CampaignService;
use App\Services\Collect\FormRenderer;
use App\Services\Collect\SubmissionService;
use App\Services\FormSchema;
use App\Services\PlanPolicy;

/** Public response pages: /f/{token}. No login; the token is the only key. */
final class PublicFormController extends Controller
{
    public function show(Request $request): Response
    {
        [$campaign, $form] = $this->load($request);
        if (($gate = $this->gate($campaign, $form)) !== null) {
            return $gate;
        }
        $lang = $this->language($request, $form);

        return $this->page($campaign, $form, $lang, [], [], 200);
    }

    public function submit(Request $request): Response
    {
        [$campaign, $form] = $this->load($request);
        if (($gate = $this->gate($campaign, $form)) !== null) {
            return $gate;
        }
        $lang = $this->language($request, $form);
        // Honeypot: real users never fill this hidden field.
        if ($request->string('website') !== '') {
            return $this->redirect('/f/' . $campaign['token'] . '/merci');
        }
        $input = $request->post();
        $result = (new SubmissionService($this->app->db()))->submit($campaign, $form, $input, $lang);

        return match ($result['status']) {
            'saved', 'duplicate' => $this->redirect('/f/' . $campaign['token'] . '/merci?lang=' . rawurlencode($lang)),
            'closed' => $this->closed($campaign, $form, (string) $result['reason']),
            default => $this->page($campaign, $form, $lang, $input, $result['errors'], 422),
        };
    }

    public function thanks(Request $request): Response
    {
        [$campaign, $form] = $this->load($request);
        $lang = $this->language($request, $form);

        return $this->view('collect/thanks', [
            'title' => FormSchema::label($form['settings']['title'] ?? null, $lang),
            'campaign' => $campaign,
            'watermark' => $this->watermark($campaign),
            'lang' => $lang,
        ], 200, 'layouts/public');
    }

    public function unlock(Request $request): Response
    {
        [$campaign, $form] = $this->load($request);
        $hash = (string) ($campaign['access_code_hash'] ?? '');
        if ($hash !== '' && password_verify($request->string('access_code'), $hash)) {
            $this->app->session()->regenerate();
            $this->app->session()->set('campaign_unlocked_' . $campaign['id'], true);

            return $this->redirect('/f/' . $campaign['token']);
        }
        sleep(1);

        return $this->view('collect/code', [
            'title' => FormSchema::label($form['settings']['title'] ?? null, (string) $form['default_language']),
            'campaign' => $campaign,
            'error' => t('collect.code_wrong'),
        ], 403, 'layouts/public');
    }

    /** @return array{0: array<string, mixed>, 1: array<string, mixed>} */
    private function load(Request $request): array
    {
        $campaign = (new CampaignRepository($this->app->db()))->findByToken($request->params['token'] ?? '') ?? throw new HttpException(404);
        $version = $this->app->db()->fetchOne(
            "SELECT schema_json FROM form_versions WHERE id = ? AND organization_id = ? AND status = 'published'",
            [$campaign['form_version_id'], $campaign['organization_id']],
        ) ?? throw new HttpException(404);
        $form = json_decode((string) $version['schema_json'], true, 64, JSON_THROW_ON_ERROR);

        return [$campaign, is_array($form) ? $form : throw new HttpException(404)];
    }

    /**
     * @param array<string, mixed> $campaign
     * @param array<string, mixed> $form
     */
    private function gate(array $campaign, array $form): ?Response
    {
        $orgId = (int) $campaign['organization_id'];
        $submissions = new SubmissionRepository($this->app->db());
        $monthlyLimit = PlanPolicy::forOrganization($this->app->db(), $orgId)->limit('responses_per_month');
        $monthly = $monthlyLimit !== null ? $submissions->countForOrganizationSince($orgId, gmdate('Y-m-01 00:00:00')) : null;
        $reason = CampaignService::closedReason($campaign, $submissions->countForCampaign($orgId, (int) $campaign['id']), $monthly, $monthlyLimit);
        if ($reason !== null) {
            return $this->closed($campaign, $form, $reason);
        }
        if (($campaign['access_code_hash'] ?? null) !== null && $this->app->session()->get('campaign_unlocked_' . $campaign['id']) !== true) {
            return $this->view('collect/code', [
                'title' => FormSchema::label($form['settings']['title'] ?? null, (string) $form['default_language']),
                'campaign' => $campaign,
                'error' => null,
            ], 200, 'layouts/public');
        }

        return null;
    }

    /**
     * @param array<string, mixed> $campaign
     * @param array<string, mixed> $form
     */
    private function closed(array $campaign, array $form, string $reason): Response
    {
        return $this->view('collect/closed', [
            'title' => FormSchema::label($form['settings']['title'] ?? null, (string) $form['default_language']),
            'reason' => $reason,
            'watermark' => $this->watermark($campaign),
        ], 410, 'layouts/public');
    }

    /**
     * @param array<string, mixed> $campaign
     * @param array<string, mixed> $form
     * @param array<string, mixed> $values
     * @param array<string, array{code: string, message: ?string}> $errors
     */
    private function page(array $campaign, array $form, string $lang, array $values, array $errors, int $status): Response
    {
        $renderer = new FormRenderer($form, $lang, $values, $errors, static fn(string $key): string => t($key));

        return $this->view('collect/form', [
            'title' => FormSchema::label($form['settings']['title'] ?? null, $lang),
            'description' => FormSchema::label(is_array($form['settings']['description'] ?? null) ? $form['settings']['description'] : null, $lang),
            'estimated' => (int) ($form['settings']['estimated_minutes'] ?? 0),
            'campaign' => $campaign,
            'languages' => array_values(array_filter((array) ($form['languages'] ?? []), 'is_string')),
            'lang' => $lang,
            'fieldsHtml' => $renderer->render(),
            'errorCount' => count($errors),
            'submission' => is_string($values['_submission'] ?? null) && Uuid::isValid($values['_submission']) ? $values['_submission'] : Uuid::v4(),
            'started' => is_string($values['_started'] ?? null) ? $values['_started'] : gmdate('Y-m-d H:i:s'),
            'watermark' => $this->watermark($campaign),
        ], $status, 'layouts/public');
    }

    /** @param array<string, mixed> $form */
    private function language(Request $request, array $form): string
    {
        $languages = array_values(array_filter((array) ($form['languages'] ?? []), 'is_string'));
        $asked = (string) $request->input('lang', '');

        return in_array($asked, $languages, true) ? $asked : (string) ($form['default_language'] ?? 'fr');
    }

    /** @param array<string, mixed> $campaign */
    private function watermark(array $campaign): bool
    {
        return PlanPolicy::forOrganization($this->app->db(), (int) $campaign['organization_id'])->flag('watermark');
    }
}
