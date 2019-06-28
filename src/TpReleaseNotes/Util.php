<?php
/**
 * @author artyfarty
 */

namespace TpReleaseNotes;


use Github\Api\ApiInterface;
use Github\Client;
use Github\ResultPager;

class Util {
    /**
     * @param Client $client
     * @param string $api
     * @param string $method
     * @param array $parameters
     * @param callable $callback
     */
    public static function paginateAll($client, $api, $method, array $parameters = [], $callback) {
        $pager = new ResultPager($client);

        $apiClass = "\\Github\\Api\\$api";

        $block = $pager->fetch(new $apiClass($client), $method, $parameters);
        $i = 1;

        do {
            self::l("Processing page #$i of $api/$method", "debug");
            foreach ($block as $item) {
                $retCode = $callback($item);

                if ($retCode == "stop") {
                    break 2;
                }
            }

            $block = $pager->fetchNext();
            $i++;
        } while ($pager->hasNext());
    }

    public static function l($message, $type = "info") {
        echo "[$type] $message\n";
    }

    public static function d($var) {
        var_dump($var);
    }

    public static function truncateMinorVersion($tag) {
        return preg_replace("#\\.0$#siu", "", $tag);
    }
}