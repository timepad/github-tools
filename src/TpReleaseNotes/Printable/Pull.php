<?php


namespace TpReleaseNotes\Printable;


class Pull {
    public $pull_id;

    public $pull_title;

    public $pull_notes;

    public $pull_author;

    public $pull_url;

    /**
     * Pull constructor.
     * @param $pull_id
     */
    public function __construct($pull_id) {
        $this->pull_id    = $pull_id;
    }


    public function printSting() {
        $result_strings = [];

        $result_strings[] = "#### {$this->pull_title}";
        $result_strings[] = "Pull Request [#{$this->pull_id}]({$this->pull_url}) by @{$this->pull_author}";
        $result_strings[] = "";

        $result_strings[] = $this->pull_notes;

        return implode("\n", $result_strings);
    }
}