<?php

namespace EasyTransac\Entities;

/**
 * Represents arguments for request "Cancellation"
 * @author Klyde
 * @copyright EasyTransac
 */
class Cancellation extends Entity
{
    /** @map:Tid **/
    protected $tid = null;
    /** @map:Language **/
    protected $language = null;

    public function setTid($tid)
    {
        $this->tid = $tid;
        return $this;
    }

    public function setLanguage($language)
    {
        $this->language = $language;
        return $this;
    }
}

?>