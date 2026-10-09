<?php
declare(strict_types=1);

use Nivc\Controllers\Api\{ClientsApi, IngestApi, LandingPagesApi, LeadsApi, MiscApi, UsersApi, WebsitesApi};
use Nivc\Controllers\Web\{AuthController, PageController};
use Nivc\Core\Router;

return static function (Router $r): void {
    $auth   = ['auth'];
    $admin  = ['auth', 'admin'];
    $client = ['auth', 'client'];
    $pub    = ['public'];

    /* ---- public ingest (token authenticated, no session / CSRF) ---- */
    $r->post('/api/v1/leads', [IngestApi::class, 'leads'], $pub);
    $r->get('/api/v1/ping', [IngestApi::class, 'ping'], $pub);
    $r->post('/api/v1/track', [IngestApi::class, 'track'], $pub);
    $r->add('OPTIONS', '/api/v1/track', [IngestApi::class, 'trackPreflight'], $pub);

    /* ---- authentication pages ---- */
    $r->get('/', [AuthController::class, 'home'], []);
    $r->get('/login', [AuthController::class, 'loginForm'], []);
    $r->post('/login', [AuthController::class, 'login'], ['selfcsrf']);
    $r->post('/logout', [AuthController::class, 'logout'], $auth);
    $r->get('/forgot', [AuthController::class, 'forgotForm'], []);
    $r->post('/forgot', [AuthController::class, 'forgot'], ['selfcsrf']);
    $r->get('/reset/{token}', [AuthController::class, 'resetForm'], []);
    $r->post('/reset/{token}', [AuthController::class, 'reset'], ['selfcsrf']);
    $r->post('/set-language', [MiscApi::class, 'locale'], []);

    /* ---- admin pages ---- */
    foreach (['' => 'admin-dashboard', '/clients' => 'admin-clients', '/leads' => 'leads', '/landing-pages' => 'landing-pages', '/websites' => 'admin-websites',
              '/analytics' => 'analytics', '/billing' => 'admin-billing', '/notifications' => 'notifications', '/settings' => 'admin-settings', '/users' => 'admin-users'] as $path => $page) {
        $r->get('/admin' . $path, static fn($rq, $p) => PageController::render($rq, $page, 'admin'), $admin);
    }
    $r->get('/admin/clients/{id}', static fn($rq, $p) => PageController::render($rq, 'client-dashboard', 'admin', ['client_id' => $p['id']]), $admin);

    /* ---- client pages ---- */
    foreach (['/dashboard' => 'client-dashboard', '/leads' => 'leads', '/analytics' => 'analytics', '/landing-page' => 'landing-pages',
              '/notifications' => 'notifications', '/account' => 'account', '/support' => 'support'] as $path => $page) {
        $r->get($path, static fn($rq, $p) => PageController::render($rq, $page, 'client'), $client);
    }

    /* ---- JSON API (session + CSRF) ---- */
    $r->get('/api/dashboard/admin', [MiscApi::class, 'adminDashboard'], $admin);
    $r->get('/api/dashboard/client', [MiscApi::class, 'clientDashboard'], $auth);
    $r->get('/api/analytics', [MiscApi::class, 'analytics'], $auth);
    $r->get('/api/filters', [MiscApi::class, 'filters'], $auth);

    $r->get('/api/clients', [ClientsApi::class, 'list'], $admin);
    $r->get('/api/clients/options', [ClientsApi::class, 'options'], $admin);
    $r->post('/api/clients', [ClientsApi::class, 'create'], $admin);
    $r->get('/api/clients/{id}', [ClientsApi::class, 'get'], $admin);
    $r->put('/api/clients/{id}', [ClientsApi::class, 'update'], $admin);
    $r->delete('/api/clients/{id}', [ClientsApi::class, 'delete'], $admin);
    $r->post('/api/clients/{id}/status', [ClientsApi::class, 'setStatus'], $admin);
    $r->post('/api/clients/{id}/extend', [ClientsApi::class, 'extend'], $admin);

    $r->get('/api/users', [UsersApi::class, 'list'], $admin);
    $r->post('/api/users', [UsersApi::class, 'create'], $admin);
    $r->put('/api/users/{id}', [UsersApi::class, 'update'], $admin);
    $r->delete('/api/users/{id}', [UsersApi::class, 'delete'], $admin);

    $r->get('/api/websites', [WebsitesApi::class, 'list'], $admin);
    $r->post('/api/websites', [WebsitesApi::class, 'create'], $admin);
    $r->put('/api/websites/{id}', [WebsitesApi::class, 'update'], $admin);
    $r->delete('/api/websites/{id}', [WebsitesApi::class, 'delete'], $admin);
    $r->post('/api/websites/{id}/test', [WebsitesApi::class, 'test'], $admin);
    $r->post('/api/websites/{id}/rotate-token', [WebsitesApi::class, 'rotate'], $admin);

    $r->get('/api/landing-pages', [LandingPagesApi::class, 'list'], $auth);
    $r->post('/api/landing-pages', [LandingPagesApi::class, 'create'], $admin);
    $r->put('/api/landing-pages/{id}', [LandingPagesApi::class, 'update'], $admin);
    $r->delete('/api/landing-pages/{id}', [LandingPagesApi::class, 'delete'], $admin);
    $r->post('/api/landing-pages/{id}/status', [LandingPagesApi::class, 'setStatus'], $admin);
    $r->post('/api/landing-pages/{id}/duplicate', [LandingPagesApi::class, 'duplicate'], $admin);

    $r->get('/api/leads', [LeadsApi::class, 'list'], $auth);
    $r->get('/api/leads/export', [LeadsApi::class, 'export'], $auth);
    $r->get('/api/leads/{id}', [LeadsApi::class, 'get'], $auth);
    $r->put('/api/leads/{id}', [LeadsApi::class, 'update'], $auth);
    $r->post('/api/leads/{id}/notes', [LeadsApi::class, 'addNote'], $auth);
    $r->post('/api/leads/{id}/contact', [LeadsApi::class, 'contact'], $auth);

    $r->get('/api/notifications', [MiscApi::class, 'notifications'], $auth);
    $r->post('/api/notifications/read', [MiscApi::class, 'notificationsRead'], $auth);

    $r->get('/api/billing', [MiscApi::class, 'billing'], $admin);
    $r->put('/api/billing/{id}', [MiscApi::class, 'billingUpdate'], $admin);

    $r->get('/api/account', [MiscApi::class, 'account'], $auth);
    $r->put('/api/account', [MiscApi::class, 'accountUpdate'], $auth);
    $r->get('/api/settings', [MiscApi::class, 'settings'], $admin);
    $r->put('/api/settings', [MiscApi::class, 'settingsUpdate'], $admin);
};
