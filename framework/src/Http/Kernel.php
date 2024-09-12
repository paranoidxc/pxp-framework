<?php

namespace Paranoid\Framework\Http;

use Doctrine\DBAL\Connection;
use Exception;
use Paranoid\Framework\EventDispatcher\EventDispatcher;
use Paranoid\Framework\Http\Event\ResponseEvent;
use Paranoid\Framework\Http\Middleware\RequestHandlerInterface;
use Paranoid\Framework\Routing\Router;
use Paranoid\Framework\Routing\RouterInterface;
use Psr\Container\ContainerInterface;

class Kernel
{

    private string $appEnv;

    //public function __construct(private Router $router)
    public function __construct(
        private ContainerInterface $container,
        private RequestHandlerInterface $requestHandler,
        private EventDispatcher $eventDispatcher,
    )
    {
        $this->appEnv = $container->get("APP_ENV");
    }

    public function handle(Request $request): Response
    {
        try {
            //throw new \Exception("Kernel ERR");
            //dd($this->container->get(Connection::class));

            //[$routeHandler, $vars] = $this->router->dispatcher($request, $this->container);
            //$response = call_user_func_array($routeHandler, $vars);

            $response = $this->requestHandler->handle($request);
        } catch (\Exception $exception) {
            $response = $this->createExceptionResponse($exception);
        }

        $this->eventDispatcher->dispatch(new ResponseEvent($request, $response));

        return $response;
    }

    /**
     * @throws \Exception $exception
     */
    private function createExceptionResponse(\Exception $exception): Response
    {
        if (in_array($this->appEnv, ['dev', 'test'])) {
            throw $exception;
        }

        if ($exception instanceof HttpException) {
            return new Response($exception->getMessage(), $exception->getStatusCode());
        }

        return new Response("Server error", Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    public function terminate(Request $request, Response $response)
    {
        $request->getSession()?->clearFlash();
        //$request->getSession()?->remove('auth_id');
    }
}
