<?php

namespace TpReleaseNotes\Github;

class PrAggregated implements \ArrayAccess
{
    /**
     * @var array
     */
    public $pr_object;

    /**
     * @var int
     */
    public $number;

    /** @var string */
    public $title;

    /** @var string */
    public $url;

    public $repo;

    public $commits;

    /**
     * @var self[]
     */
    public $children = [];

    public function __construct($gh_pr) {
        $this->pr_object = $gh_pr;

        $this->number   = +$gh_pr["number"];
        $this->url      = $gh_pr["html_url"];
        $this->title    = $gh_pr["title"];
        $this->repo     = $gh_pr["head"]["repo"]["name"];
    }

    public function offsetExists($offset)
    {
       return array_key_exists($offset, $this->pr_object);
    }

    public function offsetGet($offset)
    {
        return $this->pr_object[$offset];
    }

    public function offsetSet($offset, $value)
    {
        $this->pr_object[$offset] = $value;
    }

    public function offsetUnset($offset)
    {
        unset($this->pr_object[$offset]);
    }
}