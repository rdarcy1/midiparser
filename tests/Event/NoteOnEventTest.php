<?php
	
	namespace Tmont\Midi\Tests\Event;

	use PHPUnit_Framework_TestCase;
	use Tmont\Midi\Event\EventType;
	use Tmont\Midi\Event\NoteOnEvent;
	use Tmont\Midi\Util\Note;

	class NoteOnEventTest extends PHPUnit_Framework_TestCase {
		/**
		 * @var NoteOnEvent
		 */
		private $obj;
		
		public function setUp() {
			$this->obj = new NoteOnEvent(1, Note::A4, 0x64);
		}
		
		public function tearDown() {
			$this->obj = null;
		}
		
		public function testGetParamDescription() {
			$this->assertEquals('A4 with velocity 100', $this->obj->getParamDescription());
		}
		
		public function testGetType() {
			$this->assertSame(EventType::NOTE_ON, $this->obj->getType());
		}

        public function testIsNoteOff()
        {
        	$noteOff = new NoteOnEvent(1, Note::A4, 0);

			$this->assertFalse($this->obj->isNoteOff());
			$this->assertTrue($noteOff->isNoteOff());
		}

        public function testGetVelocity()
        {
            $this->assertSame(100, $this->obj->getVelocity());
		}

        public function testGetNoteNumber()
        {
            $this->assertSame(Note::A4, $this->obj->getNoteNumber());
        }
	}

?>
