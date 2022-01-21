<?php

namespace TpReleaseNotes\Github;

class CommitsExtrasClient extends \Github\Api\Repository\Commits
{
    public function commitPulls($username, $repository, $sha, array $parameters = [])
    {
        return $this->get('/repos/'.rawurlencode($username).'/'.rawurlencode($repository).'/commits/'.rawurlencode($sha).'/pulls', $parameters);
    }
}