<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * Root aplikasi sekarang MENGARAHKAN ke halaman login (bukan render 200),
     * jadi test bawaan Laravel (harap 200) sudah usang.
     */
    public function test_root_mengarahkan_ke_halaman_login(): void
    {
        $this->get('/')->assertRedirect('/login');
    }

    public function test_halaman_login_bisa_dibuka(): void
    {
        $this->get('/login')->assertOk();
    }

    public function test_health_check_ok(): void
    {
        $this->get('/up')->assertOk();
    }
}
