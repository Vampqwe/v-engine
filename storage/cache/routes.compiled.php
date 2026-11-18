<?php

declare(strict_types=1);

return array (
  'routes' => 
  array (
    0 => 
    array (
      'method' => 'GET',
      'pattern' => '/api/health',
      'handler' => 
      array (
        0 => 'App\\Controllers\\ApiController',
        1 => 'health',
      ),
      'middlewares' => 
      array (
        0 => 'App\\Middleware\\RateLimitMiddleware',
      ),
      'name' => 'health',
      'regex' => '#^/api/health$#u',
      'paramNames' => 
      array (
      ),
    ),
    1 => 
    array (
      'method' => 'GET',
      'pattern' => '/api/profile',
      'handler' => 
      array (
        0 => 'App\\Controllers\\ApiController',
        1 => 'profile',
      ),
      'middlewares' => 
      array (
        0 => 'App\\Middleware\\RateLimitMiddleware',
        1 => 'App\\Middleware\\AuthMiddleware',
      ),
      'name' => 'profile',
      'regex' => '#^/api/profile$#u',
      'paramNames' => 
      array (
      ),
    ),
    2 => 
    array (
      'method' => 'GET',
      'pattern' => '/',
      'handler' => 
      array (
        0 => 'App\\Controllers\\HomeController',
        1 => 'index',
      ),
      'middlewares' => 
      array (
      ),
      'name' => 'home',
      'regex' => '#^/$#u',
      'paramNames' => 
      array (
      ),
    ),
    3 => 
    array (
      'method' => 'GET',
      'pattern' => '/hello/{name}',
      'handler' => 
      array (
        0 => 'App\\Controllers\\HomeController',
        1 => 'hello',
      ),
      'middlewares' => 
      array (
      ),
      'name' => 'hello',
      'regex' => '#^/hello/(?P<name>[^/]+)$#u',
      'paramNames' => 
      array (
        0 => 'name',
      ),
    ),
    4 => 
    array (
      'method' => 'POST',
      'pattern' => '/',
      'handler' => 
      array (
        0 => 'App\\Controllers\\HomeController',
        1 => 'submit',
      ),
      'middlewares' => 
      array (
        0 => 'App\\Middleware\\CsrfMiddleware',
      ),
      'name' => NULL,
      'regex' => '#^/$#u',
      'paramNames' => 
      array (
      ),
    ),
    5 => 
    array (
      'method' => 'GET',
      'pattern' => '/admin/login',
      'handler' => 
      array (
        0 => 'Modules\\Admin\\Controllers\\AuthController',
        1 => 'showLogin',
      ),
      'middlewares' => 
      array (
      ),
      'name' => 'login',
      'regex' => '#^/admin/login$#u',
      'paramNames' => 
      array (
      ),
    ),
    6 => 
    array (
      'method' => 'POST',
      'pattern' => '/admin/login',
      'handler' => 
      array (
        0 => 'Modules\\Admin\\Controllers\\AuthController',
        1 => 'login',
      ),
      'middlewares' => 
      array (
        0 => 'Modules\\Admin\\Middleware\\LoginRateLimitMiddleware',
        1 => 'App\\Middleware\\CsrfMiddleware',
      ),
      'name' => 'login.post',
      'regex' => '#^/admin/login$#u',
      'paramNames' => 
      array (
      ),
    ),
    7 => 
    array (
      'method' => 'GET',
      'pattern' => '/admin',
      'handler' => 
      array (
        0 => 'Modules\\Admin\\Controllers\\DashboardController',
        1 => 'index',
      ),
      'middlewares' => 
      array (
        0 => 'Modules\\Admin\\Middleware\\AdminAuthMiddleware',
      ),
      'name' => 'dashboard',
      'regex' => '#^/admin$#u',
      'paramNames' => 
      array (
      ),
    ),
    8 => 
    array (
      'method' => 'POST',
      'pattern' => '/admin/logout',
      'handler' => 
      array (
        0 => 'Modules\\Admin\\Controllers\\AuthController',
        1 => 'logout',
      ),
      'middlewares' => 
      array (
        0 => 'Modules\\Admin\\Middleware\\AdminAuthMiddleware',
        1 => 'App\\Middleware\\CsrfMiddleware',
      ),
      'name' => 'logout',
      'regex' => '#^/admin/logout$#u',
      'paramNames' => 
      array (
      ),
    ),
  ),
  'named' => 
  array (
    'api.health' => 0,
    'api.profile' => 1,
    'home' => 2,
    'hello' => 3,
    'admin.login' => 5,
    'admin.login.post' => 6,
    'admin.dashboard' => 7,
    'admin.logout' => 8,
  ),
);
