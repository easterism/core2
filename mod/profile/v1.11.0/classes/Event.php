<?php
/**
 * Created by PhpStorm.
 * User: easter
 * Date: 19.11.2017
 * Time: 23:36
 */

namespace Core2\Profile;
use Sse\Events\TimedEvent;

class Event extends TimedEvent { // Beware: use SSETimedEvent for sending data at a regular interval

    public $period = 20; // the interval in seconds
    private $that;

    public function __construct(\ModProfileController $that)
    {
        $this->that = $that;
    }

    public function update(){
        return count($this->that->api->getUnreadMsg());
    }
}