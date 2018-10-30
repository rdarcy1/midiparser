<?php

namespace Tmont\Midi\Event;

abstract class NoteEvent extends ChannelEvent
{
    /**
     * Get the velocity of the note.
     *
     * @return int|null
     */
    public function getVelocity()
    {
        return $this->param2;
    }

    /**
     * Get the note number.
     *
     * @return int
     */
    public function getNoteNumber()
    {
        return $this->param1;
    }
}
