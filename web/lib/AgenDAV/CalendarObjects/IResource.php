<?php
namespace AgenDAV\CalendarObjects;

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

interface IResource
{

    public function getHref();

    public function getEtag();

    public function getCalendar();

    public function getComponent();

    public function setHref($href);

    public function setEtag($etag);

    public function setCalendar(\AgenDAV\Data\CalendarInfo $calendar);

    public function setComponent($component);

    public function expandEvents(\DateTime $start, \DateTime $end);

    /**
     * Generates a suitable array for fullcalendar. Will only work
     *
     * @access public
     * @throws \UnexpectedValueException If resource is not a valid vevent
     * @return Array
     */
    public function toArray();

    public function toText();
}
