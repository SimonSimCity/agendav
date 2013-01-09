<?php
namespace AgenDAV\CalendarObjects;

class VObjectEventTest extends \PHPUnit_Framework_TestCase
{

    protected $resources;

    protected $expected;

    public function setUp()
    {
        $this->expected = array(
            'basic_1.ics' => array(
                'href' => '/url',
                'etag' => 'etag',
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
                'href' => '/url',
                'etag' => 'etag',
                'allDay' => false,
                'duration' => 'PT2H',
                'title' => 'Event with no DTEND and defined DURATION',
                'uid' => 'BDC3A3B5-8F42-467A-95A5-68AA001EA285',
                'start' => '2013-01-09T21:00:00+0100',
                'end' => '2013-01-09T23:00:00+0100',
            ),
            'allday_no_dtend.ics' => array(
                'href' => '/url',
                'etag' => 'etag',
                'allDay' => true,
                'title' => 'All day event with no DTEND',
                'uid' => 'BDC3A3B5-8F42-467A-95A5-68AA001EA285',
                'start' => '2013-01-09T00:00:00+0000',
                'end' => '2013-01-10T00:00:00+0000',
            ),
            'basic_allday_1.ics' => array(
                'href' => '/url',
                'etag' => 'etag',
                'allDay' => true,
                'uid' => '35e58430-8726-4dc0-8693-8d2dba94b308',
                'transp' => 'TRANSPARENT',
                'title' => 'Basic all day event',
                'start' => '2013-01-09T00:00:00+0000',
                'end' => '2013-01-10T00:00:00+0000',
            ),
            'basic_allday_2.ics' => array(
                'href' => '/url',
                'etag' => 'etag',
                'allDay' => true,
                'title' => 'All day',
                'start' => '2013-01-09T00:00:00+0100',
                'end' => '2013-01-10T00:00:00+0100',
            ),
            'line_break_description.ics' => array(
                'href' => '/url',
                'etag' => 'etag',
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
        $this->assertNull($event->getVEVENT());

        $vevent = \Sabre\VObject\Component::create('VEVENT');
        $dtstart = \Sabre\VObject\Property::create('DTSTART');
        $dtstart->setDateTime(new \DateTime(), \Sabre\VObject\Property\DateTime::UTC);
        $vevent->DTSTART = $dtstart;
        $vevent->SUMMARY = 'TEST';

        $event2 = new VObjectEvent($vevent, '/url', 'etag');
        $this->assertEquals($event2->getHref(), '/url');
        $this->assertEquals($event2->getEtag(), 'etag');
        $this->assertEquals($event2->getVEVENT()->SUMMARY, 'TEST');
    }

    public function testGetSet()
    {
        $event = new VObjectEvent();
        $event->setHref('/url');
        $this->assertEquals($event->getHref(), '/url');
        $event->setEtag('etag');
        $this->assertEquals($event->getEtag(), 'etag');

        $vevent = \Sabre\VObject\Component::create('VEVENT');
        $vevent->SUMMARY = 'TEST';

        $event->setVEVENT($vevent);
        $this->assertEquals($event->getVEVENT()->SUMMARY, 'TEST');
    }

    public function testToArray()
    {
        foreach ($this->expected as $f => $expected_value) {
            $vevent = new VObjectEvent($this->resources[$f]->VEVENT, '/url', 'etag');
            $res = $vevent->toArray();
            $this->assertEquals($res, $expected_value, $f . ' parse result wasn\'t the expected one');
        }
    }

}
