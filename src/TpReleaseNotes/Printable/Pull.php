<?php


namespace TpReleaseNotes\Printable;


use Symfony\Component\Console\Output\OutputInterface;
use TpReleaseNotes\Printable\YtIssue as PrintableYtIssue;
use TpReleaseNotes\Printable\ZdIssue as ZdPrintable;

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

    /**
     * @param array $pull
     * @param \TpReleaseNotes\Youtrack\Client $yt_client
     * @param \Zendesk\API\HttpClient $zd_client
     * @param OutputInterface $logger
     * @return Pull
     */
    public static function createFromGHPull($pull, $yt_client = null, $zd_client = null, $logger = null) {
        $l = function ($msg) use ($logger) {
            if ($logger) {
                $logger->writeln($msg);
            }
        };

        $p_pull = new self($pull['number']);
        $p_pull->pull_author = $pull['user']['login'];
        $p_pull->pull_url = $pull['html_url'];

        $notes_lines = [];
        $prelude_lines = [];
        $youtrack_ids = [];

        foreach (preg_split("#[\n\r]+#u", $pull['body']) as $bodyLine) {
            if (preg_match("#^[\\d*]\\.?\\s*\\[(new|bfx|ref|del)](\\[.{1,2}])?#siu", $bodyLine)) {
                $notes_lines[] = $bodyLine;
            } elseif ($yt_client && preg_match("#/youtrack/issue/(?'issueId'[a-z]+-[0-9]+)#siu", $bodyLine, $matches)) {
                $youtrack_ids[] = $matches['issueId'];
                $l("{$pull['number']} atatches to {$matches['issueId']}");
            } elseif (!count($notes_lines)) {
                // Строчки с контентом до ноутсов запишем
                $prelude_lines[] = $bodyLine;
            }
        }

        $p_pull->pull_notes     = implode("\n", $notes_lines);
        $p_pull->pull_prelude   = trim(implode("  \n", $prelude_lines));
        $p_pull->pull_title     = preg_replace('!\\#\\d+!siu', '', $pull['title']);

        if ($yt_client) {
            $youtrack_ids = array_unique($youtrack_ids);
            foreach ($youtrack_ids as $youtrack_id) {
                $youtrack_issue = $yt_client->getIssue($youtrack_id);

                if ($youtrack_issue) {
                    $l("Got $youtrack_id data, adding");
                    $p_pull->addYtIssue(PrintableYtIssue::fromIssue($youtrack_issue));

                    if (preg_match_all("#zendesk.com/agent/tickets/(?'zd_id'[0-9]+)#siu", $youtrack_issue->getBody(), $zd_matches)) {
                        foreach ($zd_matches["zd_id"] as $zd_id) {
                            $l("Got ZD #$zd_id");

                            try {
                                $zd_issue = $zd_client->tickets()->sideload(['users'])->find($zd_id);

                                if ($zd_issue) {
                                    $p_pull->addZdIssue(ZdPrintable::fromZdIssue($zd_issue));
                                }

                            } catch (\Throwable $e) {
                                $l("Can't load #$zd_id");
                            }
                        }
                    }
                }
            }
        }

        return $p_pull;
    }

    public function addYtIssue(YtIssue $yti) {
        $this->yt_issues[] = $yti;
    }

    public function addZdIssue(ZdIssue $zdi) {
        $this->zd_issues[] = $zdi;
    }

    public function printSting($format = "mail") {
        if ($format === "mail") {
            return $this->printStingForMail();
        } elseif ($format === "tg") {
            return $this->printStingForTg();
        }
    }

    public function printStingForMail() {
        $result_strings = [];
        $format = "mail";

        $result_strings[] = "#### {$this->pull_title}";
        $result_strings[] = "* Github: [#{$this->pull_id}]({$this->pull_url}) by @{$this->pull_author}  ";

        foreach ($this->yt_issues as $yti) {
            $result_strings[] = "* {$yti->printString($format)}  ";
        }

        foreach ($this->zd_issues as $zdi) {
            $result_strings[] = "* {$zdi->printString($format)}  ";
        }

        if ($this->pull_prelude) {
            $result_strings[] = "";
            $result_strings[] = $this->pull_prelude;
            $result_strings[] = "";
        }

        $result_strings[] = $this->pull_notes;

        return implode("\n", $result_strings);
    }

    public function printStingForTg() {
        $result_strings = [];
        $format = "tg";

        $result_strings[] = "🎈 {$this->pull_title}";

        foreach ($this->yt_issues as $yti) {
            $result_strings[] = "➡️ {$yti->printString($format)}";
        }

        foreach ($this->zd_issues as $zdi) {
            $result_strings[] = "➡️ {$zdi->printString($format)}  ";
        }

        return implode("\n", $result_strings);
    }

    public function printStingForTracker() {
        $result_strings = [];
        $format = "mail";

        $result_strings[] = "## {$this->pull_title}";
        $result_strings[] = "Github: [#{$this->pull_id}]({$this->pull_url})";

        foreach ($this->yt_issues as $yti) {
            $result_strings[] = "* {$yti->printString($format)}  ";
        }

        return implode("\n", $result_strings);
    }
}