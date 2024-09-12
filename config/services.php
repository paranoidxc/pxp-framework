<?php

use Paranoid\Framework\Routing\RouterInterface;
use Paranoid\Framework\Session\SessionInterface;

$dotenv = parse_ini_file(dirname(__DIR__) . '/.env');
$_SERVER["dotenv"] = $dotenv;

$container = new \League\Container\Container();

$container->delegate(new \League\Container\ReflectionContainer(true));

$basePath = dirname(__DIR__);

$routes = include $basePath . '/routes/web.php';

$appEnv = $_SERVER["dotenv"]['APP_ENV'] ?? "prod";

$templatesPath = $basePath . '/templates';

$container->add('APP_ENV', new \League\Container\Argument\Literal\StringArgument($appEnv));

$databaseUrl = 'sqlite:///'.$basePath.'/var/db.sqlite';

$container->add(
    'base-commands-namespace',
    new \League\Container\Argument\Literal\StringArgument('Paranoid\\Framework\\Console\\Command\\')
);

$container->add(
    \Paranoid\Framework\Routing\RouterInterface::class,
    \Paranoid\Framework\Routing\Router::class,
);

$container->extend(\Paranoid\Framework\Routing\RouterInterface::class);

$container->add(
    \Paranoid\Framework\Http\Middleware\RequestHandlerInterface::class,
    \Paranoid\Framework\Http\Middleware\RequestHandler::class,
)->addArgument($container);


$container->addShared(\Paranoid\Framework\EventDispatcher\EventDispatcher::class);

$container->add(\Paranoid\Framework\Http\Kernel::class)
    ->addArguments([
        $container,
        \Paranoid\Framework\Http\Middleware\RequestHandlerInterface::class,
        \Paranoid\Framework\EventDispatcher\EventDispatcher::class,
    ]);


$container->add(\Paranoid\Framework\Console\Application::class)
    ->addArgument($container);

$container->add(\Paranoid\Framework\Console\Kernel::class)
    ->addArgument($container)
    ->addArgument( \Paranoid\Framework\Console\Application::class);


$container->addShared(
    \Paranoid\Framework\Session\SessionInterface::class,
    \Paranoid\Framework\Session\Session::class,
);
$container->add('template-renderer-factory', \Paranoid\Framework\Template\TwigFactory::class)
    ->addArgument(\Paranoid\Framework\Session\SessionInterface::class)
    ->addArgument(new \League\Container\Argument\Literal\StringArgument($templatesPath));
$container->addShared('twig', function() use ($container) {
    return $container->get('template-renderer-factory')->create();
});

$container->add(\Paranoid\Framework\Controller\AbstractController::class);

$container->inflector(\Paranoid\Framework\Controller\AbstractController::class)
   ->invokeMethod('setContainer', [$container]);

$container->add(\Paranoid\Framework\Dbal\ConnectionFactory::class)
    ->addArgument(
        new \League\Container\Argument\Literal\StringArgument($databaseUrl)
    );
$container->addShared(\Doctrine\DBAL\Connection::class, function() use($container): \Doctrine\DBAL\Connection{
    return $container->get(\Paranoid\Framework\Dbal\ConnectionFactory::class)->create();
});

$container->add(\Paranoid\Framework\Http\Middleware\RouterDispatch::class)
    ->addArgument(RouterInterface::class)
    ->addArgument($container);


$container->add(\Paranoid\Framework\Authentication\SessionAuthentication::class)
    ->addArguments([
        \App\Repository\UserRepository::class,
        SessionInterface::class,
    ]);

$container->add(\Paranoid\Framework\Http\Middleware\ExtractRouteInfo::class)
    ->addArgument(new \League\Container\Argument\Literal\ArrayArgument($routes));

return $container;
