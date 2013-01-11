<?php 
namespace AgenDAV\CalendarChannels;

/*
 * Copyright 2013 Jorge López Pérez <jorge@adobo.org>
 *
 *  This file is part of AgenDAV.
 *
 *  AgenDAV is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  any later version.
 *
 *  AgenDAV is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with AgenDAV.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace AgenDAV\CalendarObjects;

interface IEvent
{

    public function getHref();

    public function getEtag();

    public function getCalendar();

    public function getVevent();

    public function setHref($href);

    public function setEtag($etag);

    public function setCalendar(\AgenDAV\Data\CalendarInfo $calendar);

    public function setVevent(\Sabre\VObject\Component\VEvent $vevent);

    /**
     * Generates a suitable array for fullcalendar
     * 
     * @access public
     * @throws \UnexpectedValueException If VEVENT is not a valid event
     * @return Array
     */
    public function toArray();
}
