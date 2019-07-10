<?php


namespace TpReleaseNotes\Youtrack;


class Issue {
    /**
     * @var string
     */
    public $id;

    /**
     * @var array
     */
    protected $data;

    /** @var Client */
    protected $client;

    /**
     * Issue constructor.
     * @param $id
     * @param $data
     * @param $client
     */
    public function __construct($id, $data, $client) {
        $this->id = $id;
        $this->data = $data;
        $this->client = $client;
    }

    public function getName() {
        return $this->data["summary"];
    }

    public function getBody() {
        return $this->data["description"];
    }

    /** @return string[] */
    public function getTags() {
        $result = [];

        foreach ($this->data["tags"] as $tag) {
            $result[] = $tag["name"];
        }

        return $result;
    }

    protected function getCustomField($field) {
        foreach ($this->data["fields"] as $customField) {
            if ($customField["projectCustomField"]["field"]["name"] == $field) {
                return $customField;
            }
        }

        return null;
    }

    public function getCustomFieldStringValue($field, $nulls = ["?"]) {

        $field = $this->getCustomField($field);

        if ($field) {
            $fieldValue = $field["value"]["name"];

            if (in_array($fieldValue, $nulls)) {
                return null;
            }

            return $fieldValue;
        }

        return null;
    }

    public function getSize() {
        return $this->getCustomFieldStringValue("Size");
    }

    public function getPriority() {
        return $this->getCustomFieldStringValue("Priority");
    }

    public function getAssignee() {
        return $this->getCustomFieldStringValue("Assignee");
    }

    public function getDepartment() {
        return $this->getCustomFieldStringValue("Department");
    }

    public function getType() {
        return $this->getCustomFieldStringValue("Type");
    }

    public function getUrl() {
        return "{$this->client->getUrl()}/issue/{$this->id}";
    }

}