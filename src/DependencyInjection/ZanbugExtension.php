<?php

namespace Zanbug\Symfony\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Zanbug\Symfony\EventListener\ExceptionListener;
use Zanbug\Symfony\Security\UserResolver;

class ZanbugExtension extends Extension
{
    /** Package version, reported in the SDK ping (keep in step with the core's Zanbug::VERSION). */
    const VERSION = '2.0.4';

    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        // NOTE: load() only runs when the container is (re)compiled, not on every
        // request. So we must NOT call Zanbug::init() here — the compiled container
        // is cached and the static SDK client would be lost at runtime. Instead we
        // store the resolved config as a container parameter and let
        // ZanbugBundle::boot() initialize the SDK on every kernel boot / request.
        $container->setParameter('zanbug._config', array(
            'api_key' => isset($config['api_key']) ? $config['api_key'] : '',
            'host' => isset($config['host']) ? $config['host'] : 'https://zanbug.com',
            'environment' => isset($config['environment']) ? $config['environment'] : null,
            'release' => isset($config['release']) ? $config['release'] : null,
            'enabled' => isset($config['enabled']) ? $config['enabled'] : true,
            'capture_requests' => isset($config['capture_requests']) ? $config['capture_requests'] : false,
            'capture_queries' => isset($config['capture_queries']) ? $config['capture_queries'] : true,
            'slow_query_ms' => isset($config['slow_query_ms']) ? $config['slow_query_ms'] : 1000,
            'sample_rate' => isset($config['sample_rate']) ? $config['sample_rate'] : 1.0,
            // Metadata for the one-time install ping (SDK handshake).
            'app_name' => isset($config['app_name']) ? $config['app_name'] : null,
            'framework' => 'symfony',
            'framework_version' => defined('Symfony\Component\HttpKernel\Kernel::VERSION')
                ? \Symfony\Component\HttpKernel\Kernel::VERSION
                : null,
            'sdk' => 'zanbug/symfony',
            'sdk_version' => self::VERSION,
        ));

        // Register the exception listener as a service (EventSubscriberInterface).
        $definition = new Definition(ExceptionListener::class);
        $definition->setPublic(false);
        $definition->addTag('kernel.event_subscriber');
        $container->setDefinition('zanbug.exception_listener', $definition);

        // Logged-in user for every event, no manual setUser(). Public so
        // ZanbugBundle::boot() can fetch it; the token storage is optional
        // (null when symfony/security is not installed).
        $resolver = new Definition(UserResolver::class, array(
            new Reference('security.token_storage', ContainerInterface::NULL_ON_INVALID_REFERENCE),
        ));
        $resolver->setPublic(true);
        $container->setDefinition('zanbug.user_resolver', $resolver);
    }

    public function getAlias(): string
    {
        return 'zanbug';
    }
}
