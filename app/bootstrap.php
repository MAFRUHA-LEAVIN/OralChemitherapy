<?php

/**
 * Bootstrap
 * Configuration settings, and required libraries
 */

require_once __DIR__.'/../vendor/autoload.php';

$app = new Silex\Application();

$app['debug'] = true;

// Database Connection Registration
$app->register(new Silex\Provider\DoctrineServiceProvider(), array(
    'db.options' => array(
        'driver'    => 'pdo_mysql',
        'host'      => 'localhost',
        'dbname'    => 'cancerdrugdb',
        'user'      => 'root',
        'password'  => 'root',
        'charset'   => 'utf8',
    ),
));
 
// Security System Registration
$app->register(new Silex\Provider\SecurityServiceProvider(), array(
    'security.firewalls' => array(
        'default' => array(
            'pattern'      => '^/console',
            'anonymous'    => true,
            'form'         => array(
                'login_path' => '/console/login', 
                'check_path' => '/console/login_check',
                'default_target_path' => '/console'
            ),
            'logout'       => array(
                'logout_path' => '/console/logout',
                'target' => '/console/login'
            ),
            'users'        => $app->share(function() use ($app){
                return $app['user.manager'];
            }),
        ),
    ),
));
$app['security.encoder.digest'] = $app->share(function ($app) {
    return new Symfony\Component\Security\Core\Encoder\BCryptPasswordEncoder(12);
});

$app->register(new Silex\Provider\RememberMeServiceProvider());
$app->register(new Silex\Provider\ServiceControllerServiceProvider()); 
$app->register(new Silex\Provider\SessionServiceProvider());
$app->register(new Silex\Provider\UrlGeneratorServiceProvider());

// Template Lookup Registration
$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => array(
        __DIR__.'/../src/AntiC/Console/Views',
        __DIR__.'/../src/AntiC/LiveView/Views',
    ),
));

return $app;