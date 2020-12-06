<?php

namespace AntiC\User\Provider;

use Silex\Application;
use Silex\ServiceProviderInterface;
use Silex\ControllerProviderInterface;
use Silex\ControllerCollection;
use Silex\ServiceControllerResolver;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use AntiC\User\Manager\UserManager;
use AntiC\User\Controller\UserController;

/**
 * Provides the User Service
 *
 * @author Jason Grimes <https://github.com/jasongrimes>
 * @author Jeremy Smereka <jeremy.smereka@ekoed.com> ** Modified **
 *
 * @modifications Changed UserManager and UserController to AntiC
 */
class UserServiceProvider implements ServiceProviderInterface, ControllerProviderInterface
{
    /**
     * Registers services on the given app.
     *
     * This method should only be used to configure services and parameters.
     * It should not get services.
     *
     * @param Application $app An Application instance
     *
     * @modifications Removed Voter 
     */
    public function register(Application $app)
    {
        $app['user.manager'] = $app->share(function($app) { return new UserManager($app['db'], $app); });

        $app['user'] = $app->share(function($app) {
            return ($app['user.manager']->getCurrentUser());
        });

        $app['user.controller'] = $app->share(function ($app) {
            return new UserController($app['user.manager']);
        });
    }

    /**
     * Bootstraps the application.
     *
     * This method is called after all services are registers
     * and should be used for "dynamic" configuration (whenever
     * a service must be requested).
     *
     * @modifications Changed Directory
     */
    public function boot(Application $app)
    {
        // Add twig template path.
        if ($app->offsetExists('twig.loader.filesystem')) {
            $app['twig.loader.filesystem']->addPath(__DIR__ . '/../views/', 'user');
        }

    }

    /**
     * Returns routes to connect to the given application.
     *
     * @param Application $app An Application instance
     *
     * @return ControllerCollection A ControllerCollection instance
     * @throws \LogicException if ServiceController service provider is not registered.
     *
     * @modifications Changed Paths, Commented out Registration
     */
    public function connect(Application $app)
    {
        if (!$app['resolver'] instanceof ServiceControllerResolver) {
            // using RuntimeException crashes PHP?!
            throw new \LogicException('You must enable the ServiceController service provider to be able to use these routes.');
        }

        /** 
         * @var ControllerCollection $controllers 
         */
        $controllers = $app['controllers_factory'];

        $controllers->get('/account', 'user.controller:viewAction')
            ->bind('user.account')
            ->before(function(Request $request) use ($app) {
                // Require login. This should never actually cause access to be denied,
                // but it causes a login form to be rendered if the viewer is not logged in.
                if (!$app['user']) {
                    throw new AccessDeniedException();
                }
            });

        $controllers->method('GET|POST')->match('/user/add', 'user.controller:addAction')
            ->bind('user.add')
            ->before(function(Request $request) use ($app) {
                if (!$app['user'] || !$app['user']->hasRole('ROLE_ADMIN')) {
                    throw new AccessDeniedException();
                }
            });

        $controllers->method('GET|POST')->match('/user/{id}', 'user.controller:editAction')
            ->bind('user.edit')
            ->before(function(Request $request) use ($app) {
                if (!$app['user'] || !$app['user']->hasRole('ROLE_ADMIN')) {
                    throw new AccessDeniedException();
                }
            });

        $controllers->get('/user', 'user.controller:listAction')
            ->bind('user.list')
            ->before(function(Request $request) use ($app) {
                if (!$app['user'] || !$app['user']->hasRole('ROLE_ADMIN')) {
                    throw new AccessDeniedException();
                }
            });

        $controllers->post('/user/{id}/enable', 'user.controller:enableAction')
            ->bind('user.enable')
            ->before(function(Request $request) use ($app) {
                if (!$app['user'] || !$app['user']->hasRole('ROLE_ADMIN')) {
                    throw new AccessDeniedException();
                } 
            });

        $controllers->method('GET|POST')->match('/iforgot', 'user.controller:iforgotAction')
            ->bind('user.iforgot');

        $controllers->get('/login', 'user.controller:loginAction')
            ->bind('user.login')
            ->after(function(Request $request) use ($app) {
                $app['session']->invalidate();
            });

        // login_check and logout are dummy routes so we can use the names.
        // The security provider should intercept these, so no controller is needed.
        $controllers->method('GET|POST')->match('/login_check', function() {})
            ->bind('user.login_check');
        $controllers->get('/logout', function() {})
            ->bind('user.logout');

        return $controllers;
    }
}
