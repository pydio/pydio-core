<?php
/*
 * Copyright 2007-2017 Charles du Jeu - Abstrium SAS <team (at) pyd.io>
 * This file is part of Pydio.
 *
 * Pydio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Pydio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Pydio.  If not, see <http://www.gnu.org/licenses/>.
 *
 * The latest code can be found at <https://pydio.com>.
 */

namespace Pydio\Access\Core\Stream\Listener;

use GuzzleHttp\Event\SubscriberInterface;
use GuzzleHttp\Command\Event\PreparedEvent;

/**
 * Listener used to change the way the paths are given (no urlencode for the slash)
 */
class PathSubscriber implements SubscriberInterface
{

    /**
     * Get the list of events this subscriber is being triggered on
     *
     * @return array Events
     */
    public function getEvents()
    {
        return [
            'prepared'   => ['onBefore']
        ];
    }

    /**
     * Handle the before trigger
     *
     * @param PreparedEvent event
     */
    public function onBefore(PreparedEvent $e)
    {
        $request = $e->getRequest();
        if (empty($request)) {
            return;
        }

        $path = $request->getPath();
        if (empty($path)) {
            return;
        }

        $path = rawurldecode($path);
        $path = str_replace('//', '/', $path);

        $request->setPath($path);
    }
}
