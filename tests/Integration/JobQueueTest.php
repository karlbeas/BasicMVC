<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Jobs\Dispatcher;
use App\Jobs\JobQueue;

final class JobQueueTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->db()->execute('DELETE FROM jobs');
    }

    public function testPushReserveComplete(): void
    {
        $queue = new JobQueue($this->db());
        $id = $queue->push('system.ping', ['steps' => 2]);
        $job = $queue->reserve('test');

        self::assertSame($id, (int) ($job['id'] ?? 0));
        self::assertSame(1, $job['attempts'] ?? null);
        self::assertNull($queue->reserve('test'), 'A running job cannot be reserved twice');

        $queue->complete($id, ['ok' => true]);
        self::assertSame('done', $queue->find($id)['status'] ?? null);
    }

    public function testDelayedJobIsNotAvailableYet(): void
    {
        $queue = new JobQueue($this->db());
        $queue->push('system.ping', [], null, 3600);

        self::assertNull($queue->reserve('test'));
    }

    public function testFailureRetriesWithBackoffThenFails(): void
    {
        $queue = new JobQueue($this->db());
        $id = $queue->push('system.ping', [], null, 0, 2);

        $queue->reserve('test');
        $queue->fail($id, 'boom');
        $job = $queue->find($id);
        self::assertSame('pending', $job['status'] ?? null);
        self::assertGreaterThan(gmdate('Y-m-d H:i:s'), (string) ($job['available_at'] ?? ''));

        $this->db()->execute('UPDATE jobs SET available_at = ? WHERE id = ?', [gmdate('Y-m-d H:i:s', time() - 1), $id]);
        $queue->reserve('test');
        $queue->fail($id, 'boom again');
        self::assertSame('failed', $queue->find($id)['status'] ?? null);
    }

    public function testStaleRunningJobsAreReleased(): void
    {
        $queue = new JobQueue($this->db());
        $id = $queue->push('system.ping');
        $queue->reserve('killed-process');
        $this->db()->execute('UPDATE jobs SET reserved_at = ? WHERE id = ?', [gmdate('Y-m-d H:i:s', time() - 3600), $id]);

        self::assertSame(1, $queue->releaseStale(600));
        self::assertSame('pending', $queue->find($id)['status'] ?? null);
    }

    public function testDispatcherResumesYieldedJobs(): void
    {
        $queue = new JobQueue($this->db());
        $id = $queue->push('system.ping', ['steps' => 3]);
        $dispatcher = new Dispatcher($this->app);

        // 1 s budget: the handler yields immediately (it keeps a 1 s safety margin).
        self::assertSame('yielded', $dispatcher->runOne($id, 1));
        $job = $queue->find($id);
        self::assertSame('pending', $job['status'] ?? null);
        self::assertSame(0, (int) ($job['attempts'] ?? -1), 'A yield must not consume an attempt');
        self::assertSame(['done' => 0], json_decode((string) ($job['progress'] ?? ''), true));

        $stats = $dispatcher->run(10);
        self::assertSame(1, $stats['done']);
        self::assertSame(['pong' => true, 'steps' => 3], array_intersect_key(json_decode((string) $queue->find($id)['result'], true), ['pong' => 1, 'steps' => 1]));
    }

    public function testJobsKilledByTheHostAreReleasedSoonEvenWithAnOldEnvSetting(): void
    {
        $db = $this->db();
        $_ENV['JOBS_STALE_AFTER'] = '600'; // written in .env by the installer before v0.4.2
        $this->app = \App\Core\App::boot($this->app->root, false);
        $this->app->setDatabase($db);
        unset($_ENV['JOBS_STALE_AFTER']);
        $queue = new JobQueue($this->db());
        $id = $queue->push('system.ping');
        $queue->reserve('killed');
        $this->db()->execute('UPDATE jobs SET reserved_at = ? WHERE id = ?', [gmdate('Y-m-d H:i:s', time() - 200), $id]);

        self::assertSame('done', (new Dispatcher($this->app))->runOne($id, 5), 'Released after 180 s (AI timeout + 60) despite JOBS_STALE_AFTER=600, then run');
    }

    public function testCronHeartbeat(): void
    {
        (new Dispatcher($this->app))->run(3);
        self::assertLessThanOrEqual(2, Dispatcher::heartbeatAge($this->app));
    }

    public function testUnknownJobTypeFails(): void
    {
        $queue = new JobQueue($this->db());
        $id = $queue->push('does.not.exist', [], null, 0, 1);

        self::assertSame('failed', (new Dispatcher($this->app))->runOne($id, 5));
        self::assertStringContainsString('No handler', (string) $queue->find($id)['error']);
    }
}
