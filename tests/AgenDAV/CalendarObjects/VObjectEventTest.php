<?php
namespace AgenDAV\CalendarObjects;

class VObjectEventTest extends \PHPUnit_Framework_TestCase
{

    protected $resources;

    protected $expected;

    public function setUp()
    {
        $this->expected = array(
            'basic_allday_1.ics' => array(
                'href' => '/url',
                'etag' => 'etag',
                'allDay' => true,
                'title' => 'Basic all day event',
                'start' => '2013-01-09T00:00:00+0000',
                'end' => '2013-01-10T00:00:00+0000',
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
            $this->assertEquals($res, $expected_value);
        }
    }

}
