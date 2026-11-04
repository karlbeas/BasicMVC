<?php

declare(strict_types=1);

namespace App\Controllers\Collect;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\CampaignRepository;
use App\Models\FormRepository;
use App\Models\SubmissionRepository;
use App\Services\AuditLogger;
use App\Services\Collect\CampaignService;
use App\Services\Collect\FormNotCollectableException;
use App\Services\Collect\ResponseExporter;
use App\Services\PlanPolicy;
use App\Services\QuotaExceededException;
use App\Services\ValidationException;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

final class CampaignController extends Controller
{
    /** POST /forms/{form}/versions/{version}/campaigns */
    public function store(Request $request): Response
    {
        $ctx = $this->authorize('form.publish');
        $orgId = $ctx->organizationId();
        $forms = new FormRepository($this->app->db());
        $form = $forms->findByPublicId($orgId, $request->params['form'] ?? '') ?? throw new HttpException(404);
        $version = $forms->findVersionByPublicId($orgId, $request->params['version'] ?? '');
        if ($version === null || (int) $version['form_id'] !== (int) $form['id']) {
            throw new HttpException(404);
        }
        try {
            $id = $this->service()->open($ctx, (int) $form['id'], (int) $version['id'], [
                'title' => $request->string('title'),
                'closes_at' => $request->string('closes_at'),
                'response_limit' => $request->string('response_limit'),
                'access_code' => $request->string('access_code'),
            ]);
        } catch (ValidationException $e) {
            $this->flash('danger', implode(' ', array_map(static fn(string $k): string => t($k), $e->errors)));

            return $this->redirect('/forms/' . $form['public_id']);
        } catch (QuotaExceededException $e) {
            $this->flash('danger', t('quota.' . $e->limitKey, ['limit' => $e->limit]));

            return $this->redirect('/forms/' . $form['public_id']);
        } catch (FormNotCollectableException $e) {
            $this->flash('danger', t('campaigns.error.not_collectable') . ' ' . implode(' · ', array_column($e->blockers, 'path')));

            return $this->redirect('/forms/' . $form['public_id']);
        }
        $campaign = (new CampaignRepository($this->app->db()))->find($orgId, $id);
        $this->flash('success', t('campaigns.opened'));

        return $this->redirect('/campaigns/' . ($campaign['public_id'] ?? ''));
    }

    public function show(Request $request): Response
    {
        $ctx = $this->authorize('project.view');
        $orgId = $ctx->organizationId();
        $campaign = $this->campaign($request);
        $submissions = new SubmissionRepository($this->app->db());
        $count = $submissions->countForCampaign($orgId, (int) $campaign['id']);
        $version = (new FormRepository($this->app->db()))->findVersion($orgId, (int) $campaign['form_version_id']);
        $form = $this->app->db()->fetchOne('SELECT * FROM forms WHERE organization_id = ? AND id = ?', [$orgId, $campaign['form_id']]);
        $schema = json_decode((string) ($version['schema_json'] ?? '{}'), true, 64, JSON_THROW_ON_ERROR);
        $policy = PlanPolicy::forOrganization($this->app->db(), $orgId);
        $monthlyLimit = $policy->limit('responses_per_month');
        $monthly = $monthlyLimit !== null ? $submissions->countForOrganizationSince($orgId, gmdate('Y-m-01 00:00:00')) : null;
        $page = max(1, (int) $request->query('page', 1));

        return $this->view('campaigns/show', [
            'campaign' => $campaign,
            'form' => $form,
            'version' => $version,
            'publicUrl' => $this->app->absoluteUrl('f/' . $campaign['token'], $request),
            'count' => $count,
            'closedReason' => CampaignService::closedReason($campaign, $count, $monthly, $monthlyLimit),
            'columns' => ResponseExporter::columns($schema),
            'rows' => $submissions->latest($orgId, (int) $campaign['id'], 50, ($page - 1) * 50),
            'page' => $page,
            'canManage' => $ctx->can('form.publish'),
        ]);
    }

    public function status(Request $request): Response
    {
        $ctx = $this->authorize('form.publish');
        $campaign = $this->campaign($request);
        $open = $request->string('status') === 'open';
        try {
            $this->service()->setOpen($ctx, (int) $campaign['id'], $open);
            $this->flash('success', t($open ? 'campaigns.reopened' : 'campaigns.closed'));
        } catch (QuotaExceededException $e) {
            $this->flash('danger', t('quota.' . $e->limitKey, ['limit' => $e->limit]));
        }

        return $this->redirect('/campaigns/' . $campaign['public_id']);
    }

    public function qr(Request $request): Response
    {
        $this->authorize('project.view');
        $campaign = $this->campaign($request);
        $svg = (new QRCode(new QROptions([
            'outputType' => QROutputInterface::MARKUP_SVG,
            'outputBase64' => false,
            'eccLevel' => EccLevel::M,
            'addQuietzone' => true,
        ])))->render($this->app->absoluteUrl('f/' . $campaign['token'], $request));
        $headers = [
            'Content-Type' => 'image/svg+xml; charset=utf-8',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'",
            'Cache-Control' => 'private, max-age=3600',
        ];
        if ($request->query('download') !== null) {
            $headers['Content-Disposition'] = 'attachment; filename="qr-' . $campaign['token'] . '.svg"';
        }

        return new Response((string) $svg, 200, $headers);
    }

    /** CSV and JSON exports are always available, on every plan (MANIFEST §2). */
    public function export(Request $request): Response
    {
        $ctx = $this->authorize('project.view');
        $orgId = $ctx->organizationId();
        $campaign = $this->campaign($request);
        $format = $request->params['format'] ?? '';
        $version = (new FormRepository($this->app->db()))->findVersion($orgId, (int) $campaign['form_version_id']);
        $schema = json_decode((string) ($version['schema_json'] ?? '{}'), true, 64, JSON_THROW_ON_ERROR);
        $exporter = new ResponseExporter(new SubmissionRepository($this->app->db()), $schema);
        $base = 'reponses-' . preg_replace('/[^A-Za-z0-9_-]/', '_', (string) $campaign['token']) . '-' . gmdate('Ymd');
        (new AuditLogger($this->app->db()))->log($orgId, $ctx->userId(), 'campaign.exported', 'campaign', (int) $campaign['id'], ['format' => $format]);

        return match ($format) {
            'csv' => new Response($exporter->csv($orgId, (int) $campaign['id'], $this->app->lang()->locale() === 'fr' ? ';' : ','), 200, [
                'Content-Type' => 'text/csv; charset=utf-8',
                'Content-Disposition' => 'attachment; filename="' . $base . '.csv"',
            ]),
            'json' => new Response($exporter->json($orgId, (int) $campaign['id']), 200, [
                'Content-Type' => 'application/json; charset=utf-8',
                'Content-Disposition' => 'attachment; filename="' . $base . '.json"',
            ]),
            default => throw new HttpException(404),
        };
    }

    private function service(): CampaignService
    {
        return new CampaignService($this->app->db(), new AuditLogger($this->app->db()));
    }

    /** @return array<string, mixed> */
    private function campaign(Request $request): array
    {
        return (new CampaignRepository($this->app->db()))->findByPublicId($this->ctx()->organizationId(), $request->params['campaign'] ?? '')
            ?? throw new HttpException(404);
    }
}
