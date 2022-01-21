<?php


namespace TpReleaseNotes\Youtrack;


class Client {
    /**
     * @var string
     */
    protected $host;

    /**
     * @var string
     */
    protected $token;


    /**
     * @var \GuzzleHttp\Client
     */
    protected $client;

    /**
     * Client constructor.
     * @param string $host
     * @param string $token
     */
    public function __construct($host, $token) {
        $this->host    = $host;
        $this->token   = $token;

        $clientOptions = [
            "base_uri" => "{$this->getUrl()}/api/",
            "headers" => [
                "Authorization" => "Bearer perm:$token"
            ],
        ];

        $this->client = new \GuzzleHttp\Client($clientOptions);
    }

    /**
     * @param string $id
     * @return Issue
     */
    public function getIssue($id) {
        $request = [
            "fields" => "description,summary,fields(projectCustomField(field(name)),value(name)),created",
        ];

        try {
            $request = $this->client->get("issues/{$id}", ["query" => $request]);
            $body_contents = $request->getBody()->getContents();

            return new Issue($id, json_decode($body_contents, true), $this);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * @return array
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getProjects($filter = null) {
        $request = [
            "fields"    => "id,name,shortName",
        ];

        if ($filter) {
            $request["query"] = $filter;
        }

        $request = $this->client->get("admin/projects", ["query" => $request]);
        $body_contents = $request->getBody()->getContents();

        $result = [];

        foreach (json_decode($body_contents, true) as $pr) {
            $result[$pr['shortName']] = $pr;
        }

        return $result;
    }

    public function getProjectId($shortName) {
        foreach ($this->getProjects($shortName) as $p) {
            if ($p['shortName'] === $shortName) {
                return $p['id'];
            }
        }

        return null;
    }

    public function createIssue($projectShortName, $title, $text = "") {
        $r = [
            "project"       => ["id" => $this->getProjectId($projectShortName)],
            "summary"       => $title,
            "description"   => $text,
        ];

        $query = [
            "fields" => "numberInProject,description,summary,fields(projectCustomField(field(name)),value(name)),created",
        ];

        $request = $this->client->post("issues", [\GuzzleHttp\RequestOptions::JSON => $r, "query" => $query]);

        $body_contents = $request->getBody()->getContents();
        $response = json_decode($body_contents, true);

        $issue_code = "{$projectShortName}-{$response["numberInProject"]}";

        return new Issue($issue_code, json_decode($body_contents, true), $this);
    }

    public function updateIssue($issueId, $r) {
        $query = [
            "fields" => "numberInProject,description,summary,fields(projectCustomField(field(name)),value(name)),created",
        ];

        $request = $this->client->post("issues/$issueId", [\GuzzleHttp\RequestOptions::JSON => $r, "query" => $query]);
        $body_contents = $request->getBody()->getContents();
        $response = json_decode($body_contents, true);

        return new Issue($issueId, json_decode($body_contents, true), $this);
    }

    public function applyCommand($command, $issueIds = []) {
        $request = [
            "query"     => $command,
            "issues"    => [],
        ];

        if (!count($issueIds)) {
            return null;
        }

        foreach ($issueIds as $issueId) {
            $request["issues"][]["idReadable"] = $issueId;
        }

        try {
            $this->client->post("commands", [\GuzzleHttp\RequestOptions::JSON => $request]);
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function getUrl() {
        return "https://{$this->host}/youtrack";
    }
}