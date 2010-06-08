<?php

	/**
	 * \Midi\Parsing\EventParser
	 *
	 * @package    Midi
	 * @subpackage Parsing
	 * @copyright  � 2009 Tommy Montgomery <http://phpmidiparser.com/>
	 * @since      1.0
	 */

	namespace Midi\Parsing;
	
	use Midi\Event;
	use Midi\Event\EventType;
	use Midi\Event\ChannelEventFactory;
	use Midi\Util\Util;
	use Midi\MidiException;

	/**
	 * Class for parsing MIDI events
	 *
	 * @package    Midi
	 * @subpackage Parsing
	 * @since      1.0
	 */
	class EventParser extends Parser {
		
		/**
		 * The current continuation event type
		 *
		 * @var int|null
		 */
		protected $continuationEvent;
		
		/**
		 * @var ChannelEventFactory
		 */
		private $channelEventFactory;
		
		/**
		 * @since 1.0
		 *
		 * @param ChannelEventFactory $channelEventFactory
		 */
		public function __construct(ChannelEventFactory $channelEventFactory = null) {
			parent::__construct();
			
			$this->continuationEvent = null;
			$this->channelEventFactory = $channelEventFactory ?: new ChannelEventFactory();
		}
		
		/**
		 * Meta event factory
		 *
		 * If the event type does not exist, then an {@link UnknownMetaEvent}
		 * object is returned.
		 *
		 * @since 1.0
		 * @uses  Util::unpack()
		 * @todo  Factor this out into its own class
		 * 
		 * @param  int           $type See {@link MetaEventType}
		 * @param  string|binary $data
		 * @return MetaEvent
		 */
		public function getMetaEvent($type, $data) {
			switch ($type) {
				case Event\MetaEventType::SEQUENCE_NUMBER:
					$data = Util::unpack($data);
					return new Event\SequenceNumberEvent($data[0], $data[1]);
				case Event\MetaEventType::TEXT_EVENT:
					return new Event\TextEvent($data);
				case Event\MetaEventType::COPYRIGHT_NOTICE:
					return new Event\CopyrightNoticeEvent($data);
				case Event\MetaEventType::TRACK_NAME:
					return new Event\TrackNameEvent($data);
				case Event\MetaEventType::INSTRUMENT_NAME:
					return new Event\InstrumentNameEvent($data);
				case Event\MetaEventType::LYRICS:
					return new Event\LyricsEvent($data);
				case Event\MetaEventType::MARKER:
					return new Event\MarkerEvent($data);
				case Event\MetaEventType::CUE_POINT:
					return new Event\CuePointEvent($data);
				case Event\MetaEventType::END_OF_TRACK:
					return new Event\EndOfTrackEvent();
				case Event\MetaEventType::CHANNEL_PREFIX:
					$data = Util::unpack($data);
					return new Event\ChannelPrefixEvent($data[0]);
				case Event\MetaEventType::SET_TEMPO:
					$data = Util::unpack($data);
					$mpqn = ($data[0] << 16) | ($data[1] << 8) | $data[2];
					return new Event\SetTempoEvent($mpqn);
				case Event\MetaEventType::SMPTE_OFFSET:
					$data      = Util::unpack($data);
					$frameRate = ($data[0] >> 5) & 0xFF;
					$hour      = $data[0] & 0x1F;
					$minute    = $data[1];
					$second    = $data[2];
					$frame     = $data[3];
					$subFrame  = $data[4];
					return new Event\SmpteOffsetEvent($frameRate, $hour, $minute, $second, $frame, $subFrame);
				case Event\MetaEventType::TIME_SIGNATURE:
					$data = Util::unpack($data);
					return new Event\TimeSignatureEvent($data[0], pow(2, $data[1]), $data[2], $data[3]);
				case Event\MetaEventType::KEY_SIGNATURE:
					$data = Util::unpack($data);
					return new Event\KeySignatureEvent($data[0], $data[1]);
				case Event\MetaEventType::SEQUENCER_SPECIFIC:
					return new Event\SequencerSpecificEvent($data);
				default:
					return new Event\UnknownMetaEvent($data);
			}
		}
		
		/**
		 * System exclusive event factory
		 *
		 * @since 1.0
		 * 
		 * @param  string|binary $data
		 * @return SystemExclusiveEvent
		 */
		public function getSystemExclusiveEvent($data) {
			return new Event\SystemExclusiveEvent($data);
		}
		
		/**
		 * @since 1.0
		 * @uses  read()
		 * @uses  Util::unpack()
		 * @uses  parseChannelEvent()
		 * @uses  parseMetaEvent()
		 * @uses  parseSystemExclusiveEvent()
		 * 
		 * @throws {@link ParseException}
		 * @return Event
		 */
		public function parse() {
			$byte = $this->read(1, true);
			
			$eventType = Util::unpack($byte);
			$eventType = $eventType[0];
			$isContinuation = false;
			
			if ($eventType < 0x80) {
				if ($this->continuationEvent === null) {
					throw new ParseException('Invalid event: first byte must be greater than or equal to 0x80');
				} else {
					$eventType = $this->continuationEvent;
					$isContinuation = true;
					//rewind one byte so that parseChannelEvent() doesn't throw an exception
					//when it can't find two more bytes
					$this->file->fseek(-1, SEEK_CUR);
				}
			} else {
				$this->continuationEvent = $eventType;
			}
			
			if ($eventType < 0xF0) {
				return $this->parseChannelEvent($eventType, $isContinuation);
			} else if ($eventType === 0xFF) {
				return $this->parseMetaEvent();
			} else if ($eventType === 0xF0) {
				return $this->parseSystemExclusiveEvent();
			}
			
			throw new ParseException('Unsupported event type: 0x' . strtoupper(dechex($eventType)));
		}
		
		/**
		 * Parses the buffer stream for a channel event
		 *
		 * @since 1.0
		 * @uses  Util::unpack()
		 * @uses  getChannelEvent()
		 * @uses  ChannelEvent::setContinuation()
		 * @uses  ChannelEventFactory::create()
		 * 
		 * @param  int  $eventType      See {@link EventType}
		 * @param  bool $isContinuation Whether the event is a continuation of a previous event
		 * @return ChannelEvent
		 */
		protected function parseChannelEvent($eventType, $isContinuation) {
			$type = $eventType & 0xF0;
			if ($type === 0xC0 || $type === 0xD0) {
				$data = Util::unpack($this->read(1, true));
				$data[1] = null;
			} else {
				$data = Util::unpack($this->read(2, true));
			}
			
			$event = $this->channelEventFactory->create($eventType & 0xF0, $eventType & 0x0F, $data[0], $data[1]);
			if ($isContinuation) {
				$event->setContinuation(true);
			}
			
			return $event;
		}
		
		/**
		 * Parses the buffer stream for a meta event
		 *
		 * @since 1.0
		 * @uses  read()
		 * @uses  Util::unpack()
		 * @uses  getDelta()
		 * @uses  getMetaEvent()
		 * 
		 * @return MetaEvent
		 */
		protected function parseMetaEvent() {
			$metaEventType = Util::unpack($this->read(1, true));
			$metaEventType = $metaEventType[0];
			
			$length        = $this->getDelta();
			$data          = $this->read($length, true);
			return $this->getMetaEvent($metaEventType, $data);
		}
		
		/**
		 * Parses the buffer stream for a system exclusive event
		 *
		 * @since 1.0
		 * @uses  getDelta()
		 * @uses  read()
		 * @uses  getSystemExclusiveEvent()
		 * 
		 * @return SystemExclusiveEvent
		 */
		protected function parseSystemExclusiveEvent() {
			$length = $this->getDelta();
			$data   = $this->read($length, true);
			return $this->getSystemExclusiveEvent(str_split($data));
		}
		
	}

?>