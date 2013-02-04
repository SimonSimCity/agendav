<?php 
namespace AgenDAV\CalendarObjects;

use \Sabre\VObject;

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

class VObjectEventParser implements IEventParser
{

    /**
     * Expands a CalDAV resource into events
     *
     * @param IEvent $resource 
     * @param \DateTime $start 
     * @param \DateTime $end 
     * @access public
     * @return array Array of IEvent objects, containing expanded events
     */
    public function expand(IEvent $resource, \DateTime $start, \DateTime $end);

}
