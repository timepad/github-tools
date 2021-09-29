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