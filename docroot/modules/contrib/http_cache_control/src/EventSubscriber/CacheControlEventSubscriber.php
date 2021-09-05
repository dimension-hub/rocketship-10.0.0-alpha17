<?php

namespace Drupal\http_cache_control\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Drupal\Core\Site\Settings;
use Drupal\Core\Config\ConfigFactory;

/**
 * Subscriber for adding http cache control headers.
 */
class CacheControlEventSubscriber implements EventSubscriberInterface {

    /**
     * Configuration Factory.
     *
     * @var \Drupal\Core\Config\ConfigFactory
     */
    protected $configFactory;

    /**
     * Constructor.
     */
    public function __construct(ConfigFactory $configFactory) {
        $this->configFactory = $configFactory;
    }

    /**
     * Set http cache control headers.
     */
    public function setHeaderCacheControl(FilterResponseEvent $event) {
        $config = $this->configFactory->get('http_cache_control.settings');
        $response = $event->getResponse();

        if ($variation = $config->get('cache.http.vary')) {
            $vary = $response->getVary();

            foreach (array_map('trim', explode(',', $variation)) as $header) {
                $vary[] = $header;
            }
            if (!Settings::get('omit_vary_cookie')) {
                $vary[] = 'Cookie';
            }

            $response->setVary(implode(',', $vary));
        }

        if (!$response->isCacheable()) {
            return;
        }

        $ttl = $response->getMaxAge();

        switch ($response->getStatusCode()) {
            case 404:
                $ttl = $config->get('cache.http.404_max_age', $ttl);
                break;

            case 302:
                $ttl = $config->get('cache.http.302_max_age', $ttl);
                break;

            case 301:
                $ttl = $config->get('cache.http.301_max_age', $ttl);
                break;
        }

        if ($ttl != $response->getMaxAge()) {
            $response->setClientTtl($ttl);
            $response->setSharedMaxAge($ttl);
        } elseif ($ttl = $config->get('cache.http.s_maxage')) {
            $response->setSharedMaxAge($ttl);
        }

        if ($response->getStatusCode() >= 500) {
            $response->setSharedMaxAge($config->get('cache.http.5xx_max_age'));
        }
        // All stale revalidation directives to be added to non-error responses.
        elseif ($response->getStatusCode() < 400) {
            // Add stale-if-error directive.
            if ($seconds = $config->get('cache.http.stale_if_error')) {
                $response->headers->addCacheControlDirective('stale-if-error', $seconds);
            }
            // Add stale-while-revalidate directive.
            if ($seconds = $config->get('cache.http.stale_while_revalidate')) {
                $response->headers->addCacheControlDirective('stale-while-revalidate', $seconds);
            }

            // Surrogate Control
            $maxage = $config->get('cache.surrogate.maxage');
            $nostore = $config->get('cache.surrogate.nostore');

            if (!empty($maxage) || $nostore) {
                $value = $nostore ? ['no-store'] : [];
                if (!empty($maxage)) {
                    $value[] = 'max-age=' . $maxage;
                }
                $response->headers->set('Surrogate-Control', implode(', ', $value));
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents() {
        // Response: set header content for security policy.
        $events[KernelEvents::RESPONSE][] = ['setHeaderCacheControl', -10];
        return $events;
    }

}
