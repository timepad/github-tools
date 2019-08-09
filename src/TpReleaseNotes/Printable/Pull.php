<?php


namespace TpReleaseNotes\Printable;


class Pull {
    public $pull_id;

    public $pull_title;

    public $pull_notes;

    public $pull_prelude;

    public $pull_author;

    public $pull_url;

    /** @var YtIssue[] */
    public $yt_issues = [];

    /** @var ZdIssue[] */
    public $zd_issues = [];

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

    public function addZdIssue(ZdIssue $zdi) {
        $this->zd_issues[] = $zdi;
    }

    public function printSting($format = "mail") {
        $result_strings = [];

        if ($format === "mail") {
            $result_strings[] = "#### {$this->pull_title}";
            $result_strings[] = "* Github: [#{$this->pull_id}]({$this->pull_url}) by @{$this->pull_author}  ";
        } elseif ($format === "tg") {
            $result_strings[] = "🎈 {$this->pull_title}";
        }

        foreach ($this->yt_issues as $yti) {
            if ($format === "mail") {
                $result_strings[] = "* {$yti->printString($format)}  ";
            } elseif ($format === "tg") {
                $result_strings[] = "➡️ {$yti->printString($format)}";
            }
        }

        foreach ($this->zd_issues as $zdi) {
            if ($format === "mail") {
                $result_strings[] = "* {$zdi->printString($format)}  ";
            } elseif ($format === "tg") {
                $result_strings[] = "➡️ {$zdi->printString($format)}  ";
            }
        }

        if ($format === "mail") {
            if ($this->pull_prelude) {
                $result_strings[] = "";
                $result_strings[] = $this->pull_prelude;
                $result_strings[] = "";
            }

            $result_strings[] = $this->pull_notes;
        }

        return implode("\n", $result_strings);
    }
}