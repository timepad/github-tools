<?php


namespace TpReleaseNotes\Printable;


class ZdIssue {
    public $zd_id;

    public $zd_url;

    public $zd_name;

    public static function fromZdIssue($zdIssue) {
        $s = new self;

        $s->zd_id = $zdIssue->ticket->id;
        $s->zd_url = $zdIssue->ticket->url;
        $s->zd_name = $zdIssue->ticket->subject;

        return $s;
    }

    public function printString() {
        $result_strings = [];

        $result_strings[] = "[Zendesk #{$this->zd_id}]({$this->zd_url}) {$this->zd_name}";

        return implode(" ", $result_strings);
    }
}