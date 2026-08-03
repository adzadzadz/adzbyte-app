<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_the_root_customer_dashboard_requires_authentication(): void
    {
        $this->get('/')->assertRedirect('/login');
    }
}
