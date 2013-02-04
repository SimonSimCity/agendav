<?php
namespace AgenDAV\CalendarObjects;

use \Sabre\VObject;
use \AgenDAV\Data\CalendarInfo;

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

class VObjectResource implements IResource
{
    private $href;

    private $etag;

    private $calendar;

    private $component;

    public static $interesting_properties = array(
        'DURATION' => 'duration',
        'SUMMARY' => 'title',
        'UID' => 'uid',
        'DESCRIPTION' => 'description',
        'RRULE' => 'rrule',
        'RECURRENCE-ID' => 'recurrence-id',
        'LOCATION' => 'location',
        'TRANSP' => 'transp',
        'CLASS' => 'icalendar_class',
    );

    public function __construct()
    {
        $this->href = null;
        $this->calendar = null;
        $this->etag = null;
        $this->component = null;
    }

    public function getHref()
    {
        return $this->href;
    }

    public function getEtag()
    {
        return $this->etag;
    }

    public function getCalendar()
    {
        return $this->calendar;
    }

    public function getComponent()
    {
        return $this->component;
    }

    public function setHref($href)
    {
        $this->href = $href;
    }

    public function setEtag($etag)
    {
        $this->etag = $etag;
    }

    public function setCalendar(CalendarInfo $calendar)
    {
        $this->calendar = $calendar;
    }

    public function setComponent($component)
    {
        assert($component instanceof VObject\Component);
        $this->component = $component;
    }

    public function expandEvents(\DateTime $start, \DateTime $end)
    {
        // TODO copy RRULE property to each expanded event
        $expanded = clone $this->component;
        $expanded->expand($start, $end);

        $result = array();
        foreach ($expanded->VEVENT as $event) {
            $event->RRULE = $this->component->RRULE;
            $instance = clone $this;
            $instance->setComponent($event);
            $result[] = $instance;
        }

        return $result;
    }

    public function toArray()
    {
        if ($this->component === null) {
            throw new \UnexpectedValueException('Null component');
        } elseif (!($this->component instanceof VObject\Component\VEvent)) {
            throw new \UnexpectedValueException('Only VEVENTs can be serialized');
        }

        $result = array(
            'href' => $this->href,
            'etag' => $this->etag,
        );

        // Start and end dates
        $start = $this->component->DTSTART->getDateTime();
        $end = $this->component->DTEND;

        if ($end === null) {
            // Event is lacking DTEND
            $end = clone $start;

            if ($this->component->DURATION !== null) {
                $end->add(VObject\DateTimeParser::parseDuration($this->component->DURATION));
            } elseif ($this->component->DTSTART->getDateType() == VObject\Property\DateTime::DATE) {
                $end->modify('+1 day');
            }
        } else {
            $end = $end->getDateTime();
        }

        // All day events
        $result['allDay'] = false;
        if ($this->component->DTSTART->getDateType() == VObject\Property\DateTime::DATE) {
            $result['allDay'] = true;
        } elseif ($start->format('Hi') == '0000' && (($end->getTimestamp() - $start->getTimestamp()) % 86400) == 0) {
            $result['allDay'] = true;
        }

        foreach (self::$interesting_properties as $name => $index) {
            $property = $this->component->{$name};

            if ($property === null) {
                continue;
            }

            $result[$index] = $property->value;

            // TODO: make recurrent events editable
            if ($name == 'RRULE') {
                $result['editable'] = false;
            }
        }

        // Reminders TODO

        $result['start'] = $start->format(\DateTime::ISO8601);
        $result['end'] = $end->format(\DateTime::ISO8601);
        return $result;
    }

    public function toText()
    {
        return $this->component->serialize();
    }

}
