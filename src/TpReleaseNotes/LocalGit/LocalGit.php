<?php
/**
 * @author artyfarty
 */

namespace TpReleaseNotes\LocalGit;


class LocalGit {
    protected $path;

    function __construct($path = ".") {
        $this->path = $path;
    }

    function command($cmd) {
        $descriptorspec = array(
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        );
        $pipes = [];

        $resource = proc_open($cmd, $descriptorspec, $pipes, $this->path);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        foreach ($pipes as $pipe) {
            fclose($pipe);
        }
        $status = trim(proc_close($resource));
        if ($status) throw new \Exception($stderr);

        return $stdout;
    }

    /**
     * @param bool $filter
     * @return Tag[]
     * @throws \Exception
     */
    function tags($filter = false) {
        $tagSpecs = [];
        $cmdResult = $this->command("git for-each-ref --sort='-*authordate' 'refs/tags' --format='%(*refname) %(*objectname):%(*authordate:iso8601)'");

        foreach (preg_split("![\n\r]+!", $cmdResult) as $tagLine) {
            try {
                $tagSpec = Tag::fromRefDesc($tagLine);
                if ($filter && !preg_match('!^\d+(\.\d+){1,2}$!siu', $tagSpec->name)) {
                    continue;
                }

                $tagSpecs[] = $tagSpec;
            } catch (\Exception $e) {
                continue;
            }
        }

        return $tagSpecs;
    }

    /**
     * @param string $params
     * @return string[]
     * @throws \Exception
     */
    function rev_list($params = "") {
        $revs = [];
        $cmdResult = $this->command("git rev-list $params");

        foreach (preg_split("![\n\r]+!", $cmdResult) as $rev) {
            $revs[] = $rev;
        }

        return $revs;
    }

    function current_rev() {
        return $this->command("git rev-parse HEAD");
    }

    function fetch() {
        $this->command("git fetch");
    }
}