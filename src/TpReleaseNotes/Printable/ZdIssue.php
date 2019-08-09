<?php


namespace TpReleaseNotes\Printable;


class ZdIssue {
    public $zd_id;

    public $zd_url;

    public $zd_name;

    public $zd_requester;

    public $zd_requester_name;

    public static function fromZdIssue($zdIssue) {
        $s = new self;

        $s->zd_id = $zdIssue->ticket->id;
        $s->zd_name = $zdIssue->ticket->subject;

        // https://timepad.zendesk.com/api/v2/tickets/79910.json
        $api_url = $zdIssue->ticket->url;
        preg_match("#//(?'domain'.+)\\.zendesk.com/api/#siu", $api_url, $url_matches);
        $s->zd_url = "https://{$url_matches['domain']}.zendesk.com/agent/tickets/{$s->zd_id}";

        foreach ($zdIssue->users as $u) {
            if ($u->id == $zdIssue->ticket->requester_id) {
                $s->zd_requester = "{$u->name} &lt;{$u->email}&gt;";
                $s->zd_requester_name = $u->name;
            }
        }

        return $s;
    }

    public function printString($format = "mail") {
        $result_strings = [];

        if ($format === "mail") {
            $result_strings[] = "[Zendesk #{$this->zd_id}]({$this->zd_url}) {$this->zd_name} – *{$this->zd_requester}*";
        } elseif ($format === "tg") {
            $result_strings[] = "Zendesk #{$this->zd_id} {$this->zd_name} – {$this->zd_requester_name}";
        }

        return implode(" ", $result_strings);
    }
}