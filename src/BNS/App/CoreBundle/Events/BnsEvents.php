<?php
namespace BNS\App\CoreBundle\Events;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
final class BnsEvents
{
    const CLEAR_CACHE = 'bns_api.clear_cache';
    const APPLICATION_UNINSTALL = 'bns_event.application_uninstall';
    const ACTIVITY_UNINSTALL = 'bns_event.activity_uninstall';
    const THUMB_REFRESH = 'bns_event.thumbnail_refresh';
}
