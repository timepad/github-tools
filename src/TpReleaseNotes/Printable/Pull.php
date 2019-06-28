<?php


namespace TpReleaseNotes\Printable;


class Pull {
    public $pull_id;

    public $pull_title;

    public $pull_notes;

    public $pull_author;

    public $pull_url;

    /** @var YtIssue[] */
    public $yt_issues = [];

    /**
     * Pull constructor.
     * @param $pull_id
     */
    public function __construct($pull_id) {
        $this->pull_id    = $pull_id;
    }

    public function addYtIssue(YtIssue $yti) {
        $this->yt_issues[] = $yti;
    }

    public function printSting() {
        $result_strings = [];

        $result_strings[] = "#### {$this->pull_title}";
        $result_strings[] = "* Github: [#{$this->pull_id}]({$this->pull_url}) by @{$this->pull_author}";

        $issue_count = count($this->yt_issues);

        if ($issue_count) {
            foreach ($this->yt_issues as $yti) {
                $result_strings[] = "* {$yti->printString()}";
            }
        }

        $result_strings[] = $this->pull_notes;

        return implode("\n", $result_strings);
    }
}