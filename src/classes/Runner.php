<?php

use Mimey\MimeTypes as Mimey;

/**
 * Class Runner
 */
class Runner
{
    const URI = "https://api.livejournal.com/os/2.0/rest/entries/27596127/@self/@posted/";

    private $_config;
    private $_mime;
    private static $_instance;
    private $_s3;
    private $_last_id;
    private $_last_page;

    /**
     * Runner constructor.
     */
    private function __construct()
    {
        $this->_config = json_decode(file_get_contents("config.json"));
        $this->_mime = new Mimey;
        $this->_s3 = new S3Wrapper;
        $this->_last_page = $this->_config->last_page;
        $this->_last_id = $this->_config->last_id;
    }

    /**
     * @return mixed
     */
    public static function i()
    {
        if (is_null(static::$_instance))
            static::$_instance = new static();
        return static::$_instance;
    }

    /**
     * @return mixed
     */
    public function getLastId()
    {
        return $this->_last_id;
    }

    /**
     * @param mixed $last_id
     */
    public function setLastId($last_id)
    {
        $this->_last_id = $last_id;
    }

    /**
     * @return mixed
     */
    public function getLastPage()
    {
        return $this->_last_page;
    }

    /**
     * @param mixed $last_page
     */
    public function setLastPage($last_page)
    {
        $this->_last_page = $last_page;
    }

    /**
     * @param $post_id
     * @param $image_name
     * @return string
     */
    private function getImagePathName($post_id, $image_name)
    {
        return "/" . $post_id . "/" . $image_name;
    }

    /**
     * @param $post_id
     * @param $image_name
     * @return string
     */
    private function getFileUrl($post_id, $image_name)
    {
        return "https://" . S3Config::IMAGES_BUCKET . "." . S3Config::HOST
            . $this->getImagePathName($post_id, $image_name);
    }

    /**
     * @param $data
     * @return array
     */
    private function processData($data)
    {
        $result = [];
        // regexp for images in the posts
        $re = '/<img src="([0-9a-zA-Z\.\/:\-_]+)"./m';
        foreach ($data as $item) {
            $last_id = $this->getLastId();
            // if we start from the beginning or next post id less than current
            if ($last_id == 0 || $item->id < $last_id) {
                // save some information about post
                $result['title'] = $item->title;
                $result['digest'] = $item->digest;
                $result['created'] = $item->created;
                $result['api_uri'] = $item->uri;
                $result['uri'] = $item->url;
                $result['event_time'] = $item->eventTime;
                $result['tags'] = $item->tags;
                // fine all images in the post
                preg_match_all($re, $item->body, $matches, PREG_SET_ORDER, 0);
                $i = 0;
                foreach ($matches as $found) {
                    // get mime type
                    $mime = image_type_to_mime_type(exif_imagetype($found[1]));
                    // save original image path to images array
                    $result['images'][] = $found[1];
                    // set new name
                    $image_name = $i . "." . $this->_mime->getExtension($mime);
                    // put it to S3
                    $this->_s3->saveImage($this->getImagePathName($item->id, $image_name), file_get_contents($found[1]));
                    // replace image's value in body
                    $item->body = str_replace($found[1],
                        $this->getFileUrl($item->id, $image_name), $item->body);
                    $i++;
                }
                // set the body value
                $result['body'] = $item->body;
                $this->_s3->savePost($item->id . ".json", json_encode($result));
                // set last LJ post's id
                $this->setLastId($item->id);
            }
        }
        // set last LJ's page
        $this->setLastPage($this->getLastPage() + 1);
        return $result;
    }

    /**
     *
     */
    public function go()
    {
        while (true) {
            // send request to LJ
            $result = json_decode(file_get_contents(self::URI . "?" . http_build_query(
                    [
                        'count' => $this->_config->on_page,
                        'page' => $this->_config->last_page
                    ]
                )
            ));
            try {
                $this->processData($result->entry);
            } catch (Exception $e) {
                // TODO: handle diff types of Exceptions (AWS, Inner)
            }
            // save config
            $this->_config->last_id = $this->getLastId();
            $this->_config->last_page = $this->getLastPage();
            file_put_contents("config.json", json_encode($this->_config));
            // sleep to be not banned
            sleep($this->_config->timeout);
        }

    }

}