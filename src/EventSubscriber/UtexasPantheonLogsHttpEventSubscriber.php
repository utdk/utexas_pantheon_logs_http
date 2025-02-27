<?php

namespace Drupal\utexas_pantheon_logs_http\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * Event subscribed for Logs http.
 */
class UtexasPantheonLogsHttpEventSubscriber implements EventSubscriberInterface {

  /**
   * Initializes UTexas Pantheon Logs http module requirements.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   The event to process.
   */
  public function onRequest(RequestEvent $event) {
    // Kernel requests can have sub-requests. To avoid registering this
    // shutdown function twice, we check if this is the MainRequest.
    // github.austin.utexas.edu/eis1-wcs/utexas_pantheon_logs_http/issues/29.
    if ($event->isMainRequest()) {
      drupal_register_shutdown_function('utexas_pantheon_logs_http_shutdown');
    }
  }

  /**
   * Implements EventSubscriberInterface::getSubscribedEvents().
   *
   * @return array
   *   An array of event listener definitions.
   */
  public static function getSubscribedEvents() {
    // Setting high priority for this subscription in order to execute it soon
    // enough.
    $events[KernelEvents::REQUEST][] = ['onRequest', 1000];

    return $events;
  }

}
