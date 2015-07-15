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
     * @var DateTime $date
     */
    public $date;

    public function __construct($name, $ref, $date) {
        $this->name = $name;
        $this->ref = $ref;
        $this->date = $date;
    }

    /**
     * @param $refLine
     * @return Tag
     * @throws \Exception
     */
    public static function fromRefDesc($refLine) {
        if (preg_match("!refs/tags/(?'tag'.*)\\^\\{\\}\\s+(?'ref'.*?):(?'date'.*)!siu", $refLine, $tagComponents)) {
            return new self($tagComponents['tag'], $tagComponents['ref'], date_create($tagComponents['date']));
        }
        else throw new \Exception("bad line $refLine");
    }
}