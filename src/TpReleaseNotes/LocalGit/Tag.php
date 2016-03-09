<?php
/**
 * @author artyfarty
 */

namespace TpReleaseNotes\LocalGit;


use TpReleaseNotes\Util;

class Tag {
    public $name;
    public $ref;
    /**
     * @var \DateTime $date
     */
    public $date;

    /** @var string */
    public $date_raw;

    public function __construct($name, $ref, $date) {
        $this->name = $name;
        $this->ref = $ref;
        $this->date_raw = $date;
        $this->date = date_create($date);
    }

    /**
     * @param $refLine
     * @return Tag
     * @throws \Exception
     */
    public static function fromRefDesc($refLine) {
        if (preg_match("!refs/tags/(?'tag'.*)\\^\\{\\}\\s+(?'ref'.*?):(?'date'.*)!siu", $refLine, $tagComponents)) {
            return new self($tagComponents['tag'], $tagComponents['ref'], $tagComponents['date']);
        }
        else throw new \Exception("bad line $refLine");
    }
}