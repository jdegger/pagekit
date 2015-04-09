<?php

use Pagekit\Filesystem\Adapter\FileAdapter;
use Pagekit\Filesystem\Adapter\StreamAdapter;
use Pagekit\System\Event\MaintenanceListener;
use Pagekit\System\Event\MigrationListener;
use Pagekit\System\Event\SystemListener;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpKernel\EventListener\ExceptionListener;

return [

    'name' => 'system',

    'main' => function ($app) {

        if (!$app['debug']) {
            $app->subscribe(new ExceptionListener('Pagekit\System\Controller\ExceptionController::showAction'));
        }

        $app->subscribe(
            new MaintenanceListener,
            new MigrationListener,
            new SystemListener
        );

        $app->factory('finder', function() {
            return Finder::create();
        });

        $app['module']['auth']->config['rememberme.key'] = $this->config('key');

        $this->config['storage'] = '/'.trim(($this->config['storage'] ?: 'storage'), '/');
        $app['path.storage'] = rtrim($app['path'].$this->config['storage'], '/');

        $app['db.em']; // -TODO- fix me

        $app['system'] = $this;

        $app->on('kernel.boot', function() use ($app) {

            $app['module']->load($this->config['extensions']);

            if ($app->inConsole()) {
                $app['isAdmin'] = false;
                $app->trigger('system.init');
            }

        });

        $app->on('kernel.request', function($event) use ($app) {

            if (!$event->isMasterRequest()) {
                return;
            }

            $request = $event->getRequest();
            $baseUrl = $request->getSchemeAndHttpHost().$request->getBaseUrl();

            $app['file']->registerAdapter('file', new FileAdapter($app['path'], $baseUrl));
            $app['file']->registerAdapter('app', new StreamAdapter($app['path'], $baseUrl));

            $app['isAdmin'] = (bool) preg_match('#^/admin(/?$|/.+)#', $request->getPathInfo());

            $app->trigger('system.init', $event);

        }, 50);

        $app->on('kernel.request', function($event) use ($app) {

            if (!$event->isMasterRequest()) {
                return;
            }

            $app->trigger('system.loaded', $event);

        });

        $app->on('system.loaded', function () use ($app) {
            foreach ($app['module'] as $module) {

                if (!isset($module->resources)) {
                    continue;
                }

                foreach ($module->resources as $prefix => $path) {
                    $app['locator']->add($prefix, "$module->path/$path");
                }
            }
        });

    },

    'require' => [

        'profiler',
        'application',
        'feed',
        'markdown',
        'migration',
        'package',
        'tree',
        'system/cache',
        'system/comment',
        'system/console',
        'system/content',
        'system/dashboard',
        'system/editor',
        'system/finder',
        'system/info',
        'system/locale',
        'system/mail',
        'system/menu',
        'system/oauth',
        'system/option',
        'system/package',
        'system/settings',
        'system/site',
        'system/theme',
        'system/user',
        'system/view',
        'system/widget'

    ],

    'include' => 'modules/*/module.php',

    'autoload' => [

        'Pagekit\\System\\' => 'src'

    ],

    'resources' => [

        'system:' => ''

    ],

    'controllers' => [

        '@system: /' => [
            'Pagekit\\System\\Controller\\AdminController'
        ],

        '@system: /system' => [
            'Pagekit\\System\\Controller\\MigrationController',
            'Pagekit\\System\\Controller\\UpdateController',
            'Pagekit\\System\\Controller\\SystemController'
        ]

    ],

    'permissions' => [

        'system: manage themes' => [
            'title' => 'Manage themes'
        ],
        'system: manage extensions' => [
            'title' => 'Manage extensions'
        ],
        'system: access settings' => [
            'title' => 'Access system settings',
            'description' => 'Warning: Give to trusted roles only; this permission has security implications.'
        ],
        'system: software updates' => [
            'title' => 'Apply system updates',
            'description' => 'Warning: Give to trusted roles only; this permission has security implications.'
        ],
        'system: manage storage' => [
            'title' => 'Manage storage',
            'description' => 'Warning: Give to trusted roles only; this permission has security implications.'
        ],
        'system: manage storage read only' => [
            'title' => 'Manage storage (Read only)'
        ],
        'system: maintenance access' => [
            'title' => 'Use the site in maintenance mode'
        ]

    ],

    'config' => [

        'extensions' => [],

        'dashboard' => [

            'default' => [
                '1' => [
                    'type' => 'widget.user'
                ]
            ]

        ],

        'key' => '',
        'frontpage' => '',

        'site' => [
            'title' => '',
            'description' => ''
        ],

        'maintenance' => [
            'enabled' => false,
            'msg' => ''
        ],

        'api' => [
            'key' => '',
            'url' => 'http://pagekit.com/api',
        ],

        'release_channel' => 'stable',

        'storage' => '/storage',

        'theme.site' => 'alpha'

    ]

];
