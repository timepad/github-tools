<?php
/**
 * @author artyfarty
 */

namespace TpReleaseNotes\Printable;


class IssueStory {
    protected $id;


    protected $assignees = [];
    protected $committers = [];
    protected $name;

    protected $returned = 0;
    protected $done = false;
    protected $isBug = false;

    public function __construct($id) {
        $this->id = $id;
    }

    public function setInfo($event) {
        $this->name = $event['issue']['title'];
    }

    public function addEvent($event) {
        if ($event['event'] === "labeled") {
            if (preg_match("!Returned!siu", $event['label']['name'])) {
                $this->returned++;
            }

            if (preg_match("!Done!siu", $event['label']['name'])) {
                $this->done = true;
            }

            if (preg_match("!bug!siu", $event['label']['name'])) {
                $this->isBug = true;
            }
        }

        elseif ($event['event'] === "assigned") {
            $assignee = $event['assignee'];
            if (!isset($this->assignees[$assignee['login']])) {
                $this->assignees[$assignee['login']] = 0;
            }
            $this->assignees[$assignee['login']]++;
        }

        elseif ($event['event'] === "referenced") {
            $actor = $event['actor'];
            if (!isset($this->committers[$actor['login']])) {
                $this->committers[$actor['login']] = 0;
            }
            $this->committers[$actor['login']]++;
        }
    }

    protected function join_name_count($arr) {
        arsort($arr);
        $fl_first = true;

        $temp_arr = [];
        foreach ($arr as $name => $count) {
            $s = "$name ($count)";

            if ($fl_first) {
                $s = "**$s**";
            }

            $temp_arr[] = $s;
            $fl_first = false;
        }

        return implode(", ", $temp_arr);
    }

    public function out() {
        $assignees = $this->join_name_count($this->assignees);
        $committers = $this->join_name_count($this->committers);

        $assigneeNames = array_keys($this->assignees);
        $lastAssignee = end($assigneeNames);

        $projectedOwners = $this->assignees;
        foreach ($this->committers as $committer => $count) {
            if (!isset($projectedOwners[$committer])) {
                $projectedOwners[$committer] = 0;
            }

            $projectedOwners[$committer] += $count;
        }
        $projectedOwners[$lastAssignee]++;
        arsort($projectedOwners);
        $projectedOwners = array_keys($projectedOwners);
        $projectedOwner = $projectedOwners[0];


        $out = <<<EOF
#### #{$this->id} {$this->name}

**Probable owner:** $projectedOwner

**Assigned:** $lastAssignee ($assignees)
**Commits:** $committers
**Возвратов:** {$this->returned}


EOF;

        if ($this->isBug) {
            $out .= "**ЭТАБАГ**\n";
        }

        if ($this->done) {
            $out .= "**ЗАКРЫТА**\n";
        }

        return $out;
    }

    public function isDone() {
        return $this->done;
    }

    /** @var self[]  */
    protected static $stories = [];

    public static function store($event) {
        $number = $event['issue']['number'];

        if (isset($event['issue']['pull_request'])) {
            return;
        }

        if (!isset(self::$stories[$number])) {
            self::$stories[$number] = new self($number);
            self::$stories[$number]->setInfo($event);
        }

        self::$stories[$number]->addEvent($event);
    }

    public static function getStories() {
        return self::$stories;
    }
}