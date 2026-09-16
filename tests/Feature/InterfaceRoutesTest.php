<?php

use App\Domains\Users\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('renders each public interface route for guests', function (string $uri, string $component) {
    $this->get($uri)
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page->component($component));
})->with([
    'login' => ['/login', 'auth/views/Login'],
    'register' => ['/register', 'auth/views/Register'],
]);

it('renders each protected interface route for an authenticated superadmin', function (string $uri, string $component) {
    $this->actingAs(User::factory()->superAdmin()->create());

    $this->get($uri)
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page->component($component));
})->with([
    'dashboard' => ['/dashboard', 'dashboard/views/Dashboard'],
    'users' => ['/usuarios', 'users/views/Index'],
    'create user' => ['/usuarios/nuevo', 'users/views/Create'],
    'professionals' => ['/profesionales', 'professionals/views/Index'],
    'create professional' => ['/profesionales/nuevo', 'professionals/views/Create'],
    'patients' => ['/pacientes', 'patients/views/Index'],
    'create patient' => ['/pacientes/nuevo', 'patients/views/Create'],
    'locations' => ['/organizacion/sedes', 'organizations/views/Index'],
    'create location' => ['/organizacion/sedes/nueva', 'organizations/views/Create'],
    'legal entities' => ['/organizacion/razones-sociales', 'organizations/legal-entities/Index'],
    'create legal entity' => ['/organizacion/razones-sociales/nueva', 'organizations/legal-entities/Create'],
    'appointments' => ['/turnos', 'appointments/views/Index'],
    'create appointment' => ['/turnos/crear', 'appointments/views/Create'],
]);

it('redirects the application root to login', function () {
    $this->get('/')->assertRedirect('/login');
});
