<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed'); 

/*
 * Copyright 2011-2012 Jorge López Pérez <jorge@adobo.org>
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

use AgenDAV\Data\Reminder;
use AgenDAV\DateHelper;
use Sabre\VObject;
use Sabre\VObject\Component\VCalendar;

class Icshelper {
    private $config; // for iCalCreator

    private $tz;

    /**
     * @var MY_Controller
     */
    public $CI;

    private $date_frontend_format_pref;
    private $date_frontend_format;
    private $time_frontend_format_pref;
    private $time_frontend_format;


    function __construct() {

        /** @var MY_Controller $ci */
        $ci =& get_instance();
        $this->CI = $ci;

        // Timezone
        $this->tz = new DateTimeZone(
                $this->CI->config->item('default_timezone'));

        $this->date_frontend_format_pref = $this->CI->config->item('default_date_format');
        $this->time_frontend_format_pref = $this->CI->config->item('default_time_format');
        $this->date_frontend_format = DateHelper::getDateFormatFor('date', $this->date_frontend_format_pref);
        $this->time_frontend_format = DateHelper::gettimeFormatFor('date', $this->time_frontend_format_pref);

        $this->config = array(
                'unique_id' =>
                $this->CI->config->item('icalendar_unique_id'),
                );
    }

    /**
     * Creates a new iCalendar resource
     *
     * Property keys can be lowercase
     *
     * Returns generated guid, FALSE on error. $generated will be filled with
     * new generated resource
     *
     * @param array $properties
     * @param string $new_id
     * @param DateTimeZone $tz
     * @param array $reminders
     * @return VCalendar
     */
    function new_resource($properties, &$new_id, $tz, $reminders =
            array()) {
        $properties = array_change_key_case($properties, CASE_UPPER);

        $vcalendar = new VCalendar();

        $allday = (isset($properties['ALLDAY']) && $properties['ALLDAY'] ==
                'true');

        if ($allday) {
            // Discard timezone
            $tz = new DateTimeZone('UTC');
        }

        $new_id = $this->generate_guid();

        /** @var Sabre\VObject\Component\VEvent $vevent */
        $vevent = $vcalendar->add('VEVENT', array(
            'CREATED' => time(),
            'LAST-MODIFIED' => time(),
            'DTSTAMP' => time(),
            'UID' => $new_id,
            'SEQUENCE' => '0', // RFC5545, 3.8.7.4
            'SUMMARY' => $properties['SUMMARY'],
        ));

        // Rest of properties
        $add_prop = array('DTSTART', 'DTEND', 'DESCRIPTION', 'LOCATION',
                'DURATION', 'RRULE', 'TRANSP', 'CLASS');

        foreach ($add_prop as $p) {
            if (isset($properties[$p]) && !empty($properties[$p])) {
                $params = null;

                // Generate DTSTART/DTEND
                if ($p == 'DTSTART' || $p == 'DTEND') {
                    if ($tz->getName() != 'UTC') {
                        $params = array('TZID' => $tz->getName());
                    }

                    $properties[$p]->setTimeZone($tz);

                    // All day: use parameter VALUE=DATE
                    if ($allday) {
                        $params['VALUE'] = 'DATE';
                    }
                }
                $vevent->add($p, $properties[$p], $params);
            }
        }

        // VALARM components (reminders)
        $this->set_valarms($vevent, $reminders);

        return $vcalendar;
    }


    /**
     * Generates a new GUID
     *
     * Found on phunction PHP framework
     * (http://sourceforge.net/projects/phunction/)
     */
    function generate_guid()
    {
        if (function_exists('com_create_guid') === true)
        {
            return trim(com_create_guid(), '{}');
        }

        return sprintf('%04X%04X-%04X-%04X-%04X-%04X%04X%04X',
                mt_rand(0, 65535), mt_rand(0, 65535), 
                mt_rand(0, 65535), mt_rand(16384, 20479), 
                mt_rand(32768, 49151), mt_rand(0, 65535), 
                mt_rand(0, 65535), mt_rand(0, 65535));
    }

    /**
     * Expands a list of resources to repeated events, depending on
     * recurrence rules and recurrence exceptions/modifications
     *
     * @param string[][]   $resources  Resources returned by GetEvents
     * @param int       $start      Start timestamp
     * @param int       $end        End timestamp
     * @param string        $calendar       Current calendar
     * @return array
     */
    function expand_and_parse_events($resources, $start, $end, $calendar) {
        $result = array();

        // Dates
        $utc = new DateTimeZone('UTC');
        $date_start = new DateTime($start, $utc);
        $date_end = new DateTime($end, $utc);

        foreach ($resources as $r) {
            // Catch the exception somewhere global ...
            $vcalendar = $this->parse_icalendar($r['data']);
            $vcalendar->expand($date_start, $date_end);

            foreach($vcalendar->select("VEVENT") as $event)
                $result[] = $this->parse_vevent_fullcalendar($event, $r['href'], $r['etag'], $calendar);
        }

        return $result;
        
    }

    /**
     * Parses an VEVENT for Fullcalendar
     * @param \Sabre\VObject\Component\VEvent $vevent
     * @param string $href
     * @param string $etag
     * @param string $calendar
     * @return array|bool
     */
    function parse_vevent_fullcalendar($vevent, 
            $href, $etag, $calendar = 'calendario') {

        $this_event = array(
                'href' => $href,
                'calendar' => $calendar,
                'etag' => $etag,
                'disableDragging' => FALSE,
                'disableResizing' => FALSE,
                'ignoreTimezone' => TRUE,
                'timezone' => $this->tz->getName(),
                );

        // Start and end date
        /** @var \Sabre\VObject\Property\ICalendar\DateTime $dtstart */
        $dtstart = $vevent->{'DTSTART'};

        // We have for sure DTSTART
        $start = $dtstart->getDateTime();

        // Do we have DTEND?
        if (isset($vevent->{'DTEND'})) {
            $end = $vevent->{'DTEND'}->getDateTime();
        } else {
            // Calculate dtend if not present
            if (isset($vevent->{'DURATION'})) {
                /** @var \Sabre\VObject\Property\ICalendar\Duration $duration */
                $duration = $vevent->{'DURATION'};
                $end = new DateTime();
                $end->add($duration->getDateInterval());
            } else {
                // RFC 2445, p52
                if ($dtstart->hasTime()) {
                    $end = clone $start;
                } else {
                    $end = clone $start;
                    $end->add(new DateInterval('P1D'));
                }
            }
        }

        // Is this a recurrent event?
        if (isset($vevent->{'x-current-dtstart'})) {
            $current_dtstart = $vevent->{'x-current-dtstart'}->getValue();
            // Is this a multiday event? In that case, ignore this event

            // Hack to avoid getProperty() ignore next getProperty() on 
            // RRULE.
            if (!isset($vevent->{'rrule'})) {
                return FALSE;
            }

            $this_event['expanded'] = TRUE;

            // Format depends on DTSTART
            if (!$dtstart->hasTime()) {
                $current_dtstart .= ' 00:00:00';
            }

            // Keep a copy
            $orig_start = clone $start;

            $start = $this->CI->dates->x_current2datetime($current_dtstart, $this->tz);
            unset($this_event['end']);

            if (isset($vevent->{'x-current-dtend'})) {
                $current_dtend = $vevent->{'x-current-dtend'}->getValue();
                if (!$dtstart->hasTime()) {
                    $current_dtend .= ' 00:00:00';
                }

                $orig_end = clone $end;
                $end =
                    $this->CI->dates->x_current2datetime($current_dtend,
                            $this->tz);

            }
        }


        $interesting_props = array(
                'summary', 'uid', 'description', 'rrule',
                'duration', 'location', 'class', 'recurrence-id',
                'transp',
                );

        foreach ($interesting_props as $p) {
            // TODO: more properties
            // TODO multiple ocurrences of the same property?
            // TODO current-dtstart
            $prop = $vevent->{$p};

            if (empty($prop)) {
                continue;
            }

            $val = $prop->getValue();
            switch ($p) {
                case 'description':
                    $description = $val;
                    $this_event['description'] = 
                        preg_replace('/\\\n|\\\r/', "\n", $description);

                    // Format
                    $this_event['formatted_description'] =
                        preg_replace('/\\\n|\\\r/', '<br />', $description);
                    break;
                case 'rrule':
                    // TODO: Implement ...
                    throw new Exception("Not implemented");
                    /*$this_event['recurrence_components'] = $val;
                    $new_val = trim($vevent->_format_recur('RRULE',
                                array($prop)));
                    $this_event['rrule'] = $new_val;

                    $explanation =
                        $this->CI->recurrence->rrule_explain($val);
                    if ($explanation !== FALSE) {
                        $this_event['rrule_explained'] = $explanation;
                    } else {
                        $this_event['unparseable_rrule'] = TRUE;
                    }
                    // TODO make it editable when able to parse it
                    $this_event['editable'] = FALSE;*/
                    break;
                case 'class':
                    $this_event['icalendar_class'] = $val;
                    break;
                case 'summary':
                    $this_event['title'] = $val;
                    break;
                case 'recurrence-id':
                    // TODO parse a little bit
                    $this_event['recurrence_id'] = $val;
                    break;
                case 'duration':
                    // TODO: Don't know how to handle that ...
                    #$val = iCalUtilityFunctions::_format_duration($val);
                case 'uid':
                case 'location':
                case 'transp':
                    $this_event[$p] = $val;
                    break;
                default:
                    log_message('ERROR', 
                            'Attempt to parse iCalendar property ' . $p 
                            . ' on VEVENT which is not developed '
                            .'yet');
                    break;
            }
        }


        // Internal fullCalendar id
        $this_event['id'] = $calendar . $this_event['uid'];

        // Is this an all day event?
        $this_event['allDay'] = FALSE;

        if (!$dtstart->hasTime()) {
            $this_event['allDay'] = TRUE;
        } else if (($end->getTimestamp() - $start->getTimestamp())%86400 == 0) {
            if ($start->format('Hi') == '0000') {
                $this_event['allDay'] = TRUE;
            }

            // Check using UTC and local time
            if ($start->getTimeZone()->getName() == 'UTC') {
                $test_start = clone $start;
                $test_start->setTimeZone($this->tz);
                if ($test_start->format('Hi') == '0000') {
                    $this_event['allDay'] = TRUE;
                }
            }
        }

        if ($this_event['allDay'] === TRUE) {
            // Fool fullcalendar (dates are inclusive). 
            // For expanded events have special care, 
            // iCalcreator expands them using start_day=end_day, which
            // confuses fullCalendar

            $start->setTime(0, 0, 0);
            $end->setTime(0, 0, 0);

            $end->sub(new DateInterval('P1D'))->add(new
                    DateInterval('PT1H'));

            if (isset($this_event['expanded'])) {
                $orig_start->setTime(0, 0, 0);
                $orig_end->setTime(0, 0, 0);

                $orig_end->sub(new DateInterval('P1D'))->add(new
                        DateInterval('PT1H'));
            }

            $this_event['orig_allday'] = TRUE;
        } else {
            $this_event['orig_allday'] = FALSE;
        }


        // To be used with strftime()
        $ts_start = $start->getTimestamp();
        $ts_end = $end->getTimestamp();

        // Needed for some conversions (Fullcalendar timestamp and am/pm
        // indicator)
        if (!isset($this_event['allDay']) 
                || $this_event['allDay']  !== TRUE) {
            $start->setTimeZone($this->tz);
            $end->setTimeZone($this->tz);
        }

        // Expanded events
        if (isset($orig_start) && isset($orig_end)) {
            $orig_start->setTimeZone($this->tz);
            $orig_end->setTimeZone($this->tz);
            $this_event['orig_start'] = $orig_start->format(DateTime::ISO8601);
            $this_event['orig_end'] = $orig_end->format(DateTime::ISO8601);
        }

        // Readable dates for start and end

        // Keep all day events as they are (UTC)
        $system_tz = date_default_timezone_get();
        if (!isset($this_event['allDay']) 
                || $this_event['allDay']  !== TRUE) {
            date_default_timezone_set($this->tz->getName());
        }

        // Load date format
        $date_format = $this->CI->i18n->_('formats', 'full_date_strftime');

        $this_event['formatted_start'] = strftime($date_format, $ts_start); 

        if (isset($this_event['allDay']) && $this_event['allDay'] == TRUE) {
            // Next day?
            if ($start->format('Ymd') == $end->format('Ymd')) {
                $this_event['formatted_end'] =
                    '('.$this->CI->i18n->_('labels', 'allday').')';
            } else {
                $this_event['formatted_end'] = strftime($date_format, $ts_end); 
            }
        } else {
            // Are they in the same day?
            $this_event['formatted_start'] .= ' ' 
                . $this->CI->dates->strftime_time($ts_start, $start);
            if ($start->format('Ymd') == $end->format('Ymd')) {
                $this_event['formatted_end'] =
                    $this->CI->dates->strftime_time($ts_end, $end);
            } else {
                $this_event['formatted_end'] =
                    strftime($date_format, $ts_end) . ' ' .
                    $this->CI->dates->strftime_time($ts_end, $end);
            }
        }

        // Restore TZ
        date_default_timezone_set($system_tz);

        // Empty title?
        if (!isset($this_event['title'])) {
            $this_event['title'] = $this->CI->i18n->_('labels', 'untitled');
        }

        $this_event['start'] = $start->format(DateTime::ISO8601);
        $this_event['end'] = $end->format(DateTime::ISO8601);

        // Reminders for this event
        $this_event['visible_reminders'] = array();
        $this_event['reminders'] = array();

        $valarms = $this->parse_valarms($vevent);

        foreach ($valarms as $order => $reminder) {
            $this_event['visible_reminders'][] = $order;
            $this_event['reminders'][] = $reminder;
        }

        return $this_event;
    }

    /**
     * Parses an iCalendar resource to a VObject
     * @param $data string
     * @return VCalendar
     * @throws Sabre\VObject\ParseException
     */
    public function parse_icalendar($data) {
        return VObject\Reader::read($data);
    }


    /**
     * Collects all timezones (VTIMEZONE) present in a resource
     *
     * Returns an associative array with 'tzid' => DateTimeZone('real tz
     * name')
     *
     * @param $icalendar VCalendar
     * @return mixed
     * @deprecated
     */
    function get_timezones($icalendar) {
        $result = array();
        while ($vt = $icalendar->getComponent('vtimezone')) {
            $tzid = $vt->getProperty('TZID');
            // Contains (usually) the time zone name
            $tzval = $vt->getProperty('X-LIC-LOCATION');
            
            if ($tzval === FALSE || empty($tzval)) {
                // Try to extract it from TZID name
                $tzval = $tzid;
            } else {
                $tzval = $tzval[1];
            }

            // Do we have tzval?
            if ($tzval !== FALSE && !empty($tzval)) {
                $result[$tzid] = $this->CI->timezonemanager->getTz($tzval);
            }
        }

        return $result;
    }

    /**
     * Finds a component within a resource, and returns its index in the
     * components array.
     *
     * Useful for replacing existing components by using GetComponents() to
     * save resources directly
     *
     * @param   calendarComponent   $resource   Full iCalComponent VCALENDAR
     * @param   string  $type   VEVENT, VTIMEZONE, etc
     * @param   array $conditions  Associative array. Possible keys:
     *                       - RECURRENCE-ID
     *                       - ?
     * @param   calendarComponent $comp   The found object
     * @return boolean
     * @deprecated
     */
    function find_component_position($resource, $type, 
            $conditions = array(), &$comp) {

        // Position
        $i = 1;
        $found = FALSE;
        $comp = null;

        while ($found === FALSE && ($c = $resource->getComponent($type))) {
            // Check conditions
            if (isset($conditions['recurrence-id'])) {
                $recurr_id = $c->getProperty('recurrence-id');
                if ($recurr_id !== FALSE && $recurr_id ==
                        $conditions['recurrence-id']) {
                    $found = $i;
                }
            } else {
                $found = $i;
            }

            if ($found !== FALSE) {
                $comp = $c;
            }
        }

        return $found;
    }

    /**
     * Replaces a component in the n-th position
     * @deprecated
     */
    function replace_component($resource, $type, $n, $new) {
        $resource->setComponent($new, $type, $n);
        return $resource;
    }


    /**
     * Applies a LAST-MODIFIED change on the iCalendar component
     * (VEVENT, etc)
     * @param Sabre\VObject\Component $component
     * @return void
     */
    function set_last_modified($component) {
        $component->{'last-modified'} = new DateTime();

        // SEQUENCE
        if (isset($component->{'SEQUENCE'})) {
            $seq = intval($component->{'SEQUENCE'});
            $seq++;
            $component->{'SEQUENCE'} = $seq;
        }
    }

    /**
     * Gets DTSTART/other property timezone from a component
     * @deprecated
     */
    function detect_tz($component, $tzs, $prop = 'dtstart') {
        $dtstart = $component->getProperty($prop, FALSE, TRUE);
        $val = $dtstart['value'];
        $params = $dtstart['params'];
        $has_z = isset($val['tz']) ? ($val['tz']=='Z') : FALSE;
        $value = $this->paramvalue($params, 'value');
        $used_tz = null;
        if ($has_z || $value == 'DATE') {
            $used_tz = $this->CI->timezonemanager->getTz('UTC');
        } else {
            $tzid = $this->paramvalue($params, 'tzid');;

            if ($tzid !== FALSE && isset($tzs[$tzid])) {
                $used_tz = $tzs[$tzid];
            } else {
                // Not UTC but no TZID/invalid TZID?!
                $used_tz = $this->CI->timezonemanager->getTz(
                        $this->CI->config->item('default_timezone'));
            }
        }

        return $used_tz;
    }


    /**
     * Sets a component DTSTART value
     * 
     * @param Sabre\VObject\Component $vcomponent
     * @param DateTimeZone $tz      Used TZ
     * @param DateTime $new_start
     * @param string $increment
     * @param string $force_new_value_type
     * @param string $force_new_tzid
     * @return void
     * @deprecated
     */
    function make_start($vcomponent, $tz,
            $new_start = null,
            $increment = null,
            $force_new_value_type = null,
            $force_new_tzid = null) {

        $value = null;
        $format = null;

        $info = $this->extract_date($vcomponent, 'DTSTART', $tz);
        // No current DTSTART?
        if (is_null($info)) {
            $params = array('VALUE' => (is_null($force_new_value_type) ?
                        'DATE-TIME' : $force_new_value_type));
            $value = new DateTime('now', $tz);
        } else {
            $params = $info['property']['params'];
            if (!is_null($force_new_value_type)) {
                $params['VALUE'] = $force_new_value_type;
            } elseif (!isset($params['VALUE'])) {
                $params['VALUE'] = 'DATE-TIME';
            }

            $value = $this->CI->dates->idt2datetime($info['property']['value'],
                    $tz);
        }

        // DATE values can't have TZID
        if ($params['VALUE'] == 'DATE') {
            unset($params['TZID']);
        } else if (!is_null($force_new_tzid)) {
            $params['TZID'] = $force_new_tzid;
        }

        $format = $this->CI->dates->format_for($params['VALUE'], $tz);

        // Use current DTSTART
        if (!is_null($new_start)) {
            $value = $new_start;
        }

        // Increment
        if (!is_null($increment)) {
            $value->add($this->CI->dates->duration2di($increment));
        }

        $vcomponent->setProperty('dtstart', $this->CI->dates->datetime2idt(
                    $value, $tz, $format), $params);

        return $vcomponent;
    }

    /**
     * Sets a component end value
     *
     * @param calendarComponent $component
     * @param DateTimeZone $tz      Used TZ
     * @param DateTime $new_end
     * @param string $increment
     * @param string $force_new_value_type
     * @param String $force_new_tzid
     * @return calendarComponent
     * @deprecated
     */
    function make_end($component, $tz,
            $new_end = null,
            $increment = null,
            $force_new_value_type = null,
            $force_new_tzid = null) {

        $value = null;
        $format = null;
        $params = array();

        $dtend_info = $component->{'DTEND'};

        if (empty($dtend_info)) {
            // No DTEND in event
            if (empty($new_end)) {
                /** @var \Sabre\VObject\Property\ICalendar\Duration $duration */
                $duration = $component->{'DURATION'};

                if (empty($duration)) {
                    // Something is wrong . No DTEND nor DURATION
                    // Return the component as is
                    log_message('ERROR',
                            'Event with uid=' . $component->{'UID'}
                            .' has neither DTEND nor DURATION properties');
                    return $component;
                }

                $value = new DateTime();
                $value->add($duration->getDateInterval());
            }

            // Get current DTSTART params
            $dtstart_info = $this->extract_date($component, 'DTSTART', $tz);

            if (is_null($dtstart_info)) {
                // Neither DTSTART nor DTEND!?
                $params = array('VALUE' => 'DATE-TIME');
            } else {
                $params = $dtstart_info['property']['params'];
            }

            // We prefer DTEND to DURATION
            $component->deleteProperty('duration');
        } else {
            $params = $dtend_info['property']['params'];
            $value = $this->CI->dates->idt2datetime($dtend_info['property']['value'],
                    $tz);
        }

        // VALUE parameter
        if (!is_null($force_new_value_type)) {
            $params['VALUE'] = $force_new_value_type;
        } elseif (!isset($params['VALUE'])) {
            $params['VALUE'] = 'DATE-TIME';
        }


        // Use retrieved DTEND (or calculated)
        if (!is_null($new_end)) {
            $value = $new_end;
        }

        // Increment
        if (!is_null($increment)) {
            $value->add($this->CI->dates->duration2di($increment));
        }

        // DATE values can't have TZID
        if ($params['VALUE'] == 'DATE') {
            unset($params['TZID']);
        } else if (!is_null($force_new_tzid)) {
            $params['TZID'] = $force_new_tzid;
        } 

        $format = $this->CI->dates->format_for($params['VALUE'], $tz);

        // Save new value
        $component->setProperty('dtend',
                $this->CI->dates->datetime2idt($value, $tz, $format),
                $params);

        return $component;
    }

    /**
     * Make easy to parse a DTSTART/DTEND
     * @deprecated
     */
    function extract_date($component, $name = 'DTSTART', $tz) {
        $p = $component->getProperty($name, FALSE, TRUE);
        if ($p === FALSE) {
            return null;
        } else {
            $val = $p['value'];
            $params = $p['params'];
        }

        $obj = $this->CI->dates->idt2datetime(
                $val,
                $tz);

        $value_parameter = $this->paramvalue($params, 'value', 'DATE-TIME');

        return array(
                'property' => $p, // original value ...
                'value' => $value_parameter, // the time as text
                'result' => $obj, // DateTime
                );
    }

    /**
     * Changes every property passed as an associative array (key will be
     * uppercased) on given component. DTSTART, DTEND and
     * DURATION are ignored, use make_start and make_end instead
     *
     * @param calendarComponent $component
     * @param array $properties
     * @return calendarComponent
     * @deprecated
     */
    function change_properties($component, $properties) {
        $properties = array_change_key_case($properties, CASE_UPPER);

        foreach ($properties as $p => $v) {
            if ($p == 'DTSTART' || $p == 'DTEND' || $p == 'DURATION') {
                continue;
            }
            // TODO: multivalued properties?

            // TRANSP
            if ($p == 'TRANSP') {
                if ($v != 'OPAQUE' && $v != 'TRANSPARENT') {
                    log_message('ERROR', 'Invalid TRANSP value ('.$v.').  Ignoring.');
                    continue;
                }
            }

            $component->deleteProperty($p);
            if (!empty($v)) {
                $component->setProperty($p, $v);
            }
        }

        return $component;
    }


    /**
     * Changes an event to have all its components with a new timezone
     *
     * Affected properties: DTSTART, DTEND, DUE, EXDATE, RDATE
     *
     * Only changed if VALUE is DATE-TIME or TIME
     * Information extracted from RFC 2445, 4.2.19
     * @deprecated
     */
    function change_tz($component, $old_tz, $new_tzid, $new_tz) {
        $change = array('DTSTART', 'DTEND', 'DUE', 'EXDATE', 'RDATE');
        foreach ($change as $c) {
            $new_prop = array();
            $prop = $component->GetProperties($c);
            foreach ($prop as $p) {
                $valuep = $p->GetParameterValue('VALUE');
                if (!is_null($valuep) && $valuep == 'DATE') {
                    $new_prop[] = $p;
                    continue;
                }
                $tzid = $p->GetParameterValue('TZID');
                if (!is_null($tzid) && $tzid == $new_tzid) {
                    // Keep untouched
                    $new_prop[] = $p;
                    continue;
                }

                // No TZ or different TZ
                $val = $p->Value();
                $multiple = preg_split('/,/', $val);
                foreach ($multiple as $v) {
                    $new_p = clone $p;
                    $current = $this->CI->dates->idt2datetime($v,
                            $this->CI->dates->format_for($valuep, $old_tz),
                            $old_tz);
                    $new_p->SetParameterValue('TZID', $new_tzid);
                    $new_p->Value($this->CI->dates->datetime2idt($current,
                                $new_tz));
                    $new_prop[] = $new_p;
                }
            } // end foreach $prop

            // Set new properties
            $component->SetProperties($new_prop, $c);
        }

        return $component;
    }


    /**
     * Make it easy to access parameters
     * @deprecated
     */
    function paramvalue($params, $name, $default_val = FALSE) {
        $name = strtoupper($name);
        return (isset($params[$name]) ? $params[$name] : $default_val);
    }

    /**
     * Add a VTIMEZONE using the specified TZID
     * If VTIMEZONE was already added, do nothing
     *
     * @param calendarComponent $resource
     * @param string $tzid
     * @param array $timezones
     * @return  string Used TZID, even when it was not added
     * @deprecated
     */
    function add_vtimezone(&$resource, $tzid, $timezones = array()) {
        if ($tzid != 'UTC' && !isset($timezones[$tzid])) {
            $res = iCalUtilityFunctions::createTimezone($resource,
                    $tzid, array( 'X-LIC-LOCATION' => $tzid));
            if ($res === FALSE) {
                log_message('ERROR', 
                        "Couldn't create vtimezone with tzid=" . $tzid
                        .' Defaulting to UTC');
                $tzid = 'UTC';
            }
        }

        return $tzid;
    }

    /**
     * Parses a VEVENT resource VALARM definitions
     *
     * Returns an associative array ('n1#' => new Reminder, 'n2#' => new
     * Reminder...), where 'n#' is the order where this VALARM was found
     *
     * @param \Sabre\VObject\Component\VEvent $vevent
     * @return array
     */
    function parse_valarms($vevent) {
        $parsed_reminders = array();

        $order = 0;
        $valarms = $vevent->select('VALARM');
        foreach ($valarms as $valarm) {
            /** @var \Sabre\VObject\Component\VEvent $valarm */

            $order++;
            // TODO parse more actions
            switch ($valarm->{'action'}) {
                case 'DISPLAY':
                    if (isset($trigger['BEFORE'])) {
                        // Related to event start/end
                        $reminder = Reminder::createFrom($valarm->{'TRIGGER'});
                    } else {
                        /** @var \DateTime $datetime */
                        $datetime = $valarm->{'TRIGGER'};
                        // Use default timezone
                        $datetime->setTimezone($this->tz);

                        $reminder = Reminder::createFrom($datetime);
                        $reminder->tdate =
                            $datetime->format($this->date_frontend_format);
                        $reminder->ttime =
                            $datetime->format($this->time_frontend_format);
                    }

                    if (isset($reminder)) {
                        $reminder->order = $order;
                        $parsed_reminders[$order] = $reminder;
                    }
            }
        }

        return $parsed_reminders;
    }

    /**
     * Adds or replaces VALARM components (reminders) for a given VEVENT
     * resource. Removes VALARMs that were deleted by user
     *
     * @param \Sabre\VObject\Component $resource
     * @param Reminder[] $reminders
     * @param array $old_visible_reminders
     * @throws Exception
     * @return void
     */
    function set_valarms($resource, $reminders, $old_visible_reminders = array()) {
        foreach ($reminders as $r) {
            $valarm = $resource->add('VALARM');
            $valarm = $r->toVAlarmObject($valarm);

            if ($r->order !== FALSE) {
                $resource = $this->replace_component($resource,
                        'valarm', $r->order, $valarm);
                unset($old_visible_reminders[$r->order]);
            } else {
                $resource->add($valarm);
            }
        }

        // Any VALARMs left that was not present?
        if (!empty($old_visible_reminders)) {
            $remove_valarms = array_keys($old_visible_reminders);
            foreach ($remove_valarms as $n) {
                // TODO implement ...
                throw new Exception("Not implemented");
                //$resource->remove($n);
            }
        }
    }


}

