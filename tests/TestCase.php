<?php

namespace Tests;

use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Event;

abstract class TestCase extends BaseTestCase
{
    /** @var list<string> */
    private array $serverErrors = [];

    protected function setUp(): void
    {
        parent::setUp();

        // A request that crashes must never pass as "no validation errors".
        $this->serverErrors = [];
        Event::listen(RequestHandled::class, function (RequestHandled $e) {
            if ($e->response->getStatusCode() >= 500) {
                $this->serverErrors[] = $e->request->method().' '.$e->request->path().' => '.$e->response->getStatusCode();
            }
        });
    }

    protected function tearDown(): void
    {
        $errors = $this->serverErrors;
        $this->serverErrors = [];
        parent::tearDown();

        $this->assertSame([], $errors, 'A request returned a server error: '.implode(', ', $errors));
    }
}
