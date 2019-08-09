<?php


namespace TpReleaseNotes\Printable;


use TpReleaseNotes\Youtrack\Issue;

class YtIssue {
    public $yt_id;

    public $yt_title;

    public $yt_url;

    public $yt_department;

    public $yt_type;

    public $yt_size;

    public function printString($format = "mail") {
        $result_strings = [];

        if ($format === "mail") {
            $result_strings[] = "[{$this->yt_id}]({$this->yt_url}) {$this->yt_title} – {$this->yt_type}";

            if ($this->yt_department) {
                $result_strings[] = "от *{$this->yt_department}*";
            }

            if ($this->yt_size) {
                $result_strings[] = "[{$this->yt_size}]";
            }
        } elseif ($format === "tg") {
            $result_strings[] = "{$this->yt_id} {$this->yt_title} – {$this->yt_type}";

            if ($this->yt_department) {
                $result_strings[] = "от {$this->yt_department}";
            }
        }

        return implode(" ", $result_strings);
    }

    /**
     * @param Issue $issue
     * @return self
     */
    public static function fromIssue(Issue $issue) {
        $yi = new self;

        $yi->yt_id = $issue->id;
        $yi->yt_title = $issue->getName();
        $yi->yt_url = $issue->getUrl();
        $yi->yt_department = $issue->getDepartment();
        $yi->yt_type = $issue->getType();
        $yi->yt_size = $issue->getSize();

        return $yi;
    }
}