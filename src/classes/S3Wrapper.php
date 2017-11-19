<?php

use Aws\S3\S3Client as S3;

class S3Wrapper
{
    private $_client;

    public function __construct()
    {
        $this->_client = new S3([
            'version'     => 'latest',
            'region'      => S3Config::REGION,
            'credentials' => [
                'key'    => S3Config::ACCESS_KEY_ID,
                'secret' => S3Config::SECRET_KEY_ID
            ],
            'endpoint' => S3Config::ENDPOINT
        ]);
    }

    private function saveToBucket($name, $content, $bucket)
    {
        $this->_client->putObject([
            'Key'    => $name,
            'Bucket' => $bucket,
            'Body'   => $content,
        ]);
    }

    public function saveImage($name, $content)
    {
        $this->saveToBucket($name, $content, S3Config::IMAGES_BUCKET);
    }

    public function savePost($name, $content)
    {
        $this->saveToBucket($name, $content, S3Config::POSTS_BUCKET);
    }

}