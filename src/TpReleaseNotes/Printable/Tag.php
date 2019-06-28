<?php


namespace TpReleaseNotes\Printable;


use TpReleaseNotes\Util;

class Tag {
    /**
     * @var string \DateTime
     */
    public $tag;

    /**
     * @var \DateTime
     */
    public $date;

    /**
     * @var Pull[]
     */
    public $pulls = [];

    /**
     * Tag constructor.
     * @param  $tag
     * @param \DateTime         $date
     */
    public function __construct($tag, $date) {
        $this->tag  = $tag;
        $this->date = $date;
    }


    /**
     * @param Pull $pull
     */
    public function addPull($pull) {
        $this->pulls[$pull->pull_id] = $pull;
    }

    public function printSting($print_title = false) {
        $truncate_minor = function($tag) {
            return Util::truncateMinorVersion($tag);
        };

        $result_strings = [];

        if ($print_title) {
            $result_strings[] = "## Версия {$truncate_minor($this->tag)}";
        }

        if ($this->date) {
            $result_strings[] = "*{$this->date->format('d.m.Y H:i')}*";
        }

        $result_strings[] = "";

        foreach ($this->pulls as $pullInfo) {
            $result_strings[] = $pullInfo->printSting();
        }

        return implode("\n", $result_strings);
    }
}