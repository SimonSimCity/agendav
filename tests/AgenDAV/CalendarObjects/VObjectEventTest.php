<?php
namespace AgenDAV\CalendarObjects;

use AgenDAV\Data\CalendarInfo;

class VObjectEventTest extends \PHPUnit_Framework_TestCase
{

    protected $resources;

    protected $expected;

    public function setUp()
    {
        $this->expected = array(
            'basic_1.ics' => array(
                'href' => null,
                'etag' => null,
                'allDay' => false,
                'title' => 'One more test',
                'description' => 'Test',
                'uid' => 'BDC3A3B5-8F42-467A-95A5-68AA001EA285',
                'location' => 'Some place',
                'transp' => 'OPAQUE',
                'icalendar_class' => 'PUBLIC',
                'start' => '2013-01-09T21:00:00+0100',
                'end' => '2013-01-09T22:00:00+0100',
            ),
            'duration.ics' => array(
                'href' => null,
                'etag' => null,
                'allDay' => false,
                'duration' => 'PT2H',
                'title' => 'Event with no DTEND and defined DURATION',
                'uid' => 'BDC3A3B5-8F42-467A-95A5-68AA001EA285',
                'start' => '2013-01-09T21:00:00+0100',
                'end' => '2013-01-09T23:00:00+0100',
            ),
            'allday_no_dtend.ics' => array(
                'href' => null,
                'etag' => null,
                'allDay' => true,
                'title' => 'All day event with no DTEND',
                'uid' => 'BDC3A3B5-8F42-467A-95A5-68AA001EA285',
                'start' => '2013-01-09T00:00:00+0000',
                'end' => '2013-01-10T00:00:00+0000',
            ),
            'basic_allday_1.ics' => array(
                'href' => null,
                'etag' => null,
                'allDay' => true,
                'uid' => '35e58430-8726-4dc0-8693-8d2dba94b308',
                'transp' => 'TRANSPARENT',
                'title' => 'Basic all day event',
                'start' => '2013-01-09T00:00:00+0000',
                'end' => '2013-01-10T00:00:00+0000',
            ),
            'basic_allday_2.ics' => array(
                'href' => null,
                'etag' => null,
                'allDay' => true,
                'title' => 'All day',
                'start' => '2013-01-09T00:00:00+0100',
                'end' => '2013-01-10T00:00:00+0100',
            ),
            'line_break_description.ics' => array(
                'href' => null,
                'etag' => null,
                'allDay' => false,
                'title' => 'Line breaks',
                'uid' => 'E7B7C221-7F86-4EB2-BF71-4537C0A70FE2',
                'description' => "This\nis\na\ntest",
                'transp' => 'OPAQUE',
                'icalendar_class' => 'PUBLIC',
                'start' => '2013-01-09T21:00:00+0100',
                'end' => '2013-01-09T22:00:00+0100',
            ),
        );

        foreach (array_keys($this->expected) as $f) {
            $this->resources[$f] = \Sabre\VObject\Reader::read(file_get_contents(dirname(__FILE__) . '/' . $f));
        }

    }

    public function testCreate()
    {
        $event = new VObjectEvent();
        $this->assertNull($event->getHref());
        $this->assertNull($event->getEtag());
        $this->assertNull($event->getCalendar());
        $this->assertNull($event->getVevent());
    }

    public function testGetSet()
    {
        $event = new VObjectEvent();
        $event->setHref('/url');
        $this->assertEquals($event->getHref(), '/url');
        $event->setEtag('etag');
        $this->assertEquals($event->getEtag(), 'etag');
        $event->setCalendar(new CalendarInfo('http://dummy.com/'));
        $this->assertEquals($event->getCalendar()->url, 'http://dummy.com/');

        $vevent = \Sabre\VObject\Component::create('VEVENT');
        $vevent->SUMMARY = 'TEST';

        $event->setVevent($vevent);
        $this->assertEquals($event->getVevent()->SUMMARY, 'TEST');
    }

    public function testToArray()
    {
        foreach ($this->expected as $f => $expected_value) {
            $event = new VObjectEvent();
            $event->setVevent($this->resources[$f]->VEVENT);
            $res = $event->toArray();
            $this->assertEquals($res, $expected_value, $f . ' parse result wasn\'t the expected one');
        }
    }

}
